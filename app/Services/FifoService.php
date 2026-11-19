<?php

namespace App\Services;

use App\Models\StockBatch;
use App\Models\TransaksiItemSumber;
use Illuminate\Support\Facades\DB;
use Exception;

class FifoService
{
    /**
     * Alokasi stok menggunakan metode FIFO untuk Transaksi
     * 
     * @param int $kodeBarangId
     * @param float $qtyDibutuhkan
     * @param int $transaksiItemId
     * @return array
     * @throws Exception
     */
    public function alokasiStok(int $kodeBarangId, float $qtyDibutuhkan, int $transaksiItemId): array
    {
        DB::beginTransaction();
        
        try {
            // Ambil batch yang tersisa, urutkan berdasarkan FIFO
            $batches = StockBatch::byKodeBarang($kodeBarangId)
                ->tersisa()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $qtyTersisa = $qtyDibutuhkan;
            $alokasi = [];
            $totalHargaModal = 0;

            foreach ($batches as $batch) {
                if ($qtyTersisa <= 0) break;

                $qtyTersedia = $batch->qty_sisa;
                $qtyAmbil = min($qtyTersisa, $qtyTersedia);

                // Buat record transaksi item sumber
                TransaksiItemSumber::create([
                    'transaksi_item_id' => $transaksiItemId,
                    'stock_batch_id' => $batch->id,
                    'qty_diambil' => $qtyAmbil,
                    'harga_modal' => $batch->harga_beli
                ]);

                // Update qty_sisa di batch
                $batch->qty_sisa -= $qtyAmbil;
                $batch->save();

                $alokasi[] = [
                    'batch_id' => $batch->id,
                    'qty_ambil' => $qtyAmbil,
                    'harga_modal' => $batch->harga_beli
                ];

                $totalHargaModal += ($qtyAmbil * $batch->harga_beli);
                $qtyTersisa -= $qtyAmbil;
            }

            if ($qtyTersisa > 0) {
                throw new Exception("Stok tidak mencukupi. Kekurangan: {$qtyTersisa}");
            }

            DB::commit();

            return [
                'success' => true,
                'alokasi' => $alokasi,
                'total_harga_modal' => $totalHargaModal,
                'rata_rata_harga_modal' => $qtyDibutuhkan > 0 ? $totalHargaModal / $qtyDibutuhkan : 0
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Alokasi stok menggunakan metode FIFO untuk Surat Jalan (tanpa membuat TransaksiItemSumber)
     * 
     * @param int $kodeBarangId
     * @param float $qtyDibutuhkan
     * @param int $suratJalanItemId
     * @return array
     * @throws Exception
     */
    public function alokasiStokUntukSuratJalan(int $kodeBarangId, float $qtyDibutuhkan, int $suratJalanItemId): array
    {
        DB::beginTransaction();
        
        try {
            // Ambil batch yang tersisa, urutkan berdasarkan FIFO
            $batches = StockBatch::byKodeBarang($kodeBarangId)
                ->tersisa()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $qtyTersisa = $qtyDibutuhkan;
            $alokasi = [];
            $totalHargaModal = 0;

            foreach ($batches as $batch) {
                if ($qtyTersisa <= 0) break;

                $qtyTersedia = $batch->qty_sisa;
                $qtyAmbil = min($qtyTersisa, $qtyTersedia);

                // Update qty_sisa di batch (tanpa membuat TransaksiItemSumber)
                $batch->qty_sisa -= $qtyAmbil;
                $batch->save();

                $alokasi[] = [
                    'batch_id' => $batch->id,
                    'qty_ambil' => $qtyAmbil,
                    'harga_modal' => $batch->harga_beli
                ];

                $totalHargaModal += ($qtyAmbil * $batch->harga_beli);
                $qtyTersisa -= $qtyAmbil;
            }

            if ($qtyTersisa > 0) {
                throw new Exception("Stok tidak mencukupi. Kekurangan: {$qtyTersisa}");
            }

            DB::commit();

            return [
                'success' => true,
                'alokasi' => $alokasi,
                'total_harga_modal' => $totalHargaModal,
                'rata_rata_harga_modal' => $qtyDibutuhkan > 0 ? $totalHargaModal / $qtyDibutuhkan : 0
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Hitung rata-rata harga modal untuk barang tertentu
     * 
     * @param int $kodeBarangId
     * @return float
     */
    public function hitungRataRataHargaModal(int $kodeBarangId): float
    {
        $batch = StockBatch::byKodeBarang($kodeBarangId)
            ->tersisa()
            ->selectRaw('SUM(qty_sisa * harga_beli) as total_value, SUM(qty_sisa) as total_qty')
            ->first();

        if (!$batch || $batch->total_qty <= 0) {
            return 0;
        }

        return $batch->total_value / $batch->total_qty;
    }

    /**
     * Dapatkan stok tersedia untuk barang tertentu
     * 
     * @param int $kodeBarangId
     * @return float
     */
    public function getStokTersedia(int $kodeBarangId): float
    {
        return StockBatch::byKodeBarang($kodeBarangId)
            ->tersisa()
            ->sum('qty_sisa');
    }

    /**
     * Dapatkan detail batch untuk barang tertentu
     * 
     * @param int $kodeBarangId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBatchDetail(int $kodeBarangId)
    {
        return StockBatch::byKodeBarang($kodeBarangId)
            ->with(['pembelianItem.pembelian.supplierRelation'])
            ->orderBy('tanggal_masuk', 'asc')
            ->get();
    }

    /**
     * Weighted-average FIFO cost from the original sale line.
     * Used when stock returns so the new batch keeps modal, not harga jual.
     */
    public function resolveHargaModalFromTransaksiItem(?\App\Models\TransaksiItem $transaksiItem, ?\App\Models\KodeBarang $kodeBarang = null): float
    {
        if ($transaksiItem) {
            $sumber = $transaksiItem->relationLoaded('transaksiItemSumber')
                ? $transaksiItem->transaksiItemSumber
                : $transaksiItem->transaksiItemSumber()->get();

            $qtyTotal = (float) $sumber->sum('qty_diambil');
            if ($qtyTotal > 0) {
                $modalTotal = (float) $sumber->sum(function ($s) {
                    return (float) $s->qty_diambil * (float) $s->harga_modal;
                });
                if ($modalTotal > 0) {
                    return $modalTotal / $qtyTotal;
                }
            }
        }

        if ($kodeBarang) {
            $avg = $this->hitungRataRataHargaModal($kodeBarang->id);
            if ($avg > 0) {
                return $avg;
            }
            return (float) ($kodeBarang->cost ?? 0);
        }

        return 0.0;
    }

    /**
     * Tambah stok untuk return barang dari penjualan
     * 
     * @param int $kodeBarangId
     * @param float $qty
     * @param float $harga  Must be FIFO modal / cost, NOT harga jual
     * @param string $keterangan
     * @param string $customerId
     * @return bool
     * @throws Exception
     */
    public function addStock(int $kodeBarangId, float $qty, float $harga, string $keterangan, string $customerId = null): bool
    {
        DB::beginTransaction();
        
        try {
            // Cari batch yang sudah ada dengan harga yang sama
            $existingBatch = StockBatch::byKodeBarang($kodeBarangId)
                ->where('harga_beli', $harga)
                ->where('qty_sisa', '>', 0)
                ->first();

            if ($existingBatch) {
                // Tambah ke batch yang sudah ada
                $existingBatch->qty_sisa += $qty;
                $existingBatch->save();
            } else {
                // Buat batch baru untuk return
                StockBatch::create([
                    'kode_barang_id' => $kodeBarangId,
                    'qty_masuk' => $qty,
                    'qty_sisa' => $qty,
                    'harga_beli' => $harga,
                    'tanggal_masuk' => now(),
                    'keterangan' => $keterangan,
                    'customer_id' => $customerId,
                    'tipe_batch' => 'return_penjualan'
                ]);
            }

            // Update tabel stocks untuk sinkronisasi dengan master barang
            $kodeBarang = \App\Models\KodeBarang::find($kodeBarangId);
            if ($kodeBarang) {
                $stock = \App\Models\Stock::where('kode_barang', $kodeBarang->kode_barang)->first();
                if ($stock) {
                    $stock->good_stock += $qty;
                    $stock->save();
                }
            }

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Kurangi stok untuk return barang ke supplier
     * 
     * @param int $kodeBarangId
     * @param float $qty
     * @param string $keterangan
     * @param string $customerId
     * @return bool
     * @throws Exception
     */
    public function reduceStock(int $kodeBarangId, float $qty, string $keterangan, string $customerId = null): bool
    {
        DB::beginTransaction();
        
        try {
            // Ambil batch yang tersisa, urutkan berdasarkan FIFO
            $batches = StockBatch::byKodeBarang($kodeBarangId)
                ->tersisa()
                ->fifo()
                ->lockForUpdate()
                ->get();

            $qtyTersisa = $qty;

            foreach ($batches as $batch) {
                if ($qtyTersisa <= 0) break;

                $qtyTersedia = $batch->qty_sisa;
                $qtyAmbil = min($qtyTersisa, $qtyTersedia);

                // Update qty_sisa di batch
                $batch->qty_sisa -= $qtyAmbil;
                $batch->save();

                $qtyTersisa -= $qtyAmbil;
            }

            if ($qtyTersisa > 0) {
                throw new Exception("Stok tidak mencukupi untuk return. Kekurangan: {$qtyTersisa}");
            }

            // Update tabel stocks untuk sinkronisasi dengan master barang
            $kodeBarang = \App\Models\KodeBarang::find($kodeBarangId);
            if ($kodeBarang) {
                $stock = \App\Models\Stock::where('kode_barang', $kodeBarang->kode_barang)->first();
                if ($stock) {
                    $stock->good_stock -= $qty;
                    $stock->save();
                }
            }

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
