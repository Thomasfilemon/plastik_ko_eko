<?php

namespace App\Console\Commands;

use App\Models\KodeBarang;
use App\Models\PembelianItem;
use App\Models\StockBatch;
use App\Models\Transaksi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseModalFaktur extends Command
{
    protected $signature = 'laporan:diagnose-modal
                            {nota : No transaksi, e.g. KP/WS/0169}
                            {--barang= : Filter nama/kode barang (partial match)}';

    protected $description = 'Diagnose why modal/laba looks wrong on a penjualan faktur (FIFO batch breakdown)';

    public function handle(): int
    {
        $nota = trim((string) $this->argument('nota'));
        $filter = trim((string) $this->option('barang'));

        $transaksi = Transaksi::with([
            'customer',
            'items.transaksiItemSumber.stockBatch.pembelianItem.pembelian',
            'items.transaksiItemSumber.stockBatch.kodeBarang',
        ])->where('no_transaksi', $nota)->first();

        if (!$transaksi) {
            $this->error("Transaksi '{$nota}' tidak ditemukan.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("=== DIAGNOSE MODAL FAKTUR ===");
        $this->line("Nota       : {$transaksi->no_transaksi}");
        $this->line("Tanggal    : " . optional($transaksi->tanggal)->format('Y-m-d H:i:s'));
        $this->line("Customer   : " . (optional($transaksi->customer)->nama ?? $transaksi->kode_customer));
        $this->line("Grand Total: Rp " . number_format((float) $transaksi->grand_total, 0, ',', '.'));
        $this->line("Status     : {$transaksi->status}");
        $this->newLine();

        $items = $transaksi->items;
        if ($filter !== '') {
            $items = $items->filter(function ($item) use ($filter) {
                return stripos((string) $item->kode_barang, $filter) !== false
                    || stripos((string) $item->nama_barang, $filter) !== false;
            })->values();
        }

        if ($items->isEmpty()) {
            $this->warn('Tidak ada item yang cocok dengan filter.');
            return self::FAILURE;
        }

        $modalFaktur = 0.0;
        $omsetItems = 0.0;
        $issues = [];

        foreach ($items as $idx => $item) {
            $no = $idx + 1;
            $qty = (float) $item->qty;
            $hargaJual = (float) $item->harga;
            $totalJual = (float) $item->total;
            $omsetItems += $totalJual;

            $this->info("------------------------------------------------------------");
            $this->info("#{$no} {$item->kode_barang} | {$item->nama_barang}");
            $this->line(sprintf(
                "  Jual     : qty=%s %s | harga@=Rp %s | total=Rp %s",
                number_format($qty, 2, ',', '.'),
                $item->satuan ?? '-',
                number_format($hargaJual, 0, ',', '.'),
                number_format($totalJual, 0, ',', '.')
            ));
            if (!empty($item->satuan_besar)) {
                $this->line(sprintf(
                    "  Satuan besar nota: %s %s",
                    rtrim(rtrim(number_format((float) $item->qty_besar, 2, '.', ''), '0'), '.'),
                    $item->satuan_besar
                ));
            }

            $sumber = $item->transaksiItemSumber;
            if ($sumber->isEmpty()) {
                $this->error('  !! Tidak ada TransaksiItemSumber (FIFO allocation hilang) → modal dihitung 0 di laporan.');
                $issues[] = "{$item->kode_barang}: tidak ada FIFO sumber";
                $this->printRecentPurchases($item->kode_barang);
                $this->printOpenBatches($item->kode_barang);
                continue;
            }

            $modalItem = 0.0;
            $qtySumber = 0.0;
            $rows = [];

            foreach ($sumber as $s) {
                $batch = $s->stockBatch;
                $hargaBeli = (float) ($batch->harga_beli ?? $s->harga_modal ?? 0);
                $qtyAmbil = (float) $s->qty_diambil;
                $sub = $qtyAmbil * $hargaBeli;
                $modalItem += $sub;
                $qtySumber += $qtyAmbil;

                $pembelianNota = optional(optional($batch)->pembelianItem)->pembelian->nota
                    ?? optional($batch)->batch_number
                    ?? '-';
                $tipe = optional($batch)->tipe_batch ?: 'pembelian';
                $tglBatch = optional(optional($batch)->tanggal_masuk)->format('Y-m-d') ?? '-';
                $ket = optional($batch)->keterangan ?? '';

                $flag = '';
                if ($hargaJual > 0 && $hargaBeli > $hargaJual) {
                    $flag = ' << MODAL > HARGA JUAL';
                    $issues[] = sprintf(
                        '%s: batch #%s harga_beli Rp %s > harga jual Rp %s (qty %s)',
                        $item->kode_barang,
                        optional($batch)->id ?? '?',
                        number_format($hargaBeli, 0, ',', '.'),
                        number_format($hargaJual, 0, ',', '.'),
                        number_format($qtyAmbil, 2, ',', '.')
                    );
                }
                if ($tipe === 'adjustment' || stripos($ket, 'sync') !== false || stripos($ket, 'adjustment') !== false) {
                    $flag .= ' << BATCH ADJUSTMENT/SYNC (bukan dari nota beli)';
                    $issues[] = sprintf(
                        '%s: pakai batch adjustment/sync #%s harga Rp %s',
                        $item->kode_barang,
                        optional($batch)->id ?? '?',
                        number_format($hargaBeli, 0, ',', '.')
                    );
                }

                $rows[] = [
                    optional($batch)->id ?? '-',
                    $tglBatch,
                    $pembelianNota,
                    $tipe,
                    number_format($qtyAmbil, 2, ',', '.'),
                    'Rp ' . number_format($hargaBeli, 0, ',', '.'),
                    'Rp ' . number_format($sub, 0, ',', '.'),
                    trim($ket . $flag),
                ];
            }

            $this->table(
                ['Batch ID', 'Tgl Masuk', 'Nota/Batch', 'Tipe', 'Qty Diambil', 'Harga Beli', 'Sub Modal', 'Ket'],
                $rows
            );

            $avgModal = $qtySumber > 0 ? $modalItem / $qtySumber : 0;
            $this->line(sprintf(
                "  Modal item : Rp %s | qty sumber=%s | rata2 modal/unit=Rp %s",
                number_format($modalItem, 0, ',', '.'),
                number_format($qtySumber, 2, ',', '.'),
                number_format($avgModal, 0, ',', '.')
            ));

            if (abs($qtySumber - $qty) > 0.01) {
                $this->error(sprintf(
                    '  !! Qty sumber FIFO (%s) ≠ qty item jual (%s)',
                    number_format($qtySumber, 2, ',', '.'),
                    number_format($qty, 2, ',', '.')
                ));
                $issues[] = "{$item->kode_barang}: qty FIFO ≠ qty jual";
            }

            $expectedFromLastBeli = $this->lastPurchaseHarga($item->kode_barang);
            if ($expectedFromLastBeli !== null) {
                $expectedModal = $expectedFromLastBeli * $qty;
                $this->line(sprintf(
                    "  Bandingkan dg last beli: Rp %s/unit → modal kalau pakai last beli = Rp %s (selisih vs FIFO: Rp %s)",
                    number_format($expectedFromLastBeli, 0, ',', '.'),
                    number_format($expectedModal, 0, ',', '.'),
                    number_format($modalItem - $expectedModal, 0, ',', '.')
                ));
                if ($modalItem > $expectedModal * 1.05) {
                    $issues[] = sprintf(
                        '%s: modal FIFO Rp %s jauh di atas last beli Rp %s',
                        $item->kode_barang,
                        number_format($modalItem, 0, ',', '.'),
                        number_format($expectedModal, 0, ',', '.')
                    );
                }
            }

            $this->printRecentPurchases($item->kode_barang);
            $modalFaktur += $modalItem;
        }

        $omset = (float) $transaksi->grand_total;
        $laba = $omset - $modalFaktur;
        $margin = $omset > 0 ? ($laba / $omset) * 100 : 0;

        $this->newLine();
        $this->info('=== RINGKASAN (cara laporan laba per faktur) ===');
        $this->line('Omset (grand_total) : Rp ' . number_format($omset, 0, ',', '.'));
        $this->line('Sum total items     : Rp ' . number_format($omsetItems, 0, ',', '.'));
        $this->line('Modal FIFO          : Rp ' . number_format($modalFaktur, 0, ',', '.'));
        $this->line('Laba kotor          : Rp ' . number_format($laba, 0, ',', '.'));
        $this->line('Margin              : ' . number_format($margin, 2, ',', '.') . '%');
        $this->newLine();

        $this->comment('Rumus di LaporanController::labaPerFaktur:');
        $this->comment('  modal = SUM(transaksi_item_sumber.qty_diambil * stock_batches.harga_beli)');
        $this->comment('  Jadi yang menentukan modal = batch FIFO yang TERPAKAI saat jual,');
        $this->comment('  BUKAN harga beli terakhir di nota pembelian.');
        $this->newLine();

        if (empty($issues)) {
            $this->info('Tidak ada anomali mencolok terdeteksi otomatis.');
            $this->line('Kalau tetap minus: cek tabel batch di atas — kemungkinan ada batch lama dengan harga_beli lebih tinggi.');
        } else {
            $this->error('Temuan / kemungkinan penyebab:');
            foreach (array_unique($issues) as $i => $msg) {
                $this->line('  ' . ($i + 1) . '. ' . $msg);
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function lastPurchaseHarga(string $kodeBarang): ?float
    {
        $row = DB::table('pembelian_items as pi')
            ->join('pembelian as p', 'p.nota', '=', 'pi.nota')
            ->where('pi.kode_barang', $kodeBarang)
            ->where(function ($q) {
                $q->whereNull('p.status')->orWhere('p.status', '!=', 'canceled');
            })
            ->orderByDesc('p.tanggal')
            ->orderByDesc('p.created_at')
            ->orderByDesc('pi.id')
            ->select('pi.harga')
            ->first();

        return $row ? (float) $row->harga : null;
    }

    private function printRecentPurchases(string $kodeBarang): void
    {
        $rows = DB::table('pembelian_items as pi')
            ->join('pembelian as p', 'p.nota', '=', 'pi.nota')
            ->where('pi.kode_barang', $kodeBarang)
            ->where(function ($q) {
                $q->whereNull('p.status')->orWhere('p.status', '!=', 'canceled');
            })
            ->orderByDesc('p.tanggal')
            ->orderByDesc('pi.id')
            ->limit(5)
            ->get([
                'p.nota',
                'p.tanggal',
                'pi.qty',
                'pi.satuan',
                'pi.satuan_besar',
                'pi.qty_besar',
                'pi.harga',
                'p.status',
            ]);

        if ($rows->isEmpty()) {
            $this->line('  Pembelian terakhir: (tidak ada)');
            return;
        }

        $this->line('  Pembelian terakhir (max 5):');
        $this->table(
            ['Nota', 'Tanggal', 'Qty', 'Satuan', 'Qty Besar', 'Harga (base)', 'Status'],
            $rows->map(fn ($r) => [
                $r->nota,
                (string) $r->tanggal,
                number_format((float) $r->qty, 2, ',', '.'),
                $r->satuan . ($r->satuan_besar ? " / {$r->satuan_besar}" : ''),
                $r->qty_besar !== null ? number_format((float) $r->qty_besar, 2, ',', '.') : '-',
                'Rp ' . number_format((float) $r->harga, 0, ',', '.'),
                $r->status ?? 'ok',
            ])->all()
        );
    }

    private function printOpenBatches(string $kodeBarang): void
    {
        $kb = KodeBarang::where('kode_barang', $kodeBarang)->first();
        if (!$kb) {
            return;
        }

        $batches = StockBatch::where('kode_barang_id', $kb->id)
            ->where('qty_sisa', '>', 0)
            ->orderBy('tanggal_masuk')
            ->limit(10)
            ->get();

        if ($batches->isEmpty()) {
            $this->line('  Batch tersisa sekarang: (kosong)');
            return;
        }

        $this->line('  Batch tersisa sekarang (FIFO order, max 10):');
        $this->table(
            ['ID', 'Tgl', 'Batch#', 'Tipe', 'Qty Sisa', 'Harga Beli', 'Ket'],
            $batches->map(fn ($b) => [
                $b->id,
                optional($b->tanggal_masuk)->format('Y-m-d'),
                $b->batch_number,
                $b->tipe_batch ?: 'pembelian',
                number_format((float) $b->qty_sisa, 2, ',', '.'),
                'Rp ' . number_format((float) $b->harga_beli, 0, ',', '.'),
                $b->keterangan,
            ])->all()
        );
    }
}
