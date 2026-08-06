<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\PembayaranUtangSupplier;
use App\Models\PembayaranUtangSupplierDetail;
use App\Models\PembayaranUtangSupplierNotaDebit;
use App\Models\Pembelian;
use App\Models\Supplier;
use App\Models\NotaDebit;
use App\Models\Kas;
use Carbon\Carbon;

class PembayaranUtangSupplierController extends Controller
{
    /**
     * Display a listing of pembayaran utang supplier
     */
    public function index()
    {
        $pembayarans = PembayaranUtangSupplier::with(['supplier', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $summary = [
            'total_pembayaran_hari_ini' => PembayaranUtangSupplier::getTotalPembayaranHariIni(),
            'total_pembayaran_bulan_ini' => PembayaranUtangSupplier::getTotalPembayaranBulanIni(),
            'total_utang_tertagih' => Pembelian::where('status', '!=', 'canceled')
                ->whereIn('status_utang', ['belum_dibayar', 'sebagian'])
                ->sum('sisa_utang'),
            'total_utang_jatuh_tempo' => Pembelian::where('status', '!=', 'canceled')
                ->where('tanggal', '<=', now()->subDays(30))
                ->whereIn('status_utang', ['belum_dibayar', 'sebagian'])
                ->sum('sisa_utang')
        ];

        return view('pembayaran_utang_supplier.index', compact('pembayarans', 'summary'));
    }

    /**
     * Show the form for creating a new pembayaran
     */
    public function create()
    {
        $suppliers = Supplier::orderBy('nama')->get();
        $noPembayaran = PembayaranUtangSupplier::generateNoPembayaran();
        
        return view('pembayaran_utang_supplier.create', compact('suppliers', 'noPembayaran'));
    }

    /**
     * Get supplier's unpaid invoices for payment
     */
    public function getSupplierInvoices(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id'
        ]);

        $supplier = Supplier::find($request->supplier_id);
        
        $invoices = Pembelian::where('kode_supplier', $supplier->kode_supplier)
            ->where('status', '!=', 'canceled')
            ->whereIn('status_utang', ['belum_dibayar', 'sebagian'])
            ->orderBy('tanggal', 'asc')
            ->get()
            ->map(function ($pembelian) {
                return [
                    'id' => $pembelian->id,
                    'no_pembelian' => $pembelian->nota,
                    'tanggal' => $pembelian->tanggal->format('d/m/Y'),
                    'total_faktur' => $pembelian->grand_total,
                    'sudah_dibayar' => $pembelian->total_dibayar ?? 0,
                    'sisa_utang' => $pembelian->sisa_utang ?? $pembelian->grand_total,
                    'status_utang' => $pembelian->status_utang ?? 'belum_dibayar'
                ];
            });

        return response()->json($invoices);
    }

