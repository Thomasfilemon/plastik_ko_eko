<?php

namespace App\Http\Controllers;

use App\Models\KodeBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pembelian;
use App\Models\PembelianItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use App\Models\Panel;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\StockBatch;
use App\Services\UnitConversionService;


class PembelianController extends Controller
{
    protected $stockController;
    protected $panelController;

    public function __construct(StockController $stockController, PanelController $panelController)
    {
        $this->stockController = $stockController;
        $this->panelController = $panelController;
    }

    /**
     * Resolve satuan besar / satuan kecil into base-unit quantities.
     *
     * Returns the quantity and price expressed in the item's base unit (unit_dasar)
     * so all downstream stock, FIFO and panel logic keeps working in base units,
     * while also returning the big-unit info for display on the nota.
     */
    private function resolveUnitConversion($kodeBarangModel, array $item): array
    {
        $unitDasar = $kodeBarangModel->unit_dasar ?? 'LBR';
        $qtyInput = (float) ($item['qty'] ?? 0);
        $hargaInput = (float) ($item['harga'] ?? 0);
        $satuanInput = $item['satuan'] ?? $unitDasar;
        if ($satuanInput === '' || $satuanInput === null) {
            $satuanInput = $unitDasar;
        }

        // Number of base units contained in 1 of the chosen unit
        $factor = 1.0;
        if ($satuanInput !== $unitDasar) {
            $service = new UnitConversionService();
            $factor = $service->convertToBaseUnit($kodeBarangModel->id, 1, $satuanInput);
            if ($factor <= 0) {
                $factor = 1.0;
            }
        }

        $isBesar = ($satuanInput !== $unitDasar) && $factor > 1;

        return [
            'qty_base' => $qtyInput * $factor,
            // Harga selalu per satuan kecil (unit dasar)
            'harga_base' => $hargaInput,
            'satuan' => $unitDasar,
            'satuan_besar' => $isBesar ? $satuanInput : null,
            'qty_besar' => $isBesar ? $qtyInput : null,
        ];
    }

    /**
     * Zero FIFO StockBatch rows for pembelian items that have not been sold yet.
     * Throws if any batch qty was already consumed by penjualan.
     */
    private function reverseUnusedStockBatches($items, string $nota, string $actionLabel = 'dibatalkan'): void
    {
        foreach ($items as $item) {
            $batches = StockBatch::where('pembelian_item_id', $item->id)->get();

            // Fallback for orphaned batches (pembelian_item_id set null after prior buggy deletes)
            if ($batches->isEmpty()) {
                $kodeBarang = KodeBarang::where('kode_barang', $item->kode_barang)->first();
                if ($kodeBarang) {
                    $batches = StockBatch::where('kode_barang_id', $kodeBarang->id)
                        ->where('batch_number', $nota . '-' . $item->kode_barang)
                        ->where('qty_sisa', '>', 0)
                        ->get();
                }
            }

            foreach ($batches as $batch) {
                $usedQty = (float) $batch->qty_masuk - (float) $batch->qty_sisa;
                if ($usedQty > 0.0001) {
                    throw new \Exception(
                        "Nota tidak dapat {$actionLabel}: stok {$item->nama_barang} sudah terpakai sebanyak {$usedQty} "
                        . "(sudah terjual/dipakai di penjualan). Batalkan atau edit faktur penjualan yang memakai stok ini terlebih dahulu, "
                        . "baru nota pembelian ini bisa {$actionLabel}."
                    );
                }

                $batch->qty_sisa = 0;
                $batch->keterangan = trim(($batch->keterangan ?? '') . " [{$actionLabel}]");
                $batch->save();
            }
        }
    }

    /**
     * Create a FIFO StockBatch for a pembelian item (base-unit qty).
     */
    private function createStockBatchForItem(
        PembelianItem $pembelianItem,
        KodeBarang $kodeBarang,
        float $qtyBase,
        float $hargaBase,
        $tanggal,
        string $nota,
        string $supplierName
    ): void {
        StockBatch::create([
            'kode_barang_id' => $kodeBarang->id,
            'pembelian_item_id' => $pembelianItem->id,
            'qty_masuk' => $qtyBase,
            'qty_sisa' => $qtyBase,
            'harga_beli' => $hargaBase,
            'tanggal_masuk' => $tanggal,
            'batch_number' => $nota . '-' . $pembelianItem->kode_barang,
            'keterangan' => 'Pembelian dari ' . $supplierName,
        ]);
    }

