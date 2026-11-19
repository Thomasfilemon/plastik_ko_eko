<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ReturPenjualan;
use App\Models\ReturPenjualanItem;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
use App\Models\Customer;
use App\Models\NotaKredit;
use App\Models\KodeBarang;
use App\Services\FifoService;

class ReturPenjualanController extends Controller
{
    protected $fifoService;

    public function __construct(FifoService $fifoService)
    {
        $this->fifoService = $fifoService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $returPenjualan = ReturPenjualan::with(['customer', 'transaksi', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('retur_penjualan.index', compact('returPenjualan'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::where('is_active', true)->get();
        $transactions = Transaksi::with(['customer', 'items'])
            ->where('status', '!=', 'canceled')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('retur_penjualan.create', compact('customers', 'transactions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'kode_customer' => 'required|exists:customers,kode_customer',
            'transaksi_id' => 'required|exists:transaksi,id',
            'alasan_retur' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.transaksi_item_id' => 'required|exists:transaksi_items,id',
            'items.*.qty_retur' => 'required|numeric|min:0.01',
            'items.*.alasan' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Generate nomor retur
            $noRetur = $this->generateNoRetur();

            // Get transaction data
            $transaksi = Transaksi::with(['customer', 'items'])->findOrFail($request->transaksi_id);

            // Create retur penjualan
            $returPenjualan = ReturPenjualan::create([
                'no_retur' => $noRetur,
                'tanggal' => $request->tanggal,
                'kode_customer' => $request->kode_customer,
                'no_transaksi' => $transaksi->no_transaksi,
                'transaksi_id' => $request->transaksi_id,
                'total_retur' => 0, // Will be calculated
                'status' => 'pending',
                'alasan_retur' => $request->alasan_retur,
                'created_by' => Auth::id(),
            ]);

            $totalRetur = 0;

            // Create retur items
            foreach ($request->items as $item) {
                $transaksiItem = TransaksiItem::findOrFail($item['transaksi_item_id']);
                
                // Validate qty retur doesn't exceed original qty
                if ($item['qty_retur'] > $transaksiItem->qty) {
                    throw new \Exception("Qty retur tidak boleh melebihi qty asli untuk item {$transaksiItem->nama_barang}");
                }

                $totalItem = $item['qty_retur'] * $transaksiItem->harga;
                $totalRetur += $totalItem;

                ReturPenjualanItem::create([
                    'retur_penjualan_id' => $returPenjualan->id,
                    'transaksi_item_id' => $item['transaksi_item_id'],
                    'kode_barang' => $transaksiItem->kode_barang,
                    'nama_barang' => $transaksiItem->nama_barang,
                    'qty_retur' => $item['qty_retur'],
                    'satuan' => $transaksiItem->satuan ?? 'Pcs',
                    'harga' => $transaksiItem->harga,
                    'total' => $totalItem,
                    'alasan' => $item['alasan'] ?? null,
                ]);
            }

            // Update total retur
            $returPenjualan->update(['total_retur' => $totalRetur]);

            DB::commit();

            return redirect()->route('retur-penjualan.index')
                ->with('success', 'Retur penjualan berhasil dibuat dengan nomor: ' . $noRetur);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating retur penjualan: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $returPenjualan = ReturPenjualan::with([
            'customer', 
            'transaksi', 
            'items.transaksiItem', 
            'createdBy', 
            'approvedBy',
            'notaKredit'
        ])->findOrFail($id);

        return view('retur_penjualan.show', compact('returPenjualan'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $returPenjualan = ReturPenjualan::with(['items'])->findOrFail($id);
        
        if ($returPenjualan->status !== 'pending') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur yang sudah diproses tidak dapat diedit');
        }

        $customers = Customer::where('is_active', true)->get();
        $transactions = Transaksi::with(['customer', 'items'])
            ->where('status', '!=', 'canceled')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('retur_penjualan.edit', compact('returPenjualan', 'customers', 'transactions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $returPenjualan = ReturPenjualan::findOrFail($id);
        
        if ($returPenjualan->status !== 'pending') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur yang sudah diproses tidak dapat diedit');
        }

        $request->validate([
            'tanggal' => 'required|date',
            'kode_customer' => 'required|exists:customers,kode_customer',
            'transaksi_id' => 'required|exists:transaksi,id',
            'alasan_retur' => 'required|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.transaksi_item_id' => 'required|exists:transaksi_items,id',
            'items.*.qty_retur' => 'required|numeric|min:0.01',
            'items.*.alasan' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Update retur penjualan
            $returPenjualan->update([
                'tanggal' => $request->tanggal,
                'kode_customer' => $request->kode_customer,
                'transaksi_id' => $request->transaksi_id,
                'alasan_retur' => $request->alasan_retur,
            ]);

            // Delete existing items
            $returPenjualan->items()->delete();

            $totalRetur = 0;

            // Create new retur items
            foreach ($request->items as $item) {
                $transaksiItem = TransaksiItem::findOrFail($item['transaksi_item_id']);
                
                if ($item['qty_retur'] > $transaksiItem->qty) {
                    throw new \Exception("Qty retur tidak boleh melebihi qty asli untuk item {$transaksiItem->nama_barang}");
                }

                $totalItem = $item['qty_retur'] * $transaksiItem->harga;
                $totalRetur += $totalItem;

                ReturPenjualanItem::create([
                    'retur_penjualan_id' => $returPenjualan->id,
                    'transaksi_item_id' => $item['transaksi_item_id'],
                    'kode_barang' => $transaksiItem->kode_barang,
                    'nama_barang' => $transaksiItem->nama_barang,
                    'qty_retur' => $item['qty_retur'],
                    'satuan' => $transaksiItem->satuan ?? 'Pcs',
                    'harga' => $transaksiItem->harga,
                    'total' => $totalItem,
                    'alasan' => $item['alasan'] ?? null,
                ]);
            }

            // Update total retur
            $returPenjualan->update(['total_retur' => $totalRetur]);

            DB::commit();

            return redirect()->route('retur-penjualan.index')
                ->with('success', 'Retur penjualan berhasil diperbarui');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating retur penjualan: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $returPenjualan = ReturPenjualan::findOrFail($id);
        
        if ($returPenjualan->status !== 'pending') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur yang sudah diproses tidak dapat dihapus');
        }

        try {
            DB::beginTransaction();
            
            $returPenjualan->items()->delete();
            $returPenjualan->delete();
            
            DB::commit();

            return redirect()->route('retur-penjualan.index')
                ->with('success', 'Retur penjualan berhasil dihapus');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting retur penjualan: ' . $e->getMessage());
            
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Terjadi kesalahan saat menghapus retur penjualan');
        }
    }

    /**
     * Approve retur penjualan
     */
    public function approve(Request $request, string $id)
    {
        $returPenjualan = ReturPenjualan::with(['items'])->findOrFail($id);
        
        if ($returPenjualan->status !== 'pending') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur sudah diproses sebelumnya');
        }

        try {
            DB::beginTransaction();

            // Update status to approved
            $returPenjualan->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Create nota kredit
            $noNotaKredit = $this->generateNoNotaKredit();
            
            NotaKredit::create([
                'no_nota_kredit' => $noNotaKredit,
                'tanggal' => now()->toDateString(),
                'kode_customer' => $returPenjualan->kode_customer,
                'retur_penjualan_id' => $returPenjualan->id,
                'total_kredit' => $returPenjualan->total_retur,
                'keterangan' => "Nota kredit untuk retur penjualan {$returPenjualan->no_retur}",
                'status' => 'approved',
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Update customer credit
            $customer = Customer::where('kode_customer', $returPenjualan->kode_customer)->first();
            if ($customer) {
                $customer->decrement('total_piutang', $returPenjualan->total_retur);
            }

            DB::commit();

            return redirect()->route('retur-penjualan.index')
                ->with('success', "Retur penjualan berhasil disetujui. Nota kredit: {$noNotaKredit}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error approving retur penjualan: ' . $e->getMessage());
            
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Terjadi kesalahan saat menyetujui retur penjualan');
        }
    }

    /**
     * Process retur penjualan (adjust stock)
     */
    public function process(string $id)
    {
        $returPenjualan = ReturPenjualan::with(['items'])->findOrFail($id);
        
        if ($returPenjualan->status !== 'approved') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur harus disetujui terlebih dahulu');
        }

        try {
            DB::beginTransaction();

            // Process each item - add stock back at original FIFO modal (NOT harga jual)
            $returPenjualan->loadMissing('items.transaksiItem.transaksiItemSumber');
            foreach ($returPenjualan->items as $item) {
                // Get kode_barang ID from kode_barang string
                $kodeBarang = KodeBarang::where('kode_barang', $item->kode_barang)->first();
                if (!$kodeBarang) {
                    throw new \Exception("Kode barang {$item->kode_barang} tidak ditemukan");
                }

                $hargaModal = $this->fifoService->resolveHargaModalFromTransaksiItem(
                    $item->transaksiItem,
                    $kodeBarang
                );
                
                // Add stock back using FIFO service
                $this->fifoService->addStock(
                    $kodeBarang->id,
                    $item->qty_retur,
                    $hargaModal,
                    "Retur Penjualan {$returPenjualan->no_retur}"
                );
            }

            // Update status to processed
            $returPenjualan->update(['status' => 'processed']);

            DB::commit();

            return redirect()->route('retur-penjualan.index')
                ->with('success', 'Retur penjualan berhasil diproses dan stok telah disesuaikan');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing retur penjualan: ' . $e->getMessage());
            
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Terjadi kesalahan saat memproses retur penjualan');
        }
    }

    /**
     * Reject retur penjualan
     */
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'alasan_reject' => 'required|string|max:500',
        ]);

        $returPenjualan = ReturPenjualan::findOrFail($id);
        
        if ($returPenjualan->status !== 'pending') {
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Retur sudah diproses sebelumnya');
        }

        try {
            $returPenjualan->update([
                'status' => 'rejected',
                'alasan_retur' => $returPenjualan->alasan_retur . "\n\nAlasan ditolak: " . $request->alasan_reject,
            ]);

            return redirect()->route('retur-penjualan.index')
                ->with('success', 'Retur penjualan berhasil ditolak');

        } catch (\Exception $e) {
            Log::error('Error rejecting retur penjualan: ' . $e->getMessage());
            
            return redirect()->route('retur-penjualan.index')
                ->with('error', 'Terjadi kesalahan saat menolak retur penjualan');
        }
    }

    /**
     * Get transaction items for AJAX
     */
    public function getTransactionItems(Request $request)
    {
        $transaksiId = $request->get('transaksi_id');
        
        if (!$transaksiId) {
            return response()->json(['error' => 'Transaksi ID required'], 400);
        }

        $items = TransaksiItem::where('transaksi_id', $transaksiId)
            ->with('kodeBarang')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->nama_barang,
                    'qty' => $item->qty,
                    'satuan' => $item->satuan ?? 'Pcs',
                    'harga' => $item->harga,
                    'total' => $item->total,
                ];
            });

        return response()->json(['items' => $items]);
    }

    /**
     * Generate nomor retur
     */
    private function generateNoRetur()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastRetur = ReturPenjualan::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastRetur) {
            $lastNumber = (int) substr($lastRetur->no_retur, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "RP-{$year}{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate nomor nota kredit
     */
    private function generateNoNotaKredit()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastNota = NotaKredit::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastNota) {
            $lastNumber = (int) substr($lastNota->no_nota_kredit, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "NK-{$year}{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
