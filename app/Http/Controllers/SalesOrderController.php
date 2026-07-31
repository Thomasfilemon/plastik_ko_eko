<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Customer;
use App\Models\KodeBarang;
use App\Models\StokOwner;
use App\Models\Transaksi;
use App\Models\TransaksiItem;
use App\Services\UnitConversionService;
use App\Services\CustomerCreditService;
use App\Services\FifoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SalesOrderController extends Controller
{
    protected $unitService;
    protected $creditService;
    protected $fifoService;

    public function __construct(UnitConversionService $unitService, CustomerCreditService $creditService, FifoService $fifoService)
    {
        $this->unitService = $unitService;
        $this->creditService = $creditService;
        $this->fifoService = $fifoService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'salesman', 'items.kodeBarang']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by salesman
        if ($request->filled('salesman_id')) {
            $query->where('salesman_id', $request->salesman_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $salesOrders = $query->orderBy('created_at', 'desc')->paginate(15);
        $customers = Customer::orderBy('nama')->get();
        $salesmen = StokOwner::all();

        return view('sales_order.index', compact('salesOrders', 'customers', 'salesmen'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $noSo = SalesOrder::generateNoSo();
        $customers = Customer::orderBy('nama')->get();
        $salesmen = StokOwner::orderBy('id')->get();
        $kodeBarangs = KodeBarang::active()->orderBy('name')->get();

        return view('sales_order.create', compact('noSo', 'customers', 'salesmen', 'kodeBarangs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'no_so' => 'required|unique:sales_orders,no_so',
            'tanggal' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'salesman_id' => 'required|exists:stok_owners,id',
            'cara_bayar' => 'required|in:Tunai,Kredit',
            'hari_tempo' => 'required_if:cara_bayar,Kredit|integer|min:0',
            'tanggal_jatuh_tempo' => 'nullable|date|after_or_equal:tanggal',
            'tanggal_estimasi' => 'nullable|date|after_or_equal:tanggal',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.kode_barang_id' => 'required|exists:kode_barangs,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.satuan' => 'required|string',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Cek kelayakan kredit jika cara bayar kredit
            if ($request->cara_bayar === 'Kredit') {
                $customer = Customer::find($request->customer_id);
                $totalTransaksi = 0;
                
                foreach ($request->items as $item) {
                    $totalTransaksi += $item['qty'] * $item['harga'];
                }

                $kelayakan = $this->creditService->cekKelayakanKredit($customer->id, $totalTransaksi);
                if (!$kelayakan['layak']) {
                    return back()->withErrors(['credit_limit' => $kelayakan['alasan']])->withInput();
                }
            }

            // Buat Sales Order
            $salesOrder = SalesOrder::create([
                'no_so' => $request->no_so,
                'tanggal' => $request->tanggal,
                'customer_id' => $request->customer_id,
                'salesman_id' => $request->salesman_id,
                'status' => 'pending',
                'cara_bayar' => $request->cara_bayar,
                'hari_tempo' => $request->hari_tempo ?? 0,
                'tanggal_jatuh_tempo' => $request->tanggal_jatuh_tempo,
                'tanggal_estimasi' => $request->tanggal_estimasi,
                'keterangan' => $request->keterangan,
            ]);

            $subtotal = 0;

            // Buat Sales Order Items
            foreach ($request->items as $item) {
                $kodeBarang = KodeBarang::find($item['kode_barang_id']);
                
                // Konversi qty ke unit dasar untuk validasi stok
                $qtyInBaseUnit = $this->unitService->convertToBaseUnit(
                    $kodeBarang->id, 
                    $item['qty'], 
                    $item['satuan']
                );
                // dd($qtyInBaseUnit);

                // Validasi stok tersedia (opsional, bisa diabaikan untuk SO)
                $stokTersedia = $this->fifoService->getStokTersedia($kodeBarang->id);
                if ($stokTersedia < $qtyInBaseUnit) {
                    throw new Exception("Stok tidak mencukupi untuk {$kodeBarang->name}");
                }

                $total = $item['qty'] * $item['harga'];
                $subtotal += $total;

                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'kode_barang_id' => $item['kode_barang_id'],
                    'qty' => $item['qty'],
                    'satuan' => $item['satuan'],
                    'harga' => $item['harga'],
                    'total' => $total,
                    'qty_terkirim' => 0,
                    'qty_sisa' => $item['qty'],
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }

            // Update total Sales Order
            $salesOrder->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal, // Belum ada diskon
            ]);

            DB::commit();

            return redirect()->route('sales-order.index')
                ->with('success', 'Sales Order berhasil dibuat dengan nomor: ' . $salesOrder->no_so);

        } catch (Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'salesman', 'items.kodeBarang']);
        
        return view('sales_order.show', compact('salesOrder'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeCanceled()) {
            return redirect()->route('sales-order.show', $salesOrder)
                ->with('error', 'Sales Order tidak dapat diedit karena sudah diproses');
        }

        $salesOrder->load(['items.kodeBarang']);
        $customers = Customer::orderBy('nama')->get();
        $salesmen = StokOwner::orderBy('id')->get();
        $kodeBarangs = KodeBarang::active()->orderBy('name')->get();

        return view('sales_order.edit', compact('salesOrder', 'customers', 'salesmen', 'kodeBarangs'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeCanceled()) {
            return redirect()->route('sales-order.show', $salesOrder)
                ->with('error', 'Sales Order tidak dapat diedit karena sudah diproses');
        }

        $request->validate([
            'tanggal' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'salesman_id' => 'required|exists:stok_owners,id',
            'cara_bayar' => 'required|in:Tunai,Kredit',
            'hari_tempo' => 'required_if:cara_bayar,Kredit|integer|min:0',
            'tanggal_estimasi' => 'nullable|date|after_or_equal:tanggal',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.kode_barang_id' => 'required|exists:kode_barangs,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.satuan' => 'required|string',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Update Sales Order
            $salesOrder->update([
                'tanggal' => $request->tanggal,
                'customer_id' => $request->customer_id,
                'salesman_id' => $request->salesman_id,
                'cara_bayar' => $request->cara_bayar,
                'hari_tempo' => $request->hari_tempo ?? 0,
                'tanggal_estimasi' => $request->tanggal_estimasi,
                'keterangan' => $request->keterangan,
            ]);

            // Hapus items lama
            $salesOrder->items()->delete();

            $subtotal = 0;

            // Buat items baru
            foreach ($request->items as $item) {
                $total = $item['qty'] * $item['harga'];
                $subtotal += $total;

                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'kode_barang_id' => $item['kode_barang_id'],
                    'qty' => $item['qty'],
                    'satuan' => $item['satuan'],
                    'harga' => $item['harga'],
                    'total' => $total,
                    'qty_terkirim' => 0,
                    'qty_sisa' => $item['qty'],
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }

            // Update total
            $salesOrder->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
            ]);

            DB::commit();

            return redirect()->route('sales-order.show', $salesOrder)
                ->with('success', 'Sales Order berhasil diperbarui');

        } catch (Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeCanceled()) {
            return redirect()->route('sales-order.show', $salesOrder)
                ->with('error', 'Sales Order tidak dapat dihapus karena sudah diproses');
        }

        try {
            $salesOrder->delete();
            return redirect()->route('sales-order.index')
                ->with('success', 'Sales Order berhasil dihapus');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve Sales Order
     */
    public function approve(SalesOrder $salesOrder)
    {
        if (!$salesOrder->canBeApproved()) {
            return back()->with('error', 'Sales Order tidak dapat disetujui');
        }

        try {
            // Langsung ubah status menjadi processed setelah approve
            $salesOrder->update(['status' => 'processed']);
            
            // Redirect ke halaman faktur penjualan dengan parameter sales order
            return redirect()->route('transaksi.penjualan', ['sales_order_id' => $salesOrder->id])
                ->with('success', 'Sales Order berhasil disetujui dan diproses. Silakan buat faktur penjualan.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Re-approve Sales Order yang sudah dibatalkan
     */
    public function reApprove(SalesOrder $salesOrder)
    {
        if (!$salesOrder->isCanceled()) {
            return back()->with('error', 'Hanya Sales Order yang dibatalkan yang dapat di-approve kembali');
        }

        try {
            // Ubah status menjadi processed untuk re-approve
            $salesOrder->update(['status' => 'processed']);
            
            // Redirect ke halaman faktur penjualan dengan parameter sales order
            return redirect()->route('transaksi.penjualan', ['sales_order_id' => $salesOrder->id])
                ->with('success', 'Sales Order berhasil di-approve kembali dan diproses. Silakan buat faktur penjualan.');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Process Sales Order
     */
    public function process(SalesOrder $salesOrder)
    {
        // Cek apakah Sales Order sudah diproses
        if ($salesOrder->isProcessed()) {
            return back()->with('info', 'Sales Order sudah diproses sebelumnya');
        }

        if (!$salesOrder->canBeProcessed()) {
            return back()->with('error', 'Sales Order tidak dapat diproses');
        }

        try {
            $salesOrder->update(['status' => 'processed']);
            return back()->with('success', 'Sales Order berhasil diproses');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancel Sales Order
     */
    public function cancel(SalesOrder $salesOrder)
    {
        // Validasi: tidak boleh ada Surat Jalan atau Faktur aktif dari SO ini
        // Cek adanya Surat Jalan terkait SO ini (tanpa asumsi kolom status di tabel surat_jalan)
        $hasActiveSJ = \App\Models\SuratJalan::whereHas('transaksi', function($q) use ($salesOrder) {
                $q->where('sales_order_id', $salesOrder->id);
            })
            ->exists();

        $hasActiveInvoice = Transaksi::where('sales_order_id', $salesOrder->id)
            ->where('status', '!=', 'canceled')
            ->exists();

        if ($hasActiveSJ || $hasActiveInvoice) {
            return back()->with('error', 'Batalkan semua Surat Jalan/Faktur terkait terlebih dahulu.');
        }

        try {
            $salesOrder->update(['status' => 'canceled']);
            return back()->with('success', 'Sales Order berhasil dibatalkan');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Get customer price for specific product
     */
    public function getCustomerPrice(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'kode_barang_id' => 'required|exists:kode_barangs,id',
            'unit' => 'required|string',
        ]);

        try {
            $priceInfo = $this->unitService->getCustomerPrice(
                $request->customer_id,
                $request->kode_barang_id,
                $request->unit
            );

            return response()->json($priceInfo);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available units for product
     */
    public function getAvailableUnits($kodeBarangId)
    {
        try {
            $payload = $this->unitService->getAvailableUnitsWithFactors((int) $kodeBarangId);
            return response()->json($payload);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Search sales orders for API
     */
    public function search(Request $request)
    {
        $keyword = $request->input('keyword');
        
        if (!$keyword || strlen($keyword) < 2) {
            return response()->json([]);
        }

        $salesOrders = SalesOrder::with(['customer', 'salesman', 'items.kodeBarang'])
            ->where(function($query) use ($keyword) {
                $query->where('no_so', 'like', "%{$keyword}%")
                      ->orWhereHas('customer', function($q) use ($keyword) {
                          $q->where('nama', 'like', "%{$keyword}%")
                            ->orWhere('kode_customer', 'like', "%{$keyword}%");
                      });
            })
            ->where('status', 'processed') // Only show processed sales orders
            ->limit(10)
            ->get();

        return response()->json($salesOrders);
    }

    /**
     * Get items for a specific sales order
     */
    public function getItems(SalesOrder $salesOrder)
    {
        $items = $salesOrder->items()->with('kodeBarang')->get();
        
        // Debug logging
        Log::info('Sales Order Items API Response:', [
            'sales_order_id' => $salesOrder->id,
            'items_count' => $items->count(),
            'items' => $items->toArray()
        ]);
        
        return response()->json($items);
    }

    public function convertToTransaksi(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'processed') {
            return back()->with('error', 'Sales Order harus diproses sebelum dikonversi ke penjualan.');
        }

        DB::beginTransaction();
        try {
            // Generate nomor transaksi baru
            $noTransaksi = Transaksi::generateNoTransaksi(); // buat helper di model Transaksi

            // Buat transaksi header
            $transaksi = Transaksi::create([
                'no_transaksi' => $noTransaksi,
                'tanggal' => now(),
                'kode_customer' => $salesOrder->customer->kode_customer,
                'sales' => $salesOrder->salesman->kode_stok_owner,
                'pembayaran' => $salesOrder->cara_bayar,
                'cara_bayar' => $salesOrder->cara_bayar,
                'tanggal_jadi' => $salesOrder->tanggal_estimasi ?? now(),
                'subtotal' => $salesOrder->subtotal,
                'discount' => 0,
                'disc_rupiah' => 0,
                'ppn' => 0,
                'dp' => 0,
                'grand_total' => $salesOrder->grand_total,
                'status' => 'open',
                'status_piutang' => $salesOrder->cara_bayar === 'Kredit' ? 'belum_dibayar' : 'lunas',
                'total_dibayar' => $salesOrder->cara_bayar === 'Tunai' ? $salesOrder->grand_total : 0,
                'sisa_piutang' => $salesOrder->cara_bayar === 'Kredit' ? $salesOrder->grand_total : 0,
                'tanggal_jatuh_tempo' => $salesOrder->cara_bayar === 'Kredit'
                    ? now()->addDays($salesOrder->hari_tempo)
                    : null,
                'created_from_po' => true,
            ]);
            // dd($transaksi);

            // Buat transaksi item
            foreach ($salesOrder->items as $item) {
                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'no_transaksi' => $noTransaksi,
                    'kode_barang' => $item->kode_barang_id,
                    'nama_barang' => $item->kodeBarang->name,
                    'keterangan' => $item->keterangan,
                    'harga' => $item->harga,
                    'qty' => $item->qty,
                    'satuan' => $item->satuan,
                    'diskon' => 0,
                    'total' => $item->total,
                    'ongkos_kuli' => 0,
                ]);
            }

            // Update status SO menjadi completed
            $salesOrder->update(['status' => 'completed']);

            DB::commit();

            return redirect()->route('transaksi.index')
                ->with('success', 'Sales Order berhasil dikonversi menjadi Transaksi Penjualan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal konversi: ' . $e->getMessage());
        }
    }
}