    /**
     * Display the purchase transaction form.
     */
    public function index()
    {
        // Ambil nomor nota terakhir
        $lastPurchase = Pembelian::orderBy('created_at', 'desc')->first();

        // Generate nomor nota baru
        if ($lastPurchase) {
            // Ambil angka terakhir dari nota
            $lastNumber = (int) substr($lastPurchase->nota, strrpos($lastPurchase->nota, '-') + 1);
            $newNumber = $lastNumber + 1;
        } else {
            // Jika belum ada pembelian, mulai dari 1
            $newNumber = 1;
        }

        // Format nomor nota baru
        $currentMonth = date('m');
        $currentYear = date('y');
        $nota = "BL/{$currentMonth}/{$currentYear}-" . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

        // Get KodeBarang data for dropdown
        $kodeBarangs = KodeBarang::orderBy('name')->get();

        return view('pembelian.addpembelian', compact('nota', 'kodeBarangs'));
    }

    /**
     * Store a purchase transaction.
     */
    public function store(Request $request)
    {
        // In the store method, update the validation
        $request->validate([
            'nota' => 'required|string|unique:pembelian,nota',
            'no_surat_jalan' => 'nullable|string',
            'tanggal' => 'required|date',
            'kode_supplier' => 'required|exists:suppliers,kode_supplier',
            'subtotal' => 'required|numeric',
            'grand_total' => 'required|numeric',
            'items' => 'required|array',
            'items.*.kodeBarang' => 'required|string',
            'items.*.harga' => 'required|numeric',
            'items.*.qty' => 'required|numeric',
            'hari_tempo' => 'nullable|integer|min:0',
            'tanggal_jatuh_tempo' => 'nullable|date|after_or_equal:tanggal',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Use current time for the transaction
            $currentDateTime = now();
            $tanggalWithTime = $currentDateTime->format('Y-m-d H:i:s');
            
            // Get supplier name for stock mutation record
            $supplier = Supplier::where('kode_supplier', $request->kode_supplier)->first();
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';
            
            // Create purchase
            $pembelian = Pembelian::create([
                'nota' => $request->nota,
                'no_surat_jalan' => $request->no_surat_jalan,
                'tanggal' => $tanggalWithTime, // Store with time
                'kode_supplier' => $request->kode_supplier,
                'pembayaran' => $request->pembayaran ?? 'Tunai',
                'cara_bayar' => $request->cara_bayar,
                'hari_tempo' => $request->hari_tempo ?? 0,
                'tanggal_jatuh_tempo' => $request->tanggal_jatuh_tempo ?? null,
                'subtotal' => $request->subtotal,
                'diskon' => $request->diskon ?? 0,
                'ppn' => $request->ppn ?? 0,
                'grand_total' => $request->grand_total,
                'created_at' => $currentDateTime,
            ]);
            
            // Get creator name from request or default to 'ADMIN'
            $creator = Auth::check() ? Auth::user()->name : 'ADMIN';
            
            // Format transaction number for mutation record - cleaner without time
            $noTransaksi = "BL-" . date('m/y', strtotime($request->tanggal)) . "-" . 
                            substr($request->nota, strrpos($request->nota, '-') + 1) . 
                            " ({$creator})";
            
            // Create purchase items, update stock mutation, and add inventory
            foreach ($request->items as $item) {
                // Resolve satuan besar/kecil into base-unit quantity & price
                $kodeBarang = KodeBarang::where('kode_barang', $item['kodeBarang'])->first();
                $conv = $kodeBarang
                    ? $this->resolveUnitConversion($kodeBarang, $item)
                    : ['qty_base' => (float) $item['qty'], 'harga_base' => (float) $item['harga'], 'satuan' => 'LBR', 'satuan_besar' => null, 'qty_besar' => null];

                $qtyBase = $conv['qty_base'];
                $hargaBase = $conv['harga_base'];

                // Create purchase item (stored in base units, with big-unit info for the nota)
                $pembelianItem = PembelianItem::create([
                    'nota' => $request->nota,
                    'kode_barang' => $item['kodeBarang'],
                    'nama_barang' => $item['namaBarang'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'harga' => $hargaBase,
                    'qty' => $qtyBase,
                    'satuan' => $conv['satuan'],
                    'satuan_besar' => $conv['satuan_besar'],
                    'qty_besar' => $conv['qty_besar'],
                    'diskon' => $item['diskon'] ?? 0,
                    'total' => $item['total'],
                    'created_at' => $currentDateTime,
                ]);

                // Create StockBatch untuk sistem FIFO
                if ($kodeBarang) {
                    $this->createStockBatchForItem(
                        $pembelianItem,
                        $kodeBarang,
                        $qtyBase,
                        $hargaBase,
                        $request->tanggal,
                        $request->nota,
                        $supplierName
                    );
                }
                
                // Record purchase in stock mutation (just for reporting)
                $this->stockController->recordPurchase(
                    $item['kodeBarang'],
                    $item['namaBarang'],
                    $noTransaksi,
                    $tanggalWithTime, // Use date with time
                    $request->nota,
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $qtyBase,
                    $conv['satuan'], // Unit of measure (base unit)
                    'Purchase transaction', // Keterangan
                    $creator, // Created by
                    'default' // Stock owner
                );
                
                if ($kodeBarang) {
                    // Get a panel instance with this kode_barang to use as a template
                    $templatePanel = Panel::where('group_id', $item['kodeBarang'])->first();
                    
                    // Default values if no template exists
                    $panelName = $item['namaBarang'];
                    $cost = $hargaBase; // Use purchase price (per base unit) as cost
                    
                    // If template exists, use its values
                    if ($templatePanel) {
                        $panelName = $templatePanel->name;
                    }
                    
                    // Use the PanelController to add panels to inventory
                    $panelController = app()->make(PanelController::class);
                    
                    $result = $panelController->addPanelsToInventory(
                        $panelName, 
                        $cost, 
                        $item['kodeBarang'], 
                        (int) $qtyBase
                    );
                    
                    // Log the result
                    Log::info('Added panels to inventory:', ['result' => $result]);
                    
                    // Update cost di master barang dengan harga pembelian terbaru (per base unit)
                    $kodeBarang->cost = $hargaBase;
                    $kodeBarang->save();
                    Log::info('Updated master barang cost:', [
                        'kode_barang' => $item['kodeBarang'],
                        'new_cost' => $hargaBase
                    ]);
                } else {
                    Log::warning('KodeBarang not found for purchase item:', ['kode_barang' => $item['kodeBarang']]);
                }
            }
            
            DB::commit();

            return response()->json([
                'id' => $pembelian->id,
                'nota' => $pembelian->nota,
                'tanggal' => $pembelian->tanggal,
                'supplier' => $pembelian->supplierRelation->nama ?? 'N/A',
                'grand_total' => $pembelian->grand_total,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian store:', ['exception' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Search for suppliers
     */
    public function searchSuppliers(Request $request)
    {
        $keyword = $request->keyword;

        $suppliers = Supplier::where('kode_supplier', 'like', "%{$keyword}%")
            ->orWhere('nama', 'like', "%{$keyword}%")
            ->limit(10)
            ->get();

        return response()->json($suppliers);
    }

    /**
     * Get purchase data
     */
    public function getPurchase($id)
    {
        $purchase = Pembelian::with('items')->findOrFail($id);

        return response()->json($purchase);
    }

    /**
     * Show the invoice (nota) for a purchase
     */
    public function showNota($id)
    {
        $purchase = Pembelian::with('items', 'supplierRelation')->findOrFail($id);

        return view('pembelian.nota_pembelian', compact('purchase'));
    }

    public function nota($nota)
    {
        $purchase = Pembelian::with('items', 'supplierRelation')->where('nota', $nota)->firstOrFail();
        return view('pembelian.nota_pembelian', compact('purchase'));
    }

    public function listNota(Request $request)
    {
        // Get search parameters
        $searchBy = $request->input('search_by', '');
        $search = $request->input('search', '');
        $startDate = $request->input('startDate', '');
        $endDate = $request->input('endDate', '');
        
        // Build the query for Pembelian
        $query = Pembelian::with('items', 'supplierRelation');
        
        // Apply search filter if search term exists and search_by is specified
        if (!empty($search) && !empty($searchBy)) {
            if ($searchBy === 'nota') {
                $query->where('nota', 'like', "%{$search}%");
            } else if ($searchBy === 'kode_supplier') {
                $query->where('kode_supplier', 'like', "%{$search}%");
            } else if ($searchBy === 'nama_supplier') {
                $query->whereHas('supplierRelation', function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%");
                });
            } else if ($searchBy === 'cara_bayar') {
                $query->where('cara_bayar', 'like', "%{$search}%");
            }
        } else if (!empty($search)) {
            // If search_by is not specified but search term exists, search in all relevant fields
            $query->where(function($q) use ($search) {
                $q->where('nota', 'like', "%{$search}%")
                ->orWhere('kode_supplier', 'like', "%{$search}%")
                ->orWhere('cara_bayar', 'like', "%{$search}%")
                ->orWhereHas('supplierRelation', function($sq) use ($search) {
                    $sq->where('nama', 'like', "%{$search}%");
                });
            });
        }
        
        // Apply date filter if provided
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereDate('tanggal', '>=', $startDate)
                ->whereDate('tanggal', '<=', $endDate);
        } else if (!empty($startDate)) {
            $query->whereDate('tanggal', '>=', $startDate);
        } else if (!empty($endDate)) {
            $query->whereDate('tanggal', '<=', $endDate);
        }
        
        // Order by created_at descending
        $query->orderBy('created_at', 'desc');
        
        // Paginate the results and append query params to the pagination links
        $purchases = $query->paginate(10);
        $purchases->appends([
            'search_by' => $searchBy,
            'search' => $search,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
        
        return view('pembelian.lihat_nota_pembelian', compact(
            'purchases', 
            'searchBy',
            'search', 
            'startDate', 
            'endDate'
        ));
    }

    /**
     * Show the form for editing the specified purchase.
     */
    public function edit($id)
    {
        $purchase = Pembelian::with(['items.kodeBarang', 'supplierRelation'])->findOrFail($id);

        if ($purchase->status === 'canceled') {
            return redirect()->route('pembelian.nota.list')
                ->with('error', 'Nota pembelian yang sudah dibatalkan tidak dapat diedit.');
        }
        
        // Get the supplier info
        $supplier = null;
        if ($purchase->supplierRelation) {
            $supplier = $purchase->kode_supplier . ' - ' . $purchase->supplierRelation->nama;
        }
        
        return view('pembelian.editpembelian', compact('purchase', 'supplier'));
    }
    
    /**
     * Update the specified purchase in storage.
     */
    public function update(Request $request, $id)
    {
        // In the store method, update the validation
        $request->validate([
            'nota' => 'required|string|unique:pembelian,nota,'.$id,
            'no_surat_jalan' => 'nullable|string',
            'tanggal' => 'required|date',
            'kode_supplier' => 'required|exists:suppliers,kode_supplier',
            'subtotal' => 'required|numeric',
            'grand_total' => 'required|numeric',
            'items' => 'required|array',
            'items.*.kodeBarang' => 'required|string',
            'items.*.harga' => 'required|numeric',
            'items.*.qty' => 'required|numeric',
            'edit_reason' => 'required|string|max:255',
        ]);
        
        try {
            DB::beginTransaction();
            
            // Find purchase
            $pembelian = Pembelian::findOrFail($id);

            if ($pembelian->status === 'canceled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota pembelian yang sudah dibatalkan tidak dapat diedit.'
                ], 400);
            }

            $nota = $pembelian->nota; // Keep the original nota
            
            // Use current time for the transaction
            $currentDateTime = now();
            $tanggalWithTime = $currentDateTime->format('Y-m-d H:i:s');
            
            // Get supplier name for stock mutation record
            $supplier = Supplier::where('kode_supplier', $request->kode_supplier)->first();
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';
            
            // Get creator name from authenticated user or default to 'ADMIN'
            $editor = Auth::check() ? Auth::user()->name : 'ADMIN';

            
            // Format transaction number for mutation record
            $noTransaksi = "BL-" . date('m/y', strtotime($request->tanggal)) . "-" . 
                        substr($nota, strrpos($nota, '-') + 1) . 
                        " ({$editor}) [UPDATED]";
            
            // Get the original items to remove from inventory
            $originalItems = PembelianItem::where('nota', $nota)->get();

            // Reverse FIFO batches before touching stocks/panels
            $this->reverseUnusedStockBatches($originalItems, $nota, 'diedit');
            
            // Track panels to delete
            $panelsToDelete = [];
            
            // For each original item, find and mark panels for deletion
            foreach ($originalItems as $item) {
                // Find panels with this group_id that match the original purchase
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit((int) $item->qty)
                    ->get();
                
                foreach ($panels as $panel) {
                    $panelsToDelete[] = $panel->id;
                }
                
                // Record sale to reverse the original purchase in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    now(), // Use current date/time for the reversal
                    $nota . ' (reversal)',
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    'LBR', // Unit of measure
                    'Purchase reversal for update', // Keterangan
                    $editor, // Created by
                    'default' // Stock owner
                );
            }
            
            // Delete the marked panels
            if (!empty($panelsToDelete)) {
                Panel::whereIn('id', $panelsToDelete)->delete();
            }
            
            // Update purchase
            $pembelian->update([
                'tanggal' => $tanggalWithTime, // Use date with time
                'kode_supplier' => $request->kode_supplier,
                'no_surat_jalan' => $request->no_surat_jalan,
                'pembayaran' => $request->pembayaran ?? $request->metode_pembayaran ?? 'Tunai',
                'cara_bayar' => $request->cara_bayar,
                'subtotal' => $request->subtotal,
                'diskon' => $request->diskon ?? 0,
                'ppn' => $request->ppn ?? 0,
                'grand_total' => $request->grand_total,
                'updated_at' => $currentDateTime,
                'is_edited' => true,
                'edited_by' => $editor,
                'edited_at' => $currentDateTime,
                'edit_reason' => $request->edit_reason,
            ]);
            
            // Delete all existing items
            PembelianItem::where('nota', $nota)->delete();
            
            // Create new purchase items and add new inventory
            foreach ($request->items as $item) {
                // Resolve satuan besar/kecil into base-unit quantity & price
                $kodeBarang = KodeBarang::where('kode_barang', $item['kodeBarang'])->first();
                $conv = $kodeBarang
                    ? $this->resolveUnitConversion($kodeBarang, $item)
                    : ['qty_base' => (float) $item['qty'], 'harga_base' => (float) $item['harga'], 'satuan' => 'LBR', 'satuan_besar' => null, 'qty_besar' => null];

                $qtyBase = $conv['qty_base'];
                $hargaBase = $conv['harga_base'];

                $pembelianItem = PembelianItem::create([
                    'nota' => $nota,
                    'kode_barang' => $item['kodeBarang'],
                    'nama_barang' => $item['namaBarang'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'harga' => $hargaBase,
                    'qty' => $qtyBase,
                    'satuan' => $conv['satuan'],
                    'satuan_besar' => $conv['satuan_besar'],
                    'qty_besar' => $conv['qty_besar'],
                    'diskon' => $item['diskon'] ?? 0,
                    'total' => $item['total'],
                    'created_at' => $currentDateTime,
                ]);

                if ($kodeBarang) {
                    $this->createStockBatchForItem(
                        $pembelianItem,
                        $kodeBarang,
                        $qtyBase,
                        $hargaBase,
                        $request->tanggal,
                        $nota,
                        $supplierName
                    );
                }
                
                // Record new purchase in stock mutation
                $this->stockController->recordPurchase(
                    $item['kodeBarang'],
                    $item['namaBarang'],
                    $noTransaksi,
                    $tanggalWithTime, // Use date with time
                    $nota . ' (updated)',
                    $supplierName . ' (' . $request->kode_supplier . ')',
                    $qtyBase,
                    $conv['satuan'], // Unit of measure (base unit)
                    'Purchase transaction update', // Keterangan
                    $editor, // Created by
                    'default' // Stock owner
                );
                
                if ($kodeBarang) {
                    // Prefer the edited nama barang for new panels
                    $panelName = $item['namaBarang'];
                    $cost = $hargaBase; // Use purchase price (per base unit) as cost
                    
                    // Use the PanelController to add panels to inventory
                    $panelController = app()->make(PanelController::class);
                    
                    $result = $panelController->addPanelsToInventory(
                        $panelName, 
                        $cost, 
                        $item['kodeBarang'], 
                        (int) $qtyBase
                    );
                    
                    // Log the result
                    Log::info('Added panels to inventory during update:', ['result' => $result]);
                    
                    // Update cost di master barang dengan harga pembelian terbaru (per base unit)
                    $kodeBarang->cost = $hargaBase;
                    $kodeBarang->save();
                    Log::info('Updated master barang cost during update:', [
                        'kode_barang' => $item['kodeBarang'],
                        'new_cost' => $hargaBase
                    ]);
                } else {
                    Log::warning('KodeBarang not found for updated purchase item:', ['kode_barang' => $item['kodeBarang']]);
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'id' => $pembelian->id,
                'nota' => $pembelian->nota,
                'message' => 'Pembelian berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian update:', ['exception' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove the specified purchase from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            // Find purchase
            $pembelian = Pembelian::findOrFail($id);
            $nota = $pembelian->nota;
            
            // Get supplier name for stock mutation record
            $supplier = $pembelian->supplierRelation;
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';
            
            // Get creator name or default to 'ADMIN'
            $creator = 'ADMIN';
            
            // Format transaction number for deletion record
            $noTransaksi = "BL-" . date('m/y', strtotime($pembelian->tanggal)) . "-" . 
                           substr($nota, strrpos($nota, '-') + 1) . 
                           " ({$creator}) [DELETED]";
            
            // Get the items to remove from inventory
            $items = PembelianItem::where('nota', $nota)->get();

            // Reverse FIFO batches before deleting
            $this->reverseUnusedStockBatches($items, $nota, 'dihapus');
            
            // Track panels to delete
            $panelsToDelete = [];
            
            foreach ($items as $item) {
                // Find panels with this group_id that match the purchase being deleted
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit((int) $item->qty)
                    ->get();
                
                foreach ($panels as $panel) {
                    $panelsToDelete[] = $panel->id;
                }
                
                // Record sale to reverse the purchase in stock mutation
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    now(), // Use current date/time for the deletion
                    $nota . ' (deleted)',
                    $supplierName . ' (' . $pembelian->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    'default',                    
                    'LBR'
                );
            }
            
            // Delete the marked panels
            if (!empty($panelsToDelete)) {
                Log::info('Deleting panels:', ['panel_ids' => $panelsToDelete]);
                Panel::whereIn('id', $panelsToDelete)->delete();
            }
            
            // Delete all related items first
            PembelianItem::where('nota', $nota)->delete();
            
            // Delete the purchase
            $pembelian->delete();
            
            DB::commit();
            
            return redirect()->route('pembelian.nota.list')
                ->with('success', 'Nota pembelian berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian destroy:', ['exception' => $e->getMessage()]);
            
            return redirect()->route('pembelian.nota.list')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
 * Cancel a purchase transaction
 */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();
            
            // Find purchase
            $pembelian = Pembelian::findOrFail($id);
            
            // Check if already canceled
            if ($pembelian->status === 'canceled') {
                return redirect()->back()->with('error', 'Nota pembelian sudah dibatalkan sebelumnya.');
            }
            
            $nota = $pembelian->nota;
            
            // Get supplier name for stock mutation record
            $supplier = $pembelian->supplierRelation;
            $supplierName = $supplier ? $supplier->nama : 'Unknown Supplier';
            
            // Get current user or default to 'ADMIN'
            $canceledBy = Auth::check() ? Auth::user()->name : 'ADMIN';
            
            // Format transaction number for cancellation record - without time
            $noTransaksi = "BL-" . date('m/y', strtotime($pembelian->tanggal)) . "-" . 
                        substr($nota, strrpos($nota, '-') + 1) . 
                        " ({$canceledBy}) [CANCELED]";
            
            // Get the items to replenish inventory
            $items = PembelianItem::where('nota', $nota)->get();

            // Reverse FIFO batches (also blocks cancel if stock already sold)
            $this->reverseUnusedStockBatches($items, $nota, 'dibatalkan');
            
            // Track panels to mark as unavailable
            $panelsToCancel = [];
            
            // Get current date and time for records
            $currentDateTime = now()->format('Y-m-d H:i:s');
            
            foreach ($items as $item) {
                // Find panels with this group_id that match the purchase being canceled
                $panels = Panel::where('group_id', $item->kode_barang)
                    ->where('available', true)
                    ->orderBy('created_at', 'desc') // Get the most recently added first (likely from this purchase)
                    ->limit((int) $item->qty)
                    ->get();
                
                foreach ($panels as $panel) {
                    $panelsToCancel[] = $panel->id;
                }
                
                // Use the current time in the tanggal field
                $this->stockController->recordSale(
                    $item->kode_barang,
                    $item->nama_barang,
                    $noTransaksi,
                    $currentDateTime, // Include time in the date field
                    $nota . ' (canceled)',
                    $supplierName . ' (' . $pembelian->kode_supplier . ')',
                    $item->qty, // Same quantity as purchase, but as a "sale" to reduce stock
                    'LBR', // 8th parameter should be $satuan (string)
                    'Transaction canceled: ' . $request->cancel_reason, // 9th param - keterangan
                    $canceledBy, // 10th param - created_by
                    $pembelian->cabang ?? 'default' // 11th param - so (stock owner)
                );
            }
            
            // Mark the panels as unavailable but do not delete them
            if (!empty($panelsToCancel)) {
                Log::info('Marking panels as unavailable:', ['panel_ids' => $panelsToCancel]);
                Panel::whereIn('id', $panelsToCancel)->update(['available' => false]);
            }
            
            // Update the purchase as canceled - include full timestamp
            $pembelian->update([
                'status' => 'canceled',
                'canceled_by' => $canceledBy,
                'canceled_at' => now(), // Stores full timestamp
                'cancel_reason' => $request->cancel_reason
            ]);
            
            DB::commit();
            
            return redirect()->route('pembelian.nota.list')
                ->with('success', 'Nota pembelian berhasil dibatalkan.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pembelian cancel:', ['exception' => $e->getMessage()]);
            
            return redirect()->route('pembelian.nota.list')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    /**
 * Generate purchase transactions for all products for testing purposes.
 * THIS IS FOR DEVELOPMENT ONLY - DO NOT USE IN PRODUCTION
 */
    public function generateTestTransactions()
    {
        // Only allow in local/development environment
        if (!app()->environment('local', 'development')) {
            return 'This function is only available in development environment.';
        }

        try {
            DB::beginTransaction();
            
            // Get all kode barang items
            $kodeBarangItems = KodeBarang::where('status', 'Active')->get();
            
            if ($kodeBarangItems->isEmpty()) {
                return 'No active KodeBarang items found.';
            }
            
            // Get suppliers or create a default one if none exists
            $suppliers = Supplier::all();
            if ($suppliers->isEmpty()) {
                // Create a default supplier
                $supplier = Supplier::create([
                    'kode_supplier' => 'SUP-001',
                    'nama' => 'Supplier Test',
                    'alamat' => 'Jl. Test No. 123',
                    'telepon' => '08123456789',
                    'email' => 'supplier@test.com',
                ]);
                $suppliers = collect([$supplier]);
            }
            
            // Group items by attribute to create transactions for similar items
            $groupedItems = $kodeBarangItems->groupBy('attribute');
            
            $faker = \Faker\Factory::create('id_ID');
            $generatedCount = 0;
            
            // Find the highest nota number to avoid duplicates
            $currentMonth = date('m');
            $currentYear = date('y');
            $lastPurchase = Pembelian::where('nota', 'like', "BL/{$currentMonth}/{$currentYear}-%")
                                    ->orderBy('nota', 'desc')
                                    ->first();
            
            $notaNumber = 1;
            if ($lastPurchase) {
                // Extract the number part from the last nota
                $lastNotaParts = explode('-', $lastPurchase->nota);
                if (count($lastNotaParts) > 1) {
                    $notaNumber = (int)$lastNotaParts[1] + 1;
                }
            }
            
            // Create a transaction for each group of items (by attribute)
            foreach ($groupedItems as $attribute => $items) {
                // Skip processing if there are no items in this group
                if ($items->isEmpty()) {
                    continue;
                }
                
                // Generate transaction details with unique nota
                $nota = "BL/{$currentMonth}/{$currentYear}-" . str_pad($notaNumber, 5, '0', STR_PAD_LEFT);
                $notaNumber++;
                
                // Verify this nota doesn't already exist
                while (Pembelian::where('nota', $nota)->exists()) {
                    $nota = "BL/{$currentMonth}/{$currentYear}-" . str_pad($notaNumber, 5, '0', STR_PAD_LEFT);
                    $notaNumber++;
                }
                
                // Select a random supplier
                $supplier = $suppliers->random();
                
                // Use a date within the last 60 days
                $transactionDate = now()->subDays($faker->numberBetween(1, 60));
                
                // Create new transaction
                $subtotal = 0;
                $transactionItems = [];
                
                // Prepare items for this transaction
                foreach ($items as $item) {
                    // Random quantity between 1 and 5
                    $qty = $faker->numberBetween(1, 5);
                    // Use the item's cost as the purchase price
                    $harga = $item->cost;
                    // Calculate total
                    $total = $qty * $harga;
                    $subtotal += $total;
                    
                    $transactionItems[] = [
                        'kodeBarang' => $item->kode_barang,
                        'namaBarang' => $item->name,
                        'harga' => $harga,
                        'qty' => $qty,
                        'total' => $total,
                        'diskon' => 0,
                        'keterangan' => 'Auto-generated for testing'
                    ];
                }
                
                // Random discount between 0% and 5%
                $diskonPersen = $faker->randomFloat(2, 0, 5);
                $diskon = round($subtotal * ($diskonPersen / 100));
                
                // Random PPN between 0% and 11%
                $ppnPersen = $faker->randomFloat(2, 0, 11);
                $ppn = round(($subtotal - $diskon) * ($ppnPersen / 100));
                
                // Calculate grand total
                $grandTotal = $subtotal - $diskon + $ppn;
                
                // Payment methods
                $paymentMethods = ['Tunai', 'Transfer', 'Kredit'];
                $pembayaran = $faker->randomElement($paymentMethods);
                
                $surat_jalan = 'SJ-' . strtoupper($faker->bothify('??####'));
                
                // Create the purchase record
                $pembelian = Pembelian::create([
                    'nota' => $nota,
                    'no_surat_jalan' => $surat_jalan,
                    'tanggal' => $transactionDate,
                    'kode_supplier' => $supplier->kode_supplier,
                    'pembayaran' => $pembayaran,
                    'cara_bayar' => $pembayaran,
                    'subtotal' => $subtotal,
                    'diskon' => $diskon,
                    'ppn' => $ppn,
                    'grand_total' => $grandTotal,
                    'created_at' => $transactionDate,
                    'updated_at' => $transactionDate,
                ]);
                
                // Get creator name
                $creator = 'SYSTEM-TEST';
                
                // Format transaction number for mutation record
                $noTransaksi = "BL-" . $transactionDate->format('m/y') . "-" . 
                            substr($nota, strrpos($nota, '-') + 1) . 
                            " ({$creator})";
                
                // Create purchase items
                foreach ($transactionItems as $item) {
                    // Create purchase item
                    PembelianItem::create([
                        'nota' => $nota,
                        'kode_barang' => $item['kodeBarang'],
                        'nama_barang' => $item['namaBarang'],
                        'keterangan' => $item['keterangan'],
                        'harga' => $item['harga'],
                        'qty' => $item['qty'],
                        'diskon' => $item['diskon'],
                        'total' => $item['total'],
                        'created_at' => $transactionDate,
                        'updated_at' => $transactionDate,
                    ]);
                    
                    // Record purchase in stock mutation
                    $this->stockController->recordPurchase(
                        $item['kodeBarang'],
                        $item['namaBarang'],
                        $noTransaksi,
                        $transactionDate->format('Y-m-d H:i:s'),
                        $nota,
                        $supplier->nama . ' (' . $supplier->kode_supplier . ')',
                        $item['qty'],
                        'LBR',
                        'Auto-generated test transaction',
                        $creator,
                        'default'
                    );
                    
                    // Get the kode barang record for panel creation
                    $kodeBarang = KodeBarang::where('kode_barang', $item['kodeBarang'])->first();
                    if ($kodeBarang) {
                        // Add panels to inventory
                        $result = $this->panelController->addPanelsToInventory(
                            $item['namaBarang'],
                            $item['harga'], // cost
                            $item['kodeBarang'],
                            (int) $item['qty']
                        );
                        
                        $generatedCount += $item['qty'];
                    }
                }
            }
            
            DB::commit();
            
            return "Successfully generated " . count($groupedItems) . " purchase transactions with {$generatedCount} total items!";
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating test transactions:', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 'Error generating test transactions: ' . $e->getMessage();
        }
    }
    }