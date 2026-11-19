<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckDeletedFakturStock extends Command
{
    protected $signature = 'transaksi:check-deleted-stock
                            {notas* : No transaksi, e.g. KP/WS/0254 KP/WS/0255}';

    protected $description = 'Read-only: check if canceled/missing penjualan restored stock (safe before recreate)';

    public function handle(): int
    {
        $notas = $this->argument('notas');

        foreach ($notas as $nota) {
            $nota = trim($nota);
            $this->newLine();
            $this->info("========== {$nota} ==========");

            // Use query builder only (no Eloquent) — safer on prod with autoload quirks
            $t = DB::table('transaksi')->where('no_transaksi', $nota)->first();

            if (!$t) {
                $this->error('Transaksi: TIDAK ADA di DB (hilang / hard delete)');
                $items = collect();
            } else {
                $customerName = DB::table('customers')
                    ->where('kode_customer', $t->kode_customer)
                    ->value('nama');

                $this->line("Transaksi : ADA id={$t->id}");
                $this->line('Status    : ' . ($t->status ?? '-'));
                $this->line('Canceled  : ' . ($t->canceled_at ?? '-'));
                $this->line('Customer  : ' . $t->kode_customer . ' ' . ($customerName ?? ''));
                $this->line('Grand     : Rp ' . number_format((float) $t->grand_total, 0, ',', '.'));

                $items = DB::table('transaksi_items')
                    ->where('transaksi_id', $t->id)
                    ->orderBy('id')
                    ->get();

                $this->line('Items     : ' . $items->count());
                foreach ($items as $i) {
                    $this->line("  - {$i->kode_barang} | {$i->nama_barang} | qty={$i->qty} | @{$i->harga}");
                }
            }

            // Mutasi
            $this->newLine();
            $this->comment('--- Stock mutations ---');
            $rows = DB::table('stock_mutations')
                ->where(function ($q) use ($nota) {
                    $q->where('no_nota', $nota)
                        ->orWhere('no_transaksi', 'like', $nota . '%');
                })
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                $this->warn('Tidak ada mutasi untuk nota ini.');
            } else {
                $table = [];
                foreach ($rows as $r) {
                    $table[] = [
                        $r->id,
                        $r->kode_barang,
                        $r->minus,
                        $r->plus,
                        $r->no_transaksi,
                        Str::limit((string) ($r->keterangan ?? ''), 40),
                    ];
                }
                $this->table(['ID', 'Kode', 'Minus', 'Plus', 'No Transaksi', 'Ket'], $table);
            }

            $minus = (float) $rows->sum('minus');
            $plus = (float) $rows->sum('plus');
            if ($rows->isNotEmpty()) {
                $this->line("TOTAL minus(sale)={$minus} | plus(restore)={$plus} | net=" . ($plus - $minus));
            }

            // FIFO sumber
            if ($t) {
                $this->newLine();
                $this->comment('--- FIFO sumber (masih nempel = stok belum dikembalikan) ---');
                $anySumber = false;
                foreach ($items as $item) {
                    $sumber = DB::table('transaksi_item_sumber')
                        ->where('transaksi_item_id', $item->id)
                        ->get();
                    $qtySrc = (float) $sumber->sum('qty_diambil');
                    $this->line("{$item->kode_barang} qty_jual={$item->qty} sumber_rows={$sumber->count()} qty_diambil={$qtySrc}");
                    if ($sumber->count() > 0) {
                        $anySumber = true;
                    }
                }
                if ($anySumber) {
                    $this->error('Masih ada FIFO sumber → stok BELUM fully restored ke batch.');
                } else {
                    $this->info('Sumber FIFO kosong → biasanya sudah di-revert saat cancel.');
                }
            }

            // Verdict
            $this->newLine();
            $this->comment('--- VERDICT ---');
            $status = (string) ($t->status ?? '');

            if ($t && str_contains($status, 'canceled') && $plus >= $minus && $minus > 0) {
                $this->info('AMAN recreate: canceled + mutasi restore cukup.');
            } elseif ($t && !str_contains($status, 'canceled')) {
                $this->error('JANGAN recreate: faktur masih AKTIF di DB.');
            } elseif (!$t && $minus > 0 && $plus < $minus) {
                $this->error('JANGAN recreate dulu: faktur hilang tapi stok sale belum dikembalikan (minus tanpa plus cukup).');
            } elseif (!$t && $minus > 0 && $plus >= $minus) {
                $this->info('AMAN recreate: faktur hilang tapi mutasi restore sudah ada.');
            } elseif (!$t && $rows->isEmpty()) {
                $this->warn('UNCLEAR: faktur & mutasi tidak ketemu. Cek stok fisik / good_stock manual sebelum input ulang.');
            } else {
                $this->warn('UNCLEAR: cek output di atas manual.');
            }
        }

        $this->newLine();
        return self::SUCCESS;
    }
}