    /**
     * Get supplier's available nota debits
     */
    public function getSupplierNotaDebits(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id'
        ]);

        $supplier = Supplier::find($request->supplier_id);
        
        $notaDebits = NotaDebit::where('kode_supplier', $supplier->kode_supplier)
            ->where('status', 'approved')
            ->where('sisa_nota_debit', '>', 0)
            ->orderBy('tanggal', 'asc')
            ->get()
            ->map(function ($notaDebit) {
                return [
                    'id' => $notaDebit->id,
                    'no_nota_debit' => $notaDebit->no_nota_debit,
                    'tanggal' => $notaDebit->tanggal->format('d/m/Y'),
                    'total_nota_debit' => $notaDebit->total_debit,
                    'sudah_digunakan' => $notaDebit->total_debit - $notaDebit->sisa_nota_debit,
                    'sisa_nota_debit' => $notaDebit->sisa_nota_debit,
                    'keterangan' => $notaDebit->keterangan
                ];
            });

        return response()->json($notaDebits);
    }

    /**
     * Store a newly created pembayaran
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'tanggal_bayar' => 'required|date',
            'total_bayar' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|string',
            'cara_bayar' => 'required|string',
            'no_referensi' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'payment_details' => 'required|array|min:1',
            'payment_details.*.pembelian_id' => 'required|exists:pembelian,id',
            'payment_details.*.jumlah_dilunasi' => 'required|numeric|min:0',
            'nota_debit_details' => 'nullable|array',
            'nota_debit_details.*.nota_debit_id' => 'required|exists:nota_debit,id',
            'nota_debit_details.*.jumlah_digunakan' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $supplier = Supplier::find($request->supplier_id);
            
            // Calculate total utang from selected invoices only
            $totalUtang = 0;
            foreach ($request->payment_details as $detail) {
                $pembelian = Pembelian::find($detail['pembelian_id']);
                if ($pembelian) {
                    $totalUtang += $pembelian->sisa_utang;
                }
            }

            // Calculate total nota debit used
            $totalNotaDebit = 0;
            if ($request->nota_debit_details) {
                $totalNotaDebit = collect($request->nota_debit_details)->sum('jumlah_digunakan');
            }

            // Create pembayaran record
            $pembayaran = PembayaranUtangSupplier::create([
                'supplier_id' => $request->supplier_id,
                'no_pembayaran' => PembayaranUtangSupplier::generateNoPembayaran(),
                'tanggal_bayar' => $request->tanggal_bayar,
                'total_bayar' => $request->total_bayar,
                'total_utang' => $totalUtang,
                'sisa_utang' => $totalUtang - $request->total_bayar - $totalNotaDebit,
                'total_nota_debit' => $totalNotaDebit,
                'metode_pembayaran' => $request->metode_pembayaran,
                'cara_bayar' => $request->cara_bayar,
                'no_referensi' => $request->no_referensi,
                'keterangan' => $request->keterangan,
                'status' => 'confirmed', // Auto confirm for now
                'created_by' => Auth::id(),
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now()
            ]);

            // Create payment details
            foreach ($request->payment_details as $detail) {
                $pembelian = Pembelian::find($detail['pembelian_id']);
                $sudahDibayar = $pembelian->total_dibayar ?? 0;
                $jumlahDilunasi = $detail['jumlah_dilunasi'];
                
                // Hitung total nota debit yang digunakan untuk pembelian ini dalam transaksi ini
                $notaDebitDigunakanTransaksiIni = 0;
                if ($request->nota_debit_details) {
                    $notaDebitDigunakanTransaksiIni = collect($request->nota_debit_details)->sum('jumlah_digunakan');
                }
                
                // Sisa tagihan = (grand_total - nota_debit_digunakan) - sudah_dibayar - jumlah_dilunasi
                // Nota debit mengurangi tagihan, bukan menambah pembayaran
                $tagihanSetelahNotaDebit = $pembelian->grand_total - $notaDebitDigunakanTransaksiIni;
                $sisaTagihan = $tagihanSetelahNotaDebit - $sudahDibayar - $jumlahDilunasi;
                
                // Jika sisa tagihan negatif, berarti ada kelebihan pembayaran
                // Ini bisa terjadi jika ada retur setelah faktur lunas
                if ($sisaTagihan < 0) {
                    $sisaTagihan = 0; // Set ke 0 untuk display
                }

                PembayaranUtangSupplierDetail::create([
                    'pembayaran_utang_supplier_id' => $pembayaran->id,
                    'pembelian_id' => $detail['pembelian_id'],
                    'no_pembelian' => $pembelian->nota,
                    'total_faktur' => $pembelian->grand_total,
                    'sudah_dibayar' => $sudahDibayar,
                    'jumlah_dilunasi' => $jumlahDilunasi,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pelunasan' => $sisaTagihan <= 0 ? 'lunas' : 'sebagian',
                    'keterangan' => 'Pembayaran via ' . $pembayaran->no_pembayaran
                ]);

                // Update pembelian utang status
                PembayaranUtangSupplierDetail::updatePembelianUtangStatus($detail['pembelian_id']);
            }

            // Create nota debit details
            if ($request->nota_debit_details) {
                foreach ($request->nota_debit_details as $detail) {
                    $notaDebit = NotaDebit::find($detail['nota_debit_id']);
                    $jumlahDigunakan = $detail['jumlah_digunakan'];
                    $sisaNotaDebit = $notaDebit->sisa_nota_debit - $jumlahDigunakan;

                    PembayaranUtangSupplierNotaDebit::create([
                        'pembayaran_utang_supplier_id' => $pembayaran->id,
                        'nota_debit_id' => $detail['nota_debit_id'],
                        'no_nota_debit' => $notaDebit->no_nota_debit,
                        'total_nota_debit' => $notaDebit->total_debit,
                        'jumlah_digunakan' => $jumlahDigunakan,
                        'sisa_nota_debit' => $sisaNotaDebit,
                        'keterangan' => 'Digunakan untuk pembayaran ' . $pembayaran->no_pembayaran
                    ]);

                    // Update nota debit sisa
                    $notaDebit->update([
                        'sisa_nota_debit' => $sisaNotaDebit
                    ]);
                }
            }

            // Update Kas if cash payment
            if (strtolower($request->metode_pembayaran) === 'tunai') {
                Kas::create([
                    'name' => "Pembayaran Utang Supplier: {$pembayaran->no_pembayaran}",
                    'description' => "Pembayaran utang ke {$supplier->nama}",
                    'qty' => $request->total_bayar,
                    'type' => 'Kredit', // Kredit karena uang keluar
                    'saldo' => 0,
                    'is_manual' => false,
                ]);

                // Adjust Kas saldo
                $this->adjustKasSaldo();
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran utang supplier berhasil disimpan',
                    'data' => $pembayaran
                ]);
            }

            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('success', 'Pembayaran utang supplier berhasil disimpan');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing pembayaran utang supplier: ' . $e->getMessage());
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menyimpan pembayaran utang supplier'
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan pembayaran utang supplier');
        }
    }

    /**
     * Display the specified pembayaran
     */
    public function show(PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        $pembayaranUtangSupplier->load(['supplier', 'details.pembelian', 'notaDebits.notaDebit', 'createdBy', 'confirmedBy']);
        
        return view('pembayaran_utang_supplier.show', compact('pembayaranUtangSupplier'));
    }

    /**
     * Show the form for editing the specified pembayaran
     */
    public function edit(PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        if ($pembayaranUtangSupplier->status !== 'pending') {
            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('error', 'Pembayaran yang sudah dikonfirmasi tidak dapat diedit');
        }

        $suppliers = Supplier::orderBy('nama')->get();
        $pembayaranUtangSupplier->load(['details', 'notaDebits']);
        
        return view('pembayaran_utang_supplier.edit', compact('pembayaranUtangSupplier', 'suppliers'));
    }

    /**
     * Update the specified pembayaran
     */
    public function update(Request $request, PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        if ($pembayaranUtangSupplier->status !== 'pending') {
            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('error', 'Pembayaran yang sudah dikonfirmasi tidak dapat diedit');
        }

        $request->validate([
            'tanggal_bayar' => 'required|date',
            'total_bayar' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|string',
            'cara_bayar' => 'required|string',
            'no_referensi' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        try {
            $pembayaranUtangSupplier->update([
                'tanggal_bayar' => $request->tanggal_bayar,
                'total_bayar' => $request->total_bayar,
                'metode_pembayaran' => $request->metode_pembayaran,
                'cara_bayar' => $request->cara_bayar,
                'no_referensi' => $request->no_referensi,
                'keterangan' => $request->keterangan,
            ]);

            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('success', 'Pembayaran utang supplier berhasil diperbarui');

        } catch (\Exception $e) {
            Log::error('Error updating pembayaran utang supplier: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memperbarui pembayaran utang supplier');
        }
    }

    /**
     * Remove the specified pembayaran
     */
    public function destroy(PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        if ($pembayaranUtangSupplier->status !== 'pending') {
            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('error', 'Pembayaran yang sudah dikonfirmasi tidak dapat dihapus');
        }

        try {
            $pembayaranUtangSupplier->delete();

            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('success', 'Pembayaran utang supplier berhasil dihapus');

        } catch (\Exception $e) {
            Log::error('Error deleting pembayaran utang supplier: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan saat menghapus pembayaran utang supplier');
        }
    }

    /**
     * Confirm the specified pembayaran
     */
    public function confirm(PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        if ($pembayaranUtangSupplier->status !== 'pending') {
            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('error', 'Pembayaran sudah dikonfirmasi sebelumnya');
        }

        try {
            $pembayaranUtangSupplier->update([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now()
            ]);

            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('success', 'Pembayaran utang supplier berhasil dikonfirmasi');

        } catch (\Exception $e) {
            Log::error('Error confirming pembayaran utang supplier: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan saat mengonfirmasi pembayaran utang supplier');
        }
    }

    /**
     * Cancel the specified pembayaran
     */
    public function cancel(PembayaranUtangSupplier $pembayaranUtangSupplier)
    {
        if ($pembayaranUtangSupplier->status === 'cancelled') {
            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('error', 'Pembayaran sudah dibatalkan sebelumnya');
        }

        try {
            $pembayaranUtangSupplier->update([
                'status' => 'cancelled'
            ]);

            return redirect()->route('pembayaran-utang-supplier.index')
                ->with('success', 'Pembayaran utang supplier berhasil dibatalkan');

        } catch (\Exception $e) {
            Log::error('Error cancelling pembayaran utang supplier: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan saat membatalkan pembayaran utang supplier');
        }
    }

    /**
     * Adjust Kas saldo
     */
    private function adjustKasSaldo(): void
    {
        $lastKas = Kas::orderBy('id', 'desc')->first();
        $lastSaldo = $lastKas ? $lastKas->saldo : 0;

        $kasEntries = Kas::orderBy('id', 'asc')->get();
        $currentSaldo = $lastSaldo;

        foreach ($kasEntries as $kas) {
            if ($kas->type === 'Debit') {
                $currentSaldo += $kas->qty;
            } else {
                $currentSaldo -= $kas->qty;
            }
            
            $kas->update(['saldo' => $currentSaldo]);
        }
    }
}
