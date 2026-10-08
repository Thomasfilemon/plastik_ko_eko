<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stock;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class StockController extends Controller
{
    /**
     * Display the stock mutation report
     */
    public function mutasiStock(Request $request)
    {
        // Get filter parameters
        $kolom = $request->input('kolom', 'kode_barang');
        $value = $request->input('value');
        $tanggal_awal = $request->input('tanggal_awal');
        $tanggal_akhir = $request->input('tanggal_akhir');
        $grupId = $request->input('grup_barang');
        $selectedKodeBarang = $request->input('selected_kode_barang');

        // Prefer current master barang name so renames show immediately in Mutasi Stok
        $stocksQuery = Stock::query()
            ->leftJoin('kode_barangs', 'stocks.kode_barang', '=', 'kode_barangs.kode_barang')
            ->select(
                'stocks.kode_barang',
                DB::raw('COALESCE(kode_barangs.name, stocks.nama_barang) as nama_barang'),
                DB::raw('SUM(stocks.good_stock) as good_stock'),
                DB::raw('SUM(stocks.bad_stock) as bad_stock'),
                'stocks.satuan'
            )
            ->groupBy('stocks.kode_barang', 'kode_barangs.name', 'stocks.nama_barang', 'stocks.satuan');

        // Apply filters using when() for dynamic query building
        $stocksQuery->when($value, function($query, $value) use ($kolom) {
            if ($kolom === 'kode_barang') {
                return $query->where('stocks.kode_barang', 'like', "%{$value}%");
            } elseif ($kolom === 'nama') {
                return $query->where(function ($q) use ($value) {
                    $q->where('stocks.nama_barang', 'like', "%{$value}%")
                        ->orWhere('kode_barangs.name', 'like', "%{$value}%");
                });
            }
        });

        // Filter by grup barang if provided
        $stocksQuery->when($grupId, function($query, $grupId) {
            return $query->whereIn('stocks.kode_barang', function($q) use ($grupId) {
                $q->select('kode_barang')
                  ->from('kode_barangs')
                  ->where('grup_barang_id', $grupId);
            });
        });

        $stocks = $stocksQuery->get();

        // Get mutations for the filtered products
        $mutations = collect([]);
        $openingBalance = 0;
        $selectedStock = null;

        // Check if we should show mutations (either a single result or user clicked on an item)
        $selectedKodeBarang = $request->input('selected_kode_barang');

        if ($selectedKodeBarang) {
            // User specifically selected an item to view mutations for
            $selectedStock = $stocks->where('kode_barang', $selectedKodeBarang)->first();
        } elseif ($stocks->count() == 1) {
            // Only one stock item found in search, automatically show its mutations
            $selectedStock = $stocks->first();
        }

        if ($selectedStock) {
            $ledger = $this->buildMutationLedger(
                $selectedStock->kode_barang,
                (float) $selectedStock->good_stock,
                $tanggal_awal,
                $tanggal_akhir
            );
            $mutations = $ledger['mutations'];
            $openingBalance = $ledger['openingBalance'];
        }

        return view('stock.mutasi_stock', compact(
            'stocks',
            'mutations',
            'openingBalance',
            'kolom',
            'value',
            'tanggal_awal',
            'tanggal_akhir',
            'selectedStock',
            'grupId'
        ));
    }

    /**
     * Build mutasi ledger the same way for Mutasi Stok & Laporan Stok.
     * Ending balance always matches master good_stock when viewing through "now".
     * Canceled penjualan/pembelian movements are omitted so batal does not shift stock.
     */
    public function buildMutationLedger(
        string $kodeBarang,
        float $goodStock,
        ?string $tanggalAwal = null,
        ?string $tanggalAkhir = null
    ): array {
        $mutationsQuery = StockMutation::query()
            ->excludeCanceled()
            ->where('kode_barang', $kodeBarang)
            ->when($tanggalAwal && $tanggalAkhir, function ($q) use ($tanggalAwal, $tanggalAkhir) {
                $q->whereBetween(DB::raw('DATE(tanggal)'), [$tanggalAwal, $tanggalAkhir]);
            }, function ($q) use ($tanggalAwal, $tanggalAkhir) {
                if ($tanggalAwal) {
                    $q->whereDate('tanggal', '>=', $tanggalAwal);
                }
                if ($tanggalAkhir) {
                    $q->whereDate('tanggal', '<=', $tanggalAkhir);
                }
            })
            ->orderBy('tanggal')
            ->orderBy('id');

        $mutations = $mutationsQuery->get();
        $net = (float) $mutations->sum(function ($m) {
            return (float) $m->plus - (float) $m->minus;
        });

        $viewsThroughNow = !$tanggalAkhir || $tanggalAkhir >= now()->toDateString();

        if ($tanggalAwal && !$viewsThroughNow) {
            // Historical closed range: saldo awal from prior active movements
            $openingBalance = $this->getOpeningBalance($kodeBarang, $tanggalAwal);
        } else {
            // Align with master / mutasi good stock so Total akhir = Good Stock
            $openingBalance = $goodStock - $net;
        }

        $running = $openingBalance;
        $mutations = $mutations->map(function ($mutation) use (&$running) {
            $running += (float) $mutation->plus - (float) $mutation->minus;
            $mutation->total = $running;
            return $mutation;
        });

        return [
            'mutations' => $mutations,
            'openingBalance' => $openingBalance,
        ];
    }

    /**
     * Print good stock report
     */
    public function printGoodStock(Request $request)
    {
        $kolom = $request->input('kolom', 'kode_barang');
        $value = $request->input('value');

        // Group stocks by kode_barang
        $query = Stock::select(
            'stocks.kode_barang',
            'stocks.nama_barang',
            DB::raw('SUM(stocks.good_stock) as good_stock'),
            'stocks.satuan'
        )
        ->groupBy('stocks.kode_barang', 'stocks.nama_barang', 'stocks.satuan');

        if ($value) {
            if ($kolom === 'kode_barang') {
                $query->where('stocks.kode_barang', 'like', "%{$value}%");
            } elseif ($kolom === 'nama') {
                $query->where('stocks.nama_barang', 'like', "%{$value}%");
            }
        }

        $stocks = $query->get();

        return view('stock.print_good_stock', compact('stocks'));
    }

    /**
     * Record a purchase for stock mutation report only (no panel inventory update)
     */
    public function recordPurchase(
        string $kode_barang,
        string $nama_barang,
        string $no_transaksi,
        string $tanggal,
        string $no_nota,
        string $supplier_customer,
        float $quantity,
        string $satuan = 'LBR',
        string $keterangan = 'Purchase transaction',
        ?string $created_by = null,
        string $so = 'default' // Made SO optional with default value
    ): bool {
        try {
            DB::beginTransaction();

            // Get current user if not provided
            if ($created_by === null) {
                $created_by = Auth::check() ? Auth::user()->name : 'SYSTEM';
            }

            // Get all stock records for this product (regardless of SO)
            $existingStock = Stock::where('kode_barang', $kode_barang)->first();
            
            if ($existingStock) {
                // Update the stock record
                $newTotal = $existingStock->good_stock + $quantity;
                $existingStock->good_stock = $newTotal;
                $existingStock->save();
                
                // Use this stock's attributes
                $nama_barang = $existingStock->nama_barang;
                $satuan = $existingStock->satuan;
            } else {
                // Create a new stock record
                $existingStock = Stock::create([
                    'kode_barang' => $kode_barang,
                    'nama_barang' => $nama_barang,
                    'good_stock' => $quantity,
                    'bad_stock' => 0,
                    'satuan' => $satuan,
                    'so' => $so  // Keep SO for backward compatibility
                ]);
                
                $newTotal = $quantity;
            }

            // Record the stock movement
            StockMutation::create([
                'kode_barang' => $kode_barang,
                'nama_barang' => $nama_barang,
                'no_transaksi' => $no_transaksi,
                'tanggal' => $tanggal,
                'no_nota' => $no_nota,
                'supplier_customer' => $supplier_customer,
                'plus' => $quantity,
                'minus' => 0,
                'total' => $newTotal,
                'so' => $so, // Keep for historical records
                'satuan' => $satuan,
                'keterangan' => $keterangan,
                'created_by' => $created_by
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recording purchase:', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Record a sale for stock mutation report only (no panel inventory update)
     */
    public function recordSale(
        string $kode_barang,
        string $nama_barang,
        string $no_transaksi,
        string $tanggal,
        string $no_nota,
        string $customer,
        float $quantity,
        string $satuan = 'LBR',
        string $keterangan = 'Sale transaction',
        ?string $created_by = null,
        string $so = 'default' // Made SO optional with default value
    ): bool {
        try {
            DB::beginTransaction();

            // Get current user if not provided
            if ($created_by === null) {
                $created_by = Auth::check() ? Auth::user()->name : 'SYSTEM';
            }

            // Get all stock records for this product (regardless of SO)
            $existingStock = Stock::where('kode_barang', $kode_barang)->first();
            
            if (!$existingStock) {
                // Create a new stock record with negative quantity if stock doesn't exist
                $existingStock = Stock::create([
                    'kode_barang' => $kode_barang,
                    'nama_barang' => $nama_barang,
                    'good_stock' => -$quantity, // Negative because it's a sale
                    'bad_stock' => 0,
                    'satuan' => $satuan,
                    'so' => $so  // Keep SO for backward compatibility
                ]);
                
                $newTotal = -$quantity;
            } else {
                // Update the stock record
                $newTotal = $existingStock->good_stock - $quantity;
                $existingStock->good_stock = $newTotal;
                $existingStock->save();
                
                // Use this stock's attributes
                $nama_barang = $existingStock->nama_barang;
                $satuan = $existingStock->satuan;
            }

            // Record the stock movement
            StockMutation::create([
                'kode_barang' => $kode_barang,
                'nama_barang' => $nama_barang,
                'no_transaksi' => $no_transaksi,
                'tanggal' => $tanggal,
                'no_nota' => $no_nota,
                'supplier_customer' => $customer,
                'plus' => 0,
                'minus' => $quantity,
                'total' => $newTotal,
                'so' => $so, // Keep for historical records
                'satuan' => $satuan,
                'keterangan' => $keterangan,
                'created_by' => $created_by
            ]);
            

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error recording sale:', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get the opening balance for a product on a specific date
     * Modified to not require SO parameter
     */
    public function getOpeningBalance(string $kode_barang, string $date): float
    {
        // Sum active (non-canceled) movements before the date so batal does not shift saldo
        $mutations = StockMutation::where('kode_barang', $kode_barang)
            ->excludeCanceled()
            ->whereDate('tanggal', '<', $date)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['plus', 'minus']);

        $balance = 0.0;
        foreach ($mutations as $mutation) {
            $balance += (float) $mutation->plus - (float) $mutation->minus;
        }

        return $balance;
    }

    /**
     * API endpoint to get stock data for a specific product
     * Modified to work without SO parameter
     */
    public function getStock(Request $request)
    {
        $kode_barang = $request->input('kode_barang');
        $so = $request->input('so');

        $query = Stock::where('kode_barang', $kode_barang);
        
        // Only filter by SO if provided
        if (!empty($so)) {
            $query->where('so', $so);
        } else {
            // If SO not provided, just get the first record or all records summed
            $query = Stock::select(
                'kode_barang',
                'nama_barang',
                DB::raw('SUM(good_stock) as good_stock'),
                DB::raw('SUM(bad_stock) as bad_stock'),
                'satuan'
            )
            ->where('kode_barang', $kode_barang)
            ->groupBy('kode_barang', 'nama_barang', 'satuan');
        }

        $stock = $query->first();

        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'Stock not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $stock
        ]);
    }

    /**
     * API endpoint to get mutations for a specific product
     * Modified to work without SO parameter
     */
    public function getStockMutations(Request $request)
    {
        $kode_barang = $request->input('kode_barang');
        $so = $request->input('so');
        $tanggal_awal = $request->input('tanggal_awal');
        $tanggal_akhir = $request->input('tanggal_akhir');

        $query = StockMutation::where('kode_barang', $kode_barang)
            ->orderBy('tanggal')
            ->orderBy('id');
            
        // Only filter by SO if provided
        if (!empty($so)) {
            $query->where('so', $so);
        }

        if ($tanggal_awal) {
            $query->whereDate('tanggal', '>=', $tanggal_awal);
        }

        if ($tanggal_akhir) {
            $query->whereDate('tanggal', '<=', $tanggal_akhir);
        }

        $mutations = $query->get();
        $openingBalance = 0;

        if ($tanggal_awal) {
            $openingBalance = $this->getOpeningBalance($kode_barang, $tanggal_awal);
        }

        return response()->json([
            'success' => true,
            'opening_balance' => $openingBalance,
            'data' => $mutations
        ]);
    }
    
    /**
     * Consolidate stock data - helpful for one-time cleanup
     * Run this once to merge duplicate stock records by kode_barang
     */
    public function consolidateStocks()
    {
        try {
            DB::beginTransaction();
            
            // Get list of all unique kode_barang values
            $kodeBarangList = Stock::select('kode_barang')
                ->distinct()
                ->get()
                ->pluck('kode_barang');
                
            $consolidated = 0;
            
            foreach ($kodeBarangList as $kodeBarang) {
                $stocks = Stock::where('kode_barang', $kodeBarang)->get();
                
                // Skip if there's only one record
                if ($stocks->count() <= 1) {
                    continue;
                }
                
                // Sum up quantities
                $totalGoodStock = $stocks->sum('good_stock');
                $totalBadStock = $stocks->sum('bad_stock');
                
                // Keep the first record, update its quantities
                $primaryStock = $stocks->first();
                $primaryStock->good_stock = $totalGoodStock;
                $primaryStock->bad_stock = $totalBadStock;
                $primaryStock->save();
                
                // Delete the other records
                Stock::where('kode_barang', $kodeBarang)
                    ->where('id', '!=', $primaryStock->id)
                    ->delete();
                    
                $consolidated++;
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Consolidated $consolidated products"
            ]);
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error consolidating stocks:', ['message' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}