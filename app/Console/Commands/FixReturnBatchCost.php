<?php

namespace App\Console\Commands;

use App\Models\KodeBarang;
use App\Models\ReturPenjualan;
use App\Models\ReturnBarang;
use App\Models\StockBatch;
use App\Models\TransaksiItem;
use App\Services\FifoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixReturnBatchCost extends Command
{
    protected $signature = 'stock:fix-return-batch-cost
                            {--dry-run : Show what would change without writing}
                            {--batch= : Fix only this stock_batches.id}
                            {--retur= : Fix batches whose keterangan contains this no retur, e.g. RP-202609-0001}';

    protected $description = 'Fix return_penjualan StockBatch harga_beli that wrongly used harga jual; restores FIFO modal so laporan laba is correct';

    public function handle(FifoService $fifoService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchId = $this->option('batch');
        $returFilter = trim((string) $this->option('retur'));

        $query = StockBatch::query()
            ->where('tipe_batch', 'return_penjualan')
            ->orderBy('id');

        if ($batchId) {
            $query->where('id', $batchId);
        }

        if ($returFilter !== '') {
            $query->where('keterangan', 'like', '%' . $returFilter . '%');
        }

        $batches = $query->get();
        if ($batches->isEmpty()) {
            $this->warn('Tidak ada batch return_penjualan yang cocok.');
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . 'Memeriksa ' . $batches->count() . ' batch return_penjualan...');
        $this->newLine();

        $fixed = 0;
        $skipped = 0;
        $rows = [];

        foreach ($batches as $batch) {
            $resolved = $this->resolveCorrectModal($batch, $fifoService);
            $old = (float) $batch->harga_beli;
            $new = $resolved['harga'];
            $source = $resolved['source'];

            if ($new <= 0) {
                $skipped++;
                $rows[] = [
                    $batch->id,
                    optional($batch->tanggal_masuk)->format('Y-m-d'),
                    number_format($old, 0, ',', '.'),
                    '-',
                    'SKIP',
                    $source,
                ];
                continue;
            }

            // Ignore tiny float noise
            if (abs($old - $new) < 0.01) {
                $skipped++;
                $rows[] = [
                    $batch->id,
                    optional($batch->tanggal_masuk)->format('Y-m-d'),
                    number_format($old, 0, ',', '.'),
                    number_format($new, 0, ',', '.'),
                    'OK',
                    $source,
                ];
                continue;
            }

            $rows[] = [
                $batch->id,
                optional($batch->tanggal_masuk)->format('Y-m-d'),
                'Rp ' . number_format($old, 0, ',', '.'),
                'Rp ' . number_format($new, 0, ',', '.'),
                $dryRun ? 'WOULD FIX' : 'FIXED',
                $source,
            ];

            if (!$dryRun) {
                $batch->harga_beli = $new;
                $batch->keterangan = trim(($batch->keterangan ?? '') . ' [harga_beli fixed from jual→modal]');
                $batch->save();

                // Keep sumber.harga_modal in sync for rows that already consumed this batch
                DB::table('transaksi_item_sumber')
                    ->where('stock_batch_id', $batch->id)
                    ->update(['harga_modal' => $new]);
            }

            $fixed++;
        }

        $this->table(
            ['Batch', 'Tgl', 'Harga Lama', 'Harga Baru (modal)', 'Status', 'Sumber'],
            $rows
        );

        $this->newLine();
        $this->info("Selesai: {$fixed} perlu/ diperbaiki, {$skipped} skip/sudah benar.");
        if ($dryRun && $fixed > 0) {
            $this->comment('Jalankan tanpa --dry-run untuk apply. Laporan laba pakai stock_batches.harga_beli, jadi faktur lama (mis. KP/WS/0169) ikut benar setelah fix.');
        }
        if (!$dryRun && $fixed > 0) {
            $this->comment('Cek ulang: php artisan laporan:diagnose-modal "KP/WS/0169" --barang=THINWALL');
        }

        return self::SUCCESS;
    }

    private function resolveCorrectModal(StockBatch $batch, FifoService $fifoService): array
    {
        $keterangan = (string) ($batch->keterangan ?? '');
        $kodeBarang = KodeBarang::find($batch->kode_barang_id);

        // Retur Penjualan RP-...
        if (preg_match('/Retur Penjualan\s+(\S+)/i', $keterangan, $m)) {
            $noRetur = rtrim($m[1], '.');
            $retur = ReturPenjualan::with(['items.transaksiItem.transaksiItemSumber'])
                ->where('no_retur', $noRetur)
                ->first();

            if ($retur) {
                $items = $retur->items->filter(function ($item) use ($kodeBarang, $batch) {
                    if ($kodeBarang && $item->kode_barang === $kodeBarang->kode_barang) {
                        return true;
                    }
                    return abs((float) $item->qty_retur - (float) $batch->qty_masuk) < 0.01;
                });

                if ($items->isEmpty()) {
                    $items = $retur->items;
                }

                $qty = 0.0;
                $modal = 0.0;
                foreach ($items as $item) {
                    $harga = $fifoService->resolveHargaModalFromTransaksiItem($item->transaksiItem, $kodeBarang);
                    $q = (float) $item->qty_retur;
                    $qty += $q;
                    $modal += $q * $harga;
                }

                if ($qty > 0 && $modal > 0) {
                    return [
                        'harga' => $modal / $qty,
                        'source' => "retur {$noRetur} → FIFO sumber jual asli",
                    ];
                }

                return ['harga' => 0.0, 'source' => "retur {$noRetur} ketemu tapi modal sumber kosong"];
            }

            return ['harga' => 0.0, 'source' => "retur {$noRetur} tidak ditemukan"];
        }

        // Return dari RB-... / legacy return_barang
        if (preg_match('/Return dari\s+(\S+)/i', $keterangan, $m)) {
            $noReturn = rtrim($m[1], '.');
            $rb = ReturnBarang::where('no_return', $noReturn)->first();
            if ($rb && $kodeBarang) {
                $transaksiItem = TransaksiItem::with('transaksiItemSumber')
                    ->where('no_transaksi', $rb->no_transaksi_asal)
                    ->where('kode_barang', $kodeBarang->kode_barang)
                    ->first();

                $harga = $fifoService->resolveHargaModalFromTransaksiItem($transaksiItem, $kodeBarang);
                return [
                    'harga' => $harga,
                    'source' => $transaksiItem
                        ? "return {$noReturn} → FIFO sumber jual asli"
                        : "return {$noReturn} → fallback cost/avg batch",
                ];
            }

            return ['harga' => 0.0, 'source' => "return {$noReturn} tidak ditemukan"];
        }

        if ($kodeBarang) {
            $harga = $fifoService->resolveHargaModalFromTransaksiItem(null, $kodeBarang);
            return [
                'harga' => $harga,
                'source' => 'fallback cost/avg batch (keterangan tidak parse)',
            ];
        }

        return ['harga' => 0.0, 'source' => 'tidak bisa resolve'];
    }
}
