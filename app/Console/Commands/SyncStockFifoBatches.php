<?php

namespace App\Console\Commands;

use App\Models\KodeBarang;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Services\FifoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockFifoBatches extends Command
{
    protected $signature = 'stock:sync-fifo
                            {--kode= : Sync only this kode_barang}
                            {--name= : Sync barang whose name contains this string}
                            {--dry-run : Show gaps without writing}';

    protected $description = 'Create missing FIFO StockBatch rows so penjualan matches stocks.good_stock (fixes old stock adjustments).';

    public function handle(FifoService $fifoService): int
    {
        $query = KodeBarang::query()->orderBy('kode_barang');

        if ($kode = $this->option('kode')) {
            $query->where('kode_barang', $kode);
        }

        if ($name = $this->option('name')) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->warn('Tidak ada barang yang cocok.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $healed = 0;
        $alignedDown = 0;

        foreach ($items as $kodeBarang) {
            $stock = Stock::where('kode_barang', $kodeBarang->kode_barang)->first();
            if (!$stock) {
                continue;
            }

            $fifoQty = (float) $fifoService->getStokTersedia($kodeBarang->id);
            $goodStock = (float) $stock->good_stock;
            $gap = $goodStock - $fifoQty;

            if (abs($gap) < 0.0001) {
                continue;
            }

            $label = "{$kodeBarang->kode_barang} | {$kodeBarang->name}";

            if ($gap > 0) {
                $this->line("[+] {$label}: good_stock={$goodStock}, fifo={$fifoQty}, gap=+{$gap}");
                if (!$dryRun) {
                    DB::transaction(function () use ($kodeBarang, $gap) {
                        StockBatch::create([
                            'kode_barang_id' => $kodeBarang->id,
                            'qty_masuk' => $gap,
                            'qty_sisa' => $gap,
                            'harga_beli' => $kodeBarang->cost ?? 0,
                            'tanggal_masuk' => now(),
                            'keterangan' => 'Sync stock adjustment ke FIFO (artisan stock:sync-fifo)',
                            'tipe_batch' => 'adjustment',
                            'batch_number' => 'SYNC-ADJ-' . now()->format('YmdHis') . '-' . $kodeBarang->id,
                        ]);
                    });
                }
                $healed++;
            } else {
                // FIFO higher than good_stock — keep penjualan source of truth on FIFO,
                // lift good_stock so mutation/UI match.
                $this->line("[=] {$label}: good_stock={$goodStock}, fifo={$fifoQty} → set good_stock={$fifoQty}");
                if (!$dryRun) {
                    $stock->good_stock = $fifoQty;
                    $stock->save();
                }
                $alignedDown++;
            }
        }

        if ($dryRun) {
            $this->info("Dry-run: {$healed} perlu batch +, {$alignedDown} perlu align good_stock. Jalankan tanpa --dry-run untuk apply.");
        } else {
            $this->info("Selesai: {$healed} batch dibuat, {$alignedDown} good_stock di-align ke FIFO.");
        }

        return self::SUCCESS;
    }
}
