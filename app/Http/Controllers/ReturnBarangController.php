<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ReturnBarang;
use App\Models\ReturnBarangItem;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
use App\Models\Customer;
use App\Models\KodeBarang;
use App\Services\FifoService;

class ReturnBarangController extends Controller
{
    protected $fifoService;

    public function __construct(FifoService $fifoService)
    {
        $this->fifoService = $fifoService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ReturnBarang::with(['customer', 'transaksiAsal']);

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan tipe return
        if ($request->filled('tipe_return')) {
            $query->where('tipe_return', $request->tipe_return);
        }

        // Filter berdasarkan customer
        if ($request->filled('customer')) {
            $query->where('kode_customer', 'like', '%' . $request->customer . '%');
        }

        // Filter berdasarkan tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        $returns = $query->orderBy('tanggal', 'desc')->paginate(15);

        return view('return_barang.index', compact('returns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $noTransaksi = $request->get('no_transaksi');
        $transaksi = null;
        $items = collect();

        if ($noTransaksi) {
            $transaksi = Transaksi::with(['items.kodeBarang', 'customer'])
                ->where('no_transaksi', $noTransaksi)
                ->first();

            if ($transaksi) {
                $items = $transaksi->items;
            }
        }

        return view('return_barang.create', compact('transaksi', 'items'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'kode_customer' => 'required|exists:customers,kode_customer',
            'no_transaksi_asal' => 'required|exists:transaksi,no_transaksi',
            'tipe_return' => 'required|in:penjualan,pembelian',
            'alasan_return' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.kode_barang' => 'required|string',
            'items.*.qty_return' => 'required|numeric|min:0.01',
            'items.*.harga' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate nomor return
            $noReturn = ReturnBarang::generateNoReturn();

            // Create return barang
            $returnBarang = ReturnBarang::create([
                'no_return' => $noReturn,
                'tanggal' => $request->tanggal,
                'kode_customer' => $request->kode_customer,
                'no_transaksi_asal' => $request->no_transaksi_asal,
                'tipe_return' => $request->tipe_return,
                'status' => 'pending',
                'alasan_return' => $request->alasan_return,
                'created_by' => Auth::user()->name ?? 'System',
            ]);

            $totalReturn = 0;

            // Create return barang items
            foreach ($request->items as $item) {
                $itemTotal = $item['qty_return'] * $item['harga'];
                $totalReturn += $itemTotal;

                ReturnBarangItem::create([
                    'return_barang_id' => $returnBarang->id,
                    'kode_barang' => $item['kode_barang'],
                    'nama_barang' => $item['nama_barang'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'qty_return' => $item['qty_return'],
                    'satuan' => $item['satuan'] ?? 'LBR',
                    'harga' => $item['harga'],
                    'total' => $itemTotal,
                    'status_item' => 'pending',
                ]);
            }

            // Update total return
            $returnBarang->update(['total_return' => $totalReturn]);

            DB::commit();

            return redirect()->route('return-barang.index')
                ->with('success', 'Return barang berhasil dibuat dengan nomor: ' . $noReturn);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating return barang: ' . $e->getMessage());

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnBarang $returnBarang)
    {
        $returnBarang->load(['customer', 'transaksiAsal', 'items.kodeBarang']);

        return view('return_barang.show', compact('returnBarang'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeApproved()) {
            return redirect()->route('return-barang.index')
                ->with('error', 'Return barang tidak dapat diedit karena sudah diproses.');
        }

        $returnBarang->load(['customer', 'transaksiAsal', 'items.kodeBarang']);

        return view('return_barang.edit', compact('returnBarang'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeApproved()) {
            return redirect()->route('return-barang.index')
                ->with('error', 'Return barang tidak dapat diedit karena sudah diproses.');
        }

        $request->validate([
            'tanggal' => 'required|date',
            'alasan_return' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:return_barang_items,id',
            'items.*.qty_return' => 'required|numeric|min:0.01',
            'items.*.harga' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Update return barang
            $returnBarang->update([
                'tanggal' => $request->tanggal,
                'alasan_return' => $request->alasan_return,
            ]);

            $totalReturn = 0;

            // Update return barang items
            foreach ($request->items as $item) {
                $itemTotal = $item['qty_return'] * $item['harga'];
                $totalReturn += $itemTotal;

                ReturnBarangItem::where('id', $item['id'])
                    ->update([
                        'qty_return' => $item['qty_return'],
                        'harga' => $item['harga'],
                        'total' => $itemTotal,
                        'keterangan' => $item['keterangan'] ?? null,
                    ]);
            }

            // Update total return
            $returnBarang->update(['total_return' => $totalReturn]);

            DB::commit();

            return redirect()->route('return-barang.show', $returnBarang)
                ->with('success', 'Return barang berhasil diupdate.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating return barang: ' . $e->getMessage());

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeApproved()) {
            return redirect()->route('return-barang.index')
                ->with('error', 'Return barang tidak dapat dihapus karena sudah diproses.');
        }

        try {
            $returnBarang->delete();

            return redirect()->route('return-barang.index')
                ->with('success', 'Return barang berhasil dihapus.');

        } catch (\Exception $e) {
            Log::error('Error deleting return barang: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Approve return barang
     */
    public function approve(Request $request, ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeApproved()) {
            return back()->with('error', 'Return barang tidak dapat diapprove.');
        }

        $request->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        try {
            $returnBarang->update([
                'status' => 'approved',
                'approved_by' => Auth::user()->name ?? 'System',
                'approved_at' => now(),
                'catatan_approval' => $request->catatan_approval,
            ]);

            return back()->with('success', 'Return barang berhasil diapprove.');

        } catch (\Exception $e) {
            Log::error('Error approving return barang: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Reject return barang
     */
    public function reject(Request $request, ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeRejected()) {
            return back()->with('error', 'Return barang tidak dapat direject.');
        }

        $request->validate([
            'catatan_approval' => 'required|string|max:500',
        ]);

        try {
            $returnBarang->update([
                'status' => 'rejected',
                'approved_by' => Auth::user()->name ?? 'System',
                'approved_at' => now(),
                'catatan_approval' => $request->catatan_approval,
            ]);

            return back()->with('success', 'Return barang berhasil direject.');

        } catch (\Exception $e) {
            Log::error('Error rejecting return barang: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Process return barang (update stock)
     */
    public function process(ReturnBarang $returnBarang)
    {
        if (!$returnBarang->canBeProcessed()) {
            return back()->with('error', 'Return barang tidak dapat diproses.');
        }

        try {
            DB::beginTransaction();

            // Update status to processed
            $returnBarang->update(['status' => 'processed']);

            // Process each item
            foreach ($returnBarang->items as $item) {
                // Update qty_return di transaksi_items
                $transaksiItem = \App\Models\TransaksiItem::where('no_transaksi', $returnBarang->no_transaksi_asal)
                    ->where('kode_barang', $item->kode_barang)
                    ->first();
                
                if ($transaksiItem) {
                    // Check if item can be returned
                    if (!$transaksiItem->canBeReturned($item->qty_return)) {
                        throw new \Exception("Quantity return ({$item->qty_return}) melebihi quantity tersisa ({$transaksiItem->qty_sisa}) untuk item {$item->nama_barang}");
                    }
                    
                    // Add return quantity
                    $transaksiItem->addReturnQty($item->qty_return);
                }

                // Get kode barang ID from kode barang string
                $kodeBarang = \App\Models\KodeBarang::where('kode_barang', $item->kode_barang)->first();
                if (!$kodeBarang) {
                    throw new \Exception("Kode barang {$item->kode_barang} tidak ditemukan");
                }

                if ($returnBarang->tipe_return === 'penjualan') {
                    // Return barang dari penjualan = tambah stok at FIFO modal (NOT harga jual)
                    $hargaModal = $this->fifoService->resolveHargaModalFromTransaksiItem(
                        $transaksiItem,
                        $kodeBarang
                    );
                    $this->fifoService->addStock(
                        $kodeBarang->id,
                        $item->qty_return,
                        $hargaModal,
                        "Return dari {$returnBarang->no_return}",
                        $returnBarang->kode_customer
                    );
                } else {
                    // Return barang ke supplier = kurangi stok
                    $this->fifoService->reduceStock(
                        $kodeBarang->id,
                        $item->qty_return,
                        "Return ke supplier dari {$returnBarang->no_return}",
                        $returnBarang->kode_customer
                    );
                }
            }

            DB::commit();

            return back()->with('success', 'Return barang berhasil diproses dan stok telah diupdate.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing return barang: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Search transactions for return
     */
    public function searchTransactions(Request $request)
    {
        $query = $request->get('q');
        
        $transactions = Transaksi::with(['customer'])
            ->where('no_transaksi', 'like', "%{$query}%")
            ->orWhereHas('customer', function($q) use ($query) {
                $q->where('nama', 'like', "%{$query}%");
            })
            ->take(10)
            ->get(['id', 'no_transaksi', 'kode_customer', 'tanggal', 'grand_total']);

        return response()->json($transactions);
    }

    /**
     * Get transaction items for return
     */
    public function getTransactionItems(Request $request)
    {
        $noTransaksi = $request->get('no_transaksi');
        
        $transaksi = Transaksi::with(['items', 'customer'])
            ->where('no_transaksi', $noTransaksi)
            ->first();

        if (!$transaksi) {
            return response()->json(['error' => 'Transaksi tidak ditemukan'], 404);
        }

                        $items = $transaksi->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'kode_barang' => $item->kode_barang,
                        'nama_barang' => $item->nama_barang,
                        'qty_asal' => $item->qty,
                        'qty_return' => $item->qty_return ?? 0,
                        'qty_sisa' => $item->qty_sisa ?? $item->qty,
                        'harga' => $item->harga,
                        'satuan' => 'LBR', // Default satuan
                        'keterangan' => $item->keterangan,
                    ];
                });

        return response()->json([
            'transaksi' => $transaksi,
            'items' => $items
        ]);
    }
}