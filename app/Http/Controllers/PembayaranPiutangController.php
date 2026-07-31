<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pembayaran;
use App\Models\PembayaranDetail;
use App\Models\PembayaranPiutangNotaKredit;
use App\Models\Transaksi;
use App\Models\Customer;
use App\Models\Kas;
use App\Models\NotaKredit;
use Carbon\Carbon;

class PembayaranPiutangController extends Controller
{
    /**
     * Display a listing of pembayaran piutang
     */
    public function index()
    {
        $pembayarans = Pembayaran::with(['customer', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $summary = [
            'total_pembayaran_hari_ini' => Pembayaran::getTotalPembayaranHariIni(),
            'total_pembayaran_bulan_ini' => Pembayaran::getTotalPembayaranBulanIni(),
            'total_piutang_tertagih' => Transaksi::belumDibayar()->sum('sisa_piutang'),
            'total_piutang_jatuh_tempo' => Transaksi::jatuhTempo()->sum('sisa_piutang')
        ];

        return view('pembayaran_piutang.index', compact('pembayarans', 'summary'));
    }

    /**
     * Show the form for creating a new pembayaran
     */
    public function create()
    {
        $customers = Customer::orderBy('nama')->get();
        $noPembayaran = Pembayaran::generateNoPembayaran();
        
        return view('pembayaran_piutang.create', compact('customers', 'noPembayaran'));
    }

    /**
     * Get customer's available nota kredit for payment
     */
    public function getCustomerNotaKredit(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id'
        ]);

        $customer = Customer::find($request->customer_id);
        
        $notaKredit = NotaKredit::where('kode_customer', $customer->kode_customer)
            ->where('status', 'approved')
            ->where('sisa_kredit', '>', 0)
            ->orderBy('tanggal', 'desc')
            ->get()
            ->map(function($nk) {
                return [
                    'id' => $nk->id,
                    'no_nota_kredit' => $nk->no_nota_kredit,
                    'tanggal' => $nk->tanggal->format('d/m/Y'),
                    'total_kredit' => $nk->total_kredit,
                    'sisa_kredit' => $nk->sisa_kredit,
                    'keterangan' => $nk->keterangan
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $notaKredit
        ]);
    }

    /**
     * Get customer's unpaid invoices for payment
     */
    public function getCustomerInvoices(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id'
        ]);

        try {
            $customerId = $request->customer_id;
            $customer = Customer::find($customerId);
            $excludePembayaranId = $request->exclude_pembayaran_id;

            // Amounts from this payment to "add back" into available sisa when editing
            $amountsByTransaksi = collect();
            if ($excludePembayaranId) {
                $amountsByTransaksi = PembayaranDetail::where('pembayaran_id', $excludePembayaranId)
                    ->get()
                    ->keyBy('transaksi_id');
            }

            // Get unpaid invoices, plus invoices already in the payment being edited
            $invoices = Transaksi::where('kode_customer', $customer->kode_customer)
                ->where(function ($q) use ($excludePembayaranId) {
                    $q->whereIn('status_piutang', ['belum_dibayar', 'sebagian']);
                    if ($excludePembayaranId) {
                        $q->orWhereHas('pembayaranDetails', function ($d) use ($excludePembayaranId) {
                            $d->where('pembayaran_id', $excludePembayaranId);
                        });
                    }
                })
                ->orderBy('tanggal', 'asc') // FIFO - oldest first
                ->get()
                ->map(function ($invoice) use ($amountsByTransaksi) {
                    // Hitung total retur penjualan yang sudah approved untuk transaksi ini
                    $totalReturApproved = \App\Models\ReturPenjualan::where('transaksi_id', $invoice->id)
                        ->whereIn('status', ['approved', 'processed'])
                        ->sum('total_retur');

                    $dilunasiByThis = (float) optional($amountsByTransaksi->get($invoice->id))->jumlah_dilunasi;
                    $sudahDibayarAdjusted = max(0, ($invoice->total_dibayar ?? 0) - $dilunasiByThis);

                    // Sisa piutang setelah dikurangi retur, treating excluded payment as not applied
                    $tagihanSetelahRetur = $invoice->grand_total - $totalReturApproved;
                    $sisaPiutangSetelahRetur = $tagihanSetelahRetur - $sudahDibayarAdjusted;
                    
                    // Pastikan tidak negatif
                    if ($sisaPiutangSetelahRetur < 0) {
                        $sisaPiutangSetelahRetur = 0;
                    }

                    return [
                        'id' => $invoice->id,
                        'no_transaksi' => $invoice->no_transaksi,
                        'tanggal' => $invoice->tanggal->format('d/m/Y'),
                        'tanggal_jatuh_tempo' => $invoice->tanggal_jatuh_tempo ? $invoice->tanggal_jatuh_tempo->format('d/m/Y') : '-',
                        'total_faktur' => $invoice->grand_total,
                        'sudah_dibayar' => $sudahDibayarAdjusted,
                        'total_retur' => $totalReturApproved,
                        'sisa_tagihan' => $sisaPiutangSetelahRetur,
                        'sisa_tagihan_original' => $invoice->sisa_piutang,
                        'status_piutang' => $invoice->status_piutang,
                        'is_jatuh_tempo' => $invoice->checkJatuhTempo(),
                        'hari_keterlambatan' => $invoice->hari_keterlambatan,
                        'suggested_payment' => $dilunasiByThis > 0 ? $dilunasiByThis : $sisaPiutangSetelahRetur,
                        'jumlah_dilunasi_saat_ini' => $dilunasiByThis,
                    ];
                });

            // Get nota kredit dari retur penjualan yang belum digunakan
            $notaKredit = \App\Models\NotaKredit::where('kode_customer', $customer->kode_customer)
                ->where('status', 'approved')
                ->where('sisa_kredit', '>', 0)
                ->orderBy('tanggal', 'asc')
                ->get()
                ->map(function ($nota) {
                    return [
                        'id' => $nota->id,
                        'no_nota_kredit' => $nota->no_nota_kredit,
                        'tanggal' => $nota->tanggal->format('d/m/Y'),
                        'total_kredit' => $nota->total_kredit,
                        'sisa_kredit' => $nota->sisa_kredit,
                        'keterangan' => $nota->keterangan,
                        'type' => 'nota_kredit'
                    ];
                });

            $totalPiutang = $invoices->sum('sisa_tagihan');
            $totalJatuhTempo = $invoices->where('is_jatuh_tempo', true)->sum('sisa_tagihan');

            return response()->json([
                'success' => true,
                'customer' => [
                    'nama' => $customer->nama,
                    'limit_kredit' => $customer->limit_kredit,
                    'limit_hari_tempo' => $customer->limit_hari_tempo
                ],
                'invoices' => $invoices,
                'nota_kredit' => $notaKredit,
                'summary' => [
                    'total_invoices' => $invoices->count(),
                    'total_piutang' => $totalPiutang,
                    'total_jatuh_tempo' => $totalJatuhTempo,
                    'invoices_jatuh_tempo' => $invoices->where('is_jatuh_tempo', true)->count(),
                    'total_nota_kredit' => $notaKredit->sum('sisa_kredit')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting customer invoices:', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get auto-suggestion for payment allocation
     */
    public function getPaymentSuggestion(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'total_bayar' => 'required|numeric|min:0.01'
        ]);

        try {
            $customerId = $request->customer_id;
            $totalBayar = $request->total_bayar;

            // Get unpaid invoices ordered by date (FIFO)
            $invoices = Transaksi::where('kode_customer', Customer::find($customerId)->kode_customer)
                ->whereIn('status_piutang', ['belum_dibayar', 'sebagian'])
                ->orderBy('tanggal', 'asc')
                ->get();

            $suggestions = [];
            $remainingPayment = $totalBayar;

            foreach ($invoices as $invoice) {
                if ($remainingPayment <= 0) break;

                // Hitung total retur penjualan yang sudah approved untuk transaksi ini
                $totalReturApproved = \App\Models\ReturPenjualan::where('transaksi_id', $invoice->id)
                    ->whereIn('status', ['approved', 'processed'])
                    ->sum('total_retur');

                // Sisa piutang setelah dikurangi retur
                $tagihanSetelahRetur = $invoice->grand_total - $totalReturApproved;
                $sisaTagihan = $tagihanSetelahRetur - $invoice->total_dibayar;
                
                // Pastikan tidak negatif
                if ($sisaTagihan < 0) {
                    $sisaTagihan = 0;
                }
                
                $suggestedAmount = min($remainingPayment, $sisaTagihan);

                $suggestions[] = [
                    'transaksi_id' => $invoice->id,
                    'no_transaksi' => $invoice->no_transaksi,
                    'tanggal' => $invoice->tanggal->format('d/m/Y'),
                    'total_faktur' => $invoice->grand_total,
                    'sudah_dibayar' => $invoice->total_dibayar,
                    'total_retur' => $totalReturApproved,
                    'sisa_tagihan' => $sisaTagihan,
                    'suggested_payment' => $suggestedAmount,
                    'is_jatuh_tempo' => $invoice->checkJatuhTempo(),
                    'priority' => $invoice->checkJatuhTempo() ? 'high' : 'normal'
                ];

                $remainingPayment -= $suggestedAmount;
            }

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
                'total_suggested' => $totalBayar - $remainingPayment,
                'remaining_payment' => $remainingPayment
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment suggestion:', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created pembayaran
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tanggal_bayar' => 'required|date',
            'total_bayar' => 'required|numeric|min:0.01',
            'metode_pembayaran' => 'required|string',
            'cara_bayar' => 'required|string',
            'no_referensi' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'payment_details' => 'required|array|min:1',
            'payment_details.*.transaksi_id' => 'required|exists:transaksi,id',
            'payment_details.*.jumlah_dilunasi' => 'required|numeric|min:0.01'
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::find($request->customer_id);
            
            // Calculate total piutang before payment
            $totalPiutang = Transaksi::where('kode_customer', $customer->kode_customer)
                ->whereIn('status_piutang', ['belum_dibayar', 'sebagian'])
                ->sum('sisa_piutang');

            // Create pembayaran record
            $pembayaran = Pembayaran::create([
                'customer_id' => $request->customer_id,
                'no_pembayaran' => Pembayaran::generateNoPembayaran(),
                'tanggal_bayar' => $request->tanggal_bayar,
                'total_bayar' => $request->total_bayar,
                'total_piutang' => $totalPiutang,
                'sisa_piutang' => $totalPiutang - $request->total_bayar,
                'metode_pembayaran' => $request->metode_pembayaran,
                'cara_bayar' => $request->cara_bayar,
                'no_referensi' => $request->no_referensi,
                'keterangan' => $request->keterangan,
                'status' => 'confirmed', // Auto confirm for now
                'created_by' => auth()->id(),
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now()
            ]);

            // Create payment details
            foreach ($request->payment_details as $detail) {
                $transaksi = Transaksi::find($detail['transaksi_id']);
                $sudahDibayar = $transaksi->total_dibayar ?? 0;
                $jumlahDilunasi = $detail['jumlah_dilunasi'];
                
                // Hitung total nota kredit yang digunakan untuk transaksi ini dalam pembayaran ini
                $notaKreditDigunakanTransaksiIni = 0;
                if ($request->nota_kredit_details) {
                    $notaKreditDigunakanTransaksiIni = collect($request->nota_kredit_details)->sum('jumlah_digunakan');
                }
                
                // Sisa tagihan = (grand_total - nota_kredit_digunakan) - sudah_dibayar - jumlah_dilunasi
                // Nota kredit mengurangi tagihan, bukan menambah pembayaran
                $tagihanSetelahNotaKredit = $transaksi->grand_total - $notaKreditDigunakanTransaksiIni;
                $sisaTagihan = $tagihanSetelahNotaKredit - $sudahDibayar - $jumlahDilunasi;
                
                // Jika sisa tagihan negatif, berarti ada kelebihan pembayaran
                // Ini bisa terjadi jika ada retur setelah faktur lunas
                if ($sisaTagihan < 0) {
                    $sisaTagihan = 0; // Set ke 0 untuk display
                }

                PembayaranDetail::create([
                    'pembayaran_id' => $pembayaran->id,
                    'transaksi_id' => $detail['transaksi_id'],
                    'no_transaksi' => $transaksi->no_transaksi,
                    'total_faktur' => $transaksi->grand_total,
                    'sudah_dibayar' => $sudahDibayar,
                    'jumlah_dilunasi' => $jumlahDilunasi,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pelunasan' => $sisaTagihan <= 0 ? 'lunas' : 'sebagian',
                    'keterangan' => 'Pembayaran via ' . $pembayaran->no_pembayaran
                ]);

                // Update transaksi piutang status
                PembayaranDetail::updateTransaksiPiutangStatus($detail['transaksi_id']);
            }

            // Create nota kredit details
            if ($request->nota_kredit_details) {
                foreach ($request->nota_kredit_details as $detail) {
                    $notaKredit = NotaKredit::find($detail['nota_kredit_id']);
                    $jumlahDigunakan = $detail['jumlah_digunakan'];
                    $sisaNotaKredit = $notaKredit->sisa_kredit - $jumlahDigunakan;

                    PembayaranPiutangNotaKredit::create([
                        'pembayaran_id' => $pembayaran->id,
                        'nota_kredit_id' => $detail['nota_kredit_id'],
                        'no_nota_kredit' => $notaKredit->no_nota_kredit,
                        'total_nota_kredit' => $notaKredit->total_kredit,
                        'jumlah_digunakan' => $jumlahDigunakan,
                        'sisa_nota_kredit' => $sisaNotaKredit,
                        'keterangan' => 'Digunakan untuk pembayaran ' . $pembayaran->no_pembayaran
                    ]);

                    // Update nota kredit sisa
                    $notaKredit->update([
                        'sisa_kredit' => $sisaNotaKredit,
                        'status' => $sisaNotaKredit <= 0 ? 'processed' : 'approved'
                    ]);
                }
            }

            // Update Kas if cash payment
            if (strtolower($request->metode_pembayaran) === 'tunai') {
                Kas::create([
                    'name' => "Pembayaran Piutang: {$pembayaran->no_pembayaran}",
                    'description' => "Pembayaran piutang dari {$customer->nama}",
                    'qty' => $request->total_bayar,
                    'type' => 'Debit',
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
                    'message' => 'Pembayaran berhasil disimpan',
                    'pembayaran' => [
                        'id' => $pembayaran->id,
                        'no_pembayaran' => $pembayaran->no_pembayaran,
                        'customer' => $customer->nama,
                        'total_bayar' => $pembayaran->total_bayar,
                        'tanggal_bayar' => $pembayaran->tanggal_bayar->format('d/m/Y')
                    ]
                ]);
            }

            return redirect()
                ->route('pembayaran-piutang.show', $pembayaran->id)
                ->with('success', 'Pembayaran berhasil disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storing pembayaran:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified pembayaran
     */
    public function show(Pembayaran $pembayaran)
    {
        $pembayaran->load(['customer', 'details.transaksi', 'createdBy', 'confirmedBy']);
        
        return view('pembayaran_piutang.show', compact('pembayaran'));
    }

    /**
     * Show the form for editing the specified pembayaran
     */
    public function edit(Pembayaran $pembayaran)
    {
        if ($pembayaran->isCancelled()) {
            return redirect()->route('pembayaran-piutang.show', $pembayaran->id)
                ->with('error', 'Pembayaran yang sudah dibatalkan tidak dapat diedit.');
        }

        $pembayaran->load(['customer', 'details.transaksi', 'notaKreditDetails']);
        $customers = Customer::orderBy('nama')->get();
        
        return view('pembayaran_piutang.edit', compact('pembayaran', 'customers'));
    }

    /**
     * Update the specified pembayaran (including confirmed ones).
     * Reverses old allocations, then reapplies new payment details.
     */
    public function update(Request $request, Pembayaran $pembayaran)
    {
        if ($pembayaran->isCancelled()) {
            $message = 'Pembayaran yang sudah dibatalkan tidak dapat diedit';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 400);
            }
            return redirect()->back()->with('error', $message);
        }

        $request->validate([
            'tanggal_bayar' => 'required|date',
            'total_bayar' => 'required|numeric|min:0.01',
            'metode_pembayaran' => 'required|string',
            'cara_bayar' => 'required|string',
            'no_referensi' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'payment_details' => 'required|array|min:1',
            'payment_details.*.transaksi_id' => 'required|exists:transaksi,id',
            'payment_details.*.jumlah_dilunasi' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $pembayaran->load(['details', 'notaKreditDetails', 'customer']);
            $customer = $pembayaran->customer;
            $affectedTransaksiIds = $pembayaran->details->pluck('transaksi_id')->unique()->values()->all();

            // Reverse nota kredit usage
            foreach ($pembayaran->notaKreditDetails as $nkDetail) {
                $notaKredit = NotaKredit::find($nkDetail->nota_kredit_id);
                if ($notaKredit) {
                    $notaKredit->sisa_kredit += $nkDetail->jumlah_digunakan;
                    if ($notaKredit->status === 'processed') {
                        $notaKredit->status = 'approved';
                    }
                    $notaKredit->save();
                }
                $nkDetail->delete();
            }

            // Reverse Kas entry for previous tunai payment
            if (strtolower((string) $pembayaran->metode_pembayaran) === 'tunai') {
                Kas::create([
                    'name' => "Revisi Pembayaran Piutang: {$pembayaran->no_pembayaran}",
                    'description' => "Pembalikan pembayaran piutang (edit) dari {$customer->nama}",
                    'qty' => $pembayaran->total_bayar,
                    'type' => 'Kredit',
                    'saldo' => 0,
                    'is_manual' => false,
                ]);
            }

            // Remove old payment details
            $pembayaran->details()->delete();

            $pembayaran->update([
                'tanggal_bayar' => $request->tanggal_bayar,
                'total_bayar' => $request->total_bayar,
                'metode_pembayaran' => $request->metode_pembayaran,
                'cara_bayar' => $request->cara_bayar,
                'no_referensi' => $request->no_referensi,
                'keterangan' => $request->keterangan,
                'status' => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => $pembayaran->confirmed_at ?? now(),
            ]);

            // Recalc old invoices first (after details deleted, confirmed payment no longer counts)
            foreach ($affectedTransaksiIds as $transaksiId) {
                PembayaranDetail::updateTransaksiPiutangStatus($transaksiId);
            }

            // Create new payment details
            foreach ($request->payment_details as $detail) {
                $transaksi = Transaksi::find($detail['transaksi_id']);
                $sudahDibayar = $transaksi->total_dibayar ?? 0;
                $jumlahDilunasi = $detail['jumlah_dilunasi'];
                $sisaTagihan = max(0, ($transaksi->grand_total - $sudahDibayar - $jumlahDilunasi));

                PembayaranDetail::create([
                    'pembayaran_id' => $pembayaran->id,
                    'transaksi_id' => $detail['transaksi_id'],
                    'no_transaksi' => $transaksi->no_transaksi,
                    'total_faktur' => $transaksi->grand_total,
                    'sudah_dibayar' => $sudahDibayar,
                    'jumlah_dilunasi' => $jumlahDilunasi,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pelunasan' => $sisaTagihan <= 0 ? 'lunas' : 'sebagian',
                    'keterangan' => 'Pembayaran via ' . $pembayaran->no_pembayaran . ' (diedit)'
                ]);

                PembayaranDetail::updateTransaksiPiutangStatus($detail['transaksi_id']);
                $affectedTransaksiIds[] = $detail['transaksi_id'];
            }

            // Optional nota kredit on edit
            if ($request->nota_kredit_details) {
                foreach ($request->nota_kredit_details as $detail) {
                    $notaKredit = NotaKredit::find($detail['nota_kredit_id']);
                    $jumlahDigunakan = $detail['jumlah_digunakan'];
                    $sisaNotaKredit = $notaKredit->sisa_kredit - $jumlahDigunakan;

                    PembayaranPiutangNotaKredit::create([
                        'pembayaran_id' => $pembayaran->id,
                        'nota_kredit_id' => $detail['nota_kredit_id'],
                        'no_nota_kredit' => $notaKredit->no_nota_kredit,
                        'total_nota_kredit' => $notaKredit->total_kredit,
                        'jumlah_digunakan' => $jumlahDigunakan,
                        'sisa_nota_kredit' => $sisaNotaKredit,
                        'keterangan' => 'Digunakan untuk pembayaran ' . $pembayaran->no_pembayaran
                    ]);

                    $notaKredit->update([
                        'sisa_kredit' => $sisaNotaKredit,
                        'status' => $sisaNotaKredit <= 0 ? 'processed' : 'approved'
                    ]);
                }
            }

            // Refresh header piutang snapshot
            $totalPiutangCustomer = Transaksi::where('kode_customer', $customer->kode_customer)
                ->whereIn('status_piutang', ['belum_dibayar', 'sebagian'])
                ->sum('sisa_piutang');

            $pembayaran->update([
                'total_piutang' => $totalPiutangCustomer + $request->total_bayar,
                'sisa_piutang' => $totalPiutangCustomer,
            ]);

            if (strtolower($request->metode_pembayaran) === 'tunai') {
                Kas::create([
                    'name' => "Pembayaran Piutang: {$pembayaran->no_pembayaran}",
                    'description' => "Pembayaran piutang (hasil edit) dari {$customer->nama}",
                    'qty' => $request->total_bayar,
                    'type' => 'Debit',
                    'saldo' => 0,
                    'is_manual' => false,
                ]);
            }

            $this->adjustKasSaldo();

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran berhasil diupdate'
                ]);
            }

            return redirect()
                ->route('pembayaran-piutang.show', $pembayaran->id)
                ->with('success', 'Pembayaran berhasil diupdate.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating pembayaran:', ['message' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified pembayaran
     */
    public function destroy(Pembayaran $pembayaran): JsonResponse
    {
        if ($pembayaran->isConfirmed()) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran yang sudah dikonfirmasi tidak dapat dihapus. Batalkan pembayaran terlebih dahulu.'
            ], 400);
        }

        try {
            // Restore transaksi piutang status
            foreach ($pembayaran->details as $detail) {
                PembayaranDetail::updateTransaksiPiutangStatus($detail->transaksi_id);
            }

            $pembayaran->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting pembayaran:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm pembayaran
     */
    public function confirm(Pembayaran $pembayaran): JsonResponse
    {
        try {
            $pembayaran->confirm(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dikonfirmasi'
            ]);

        } catch (\Exception $e) {
            Log::error('Error confirming pembayaran:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel pembayaran and restore piutang / nota kredit / kas
     */
    public function cancel(Pembayaran $pembayaran): JsonResponse
    {
        if ($pembayaran->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran sudah dibatalkan sebelumnya'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $pembayaran->load(['details', 'notaKreditDetails', 'customer']);
            $customer = $pembayaran->customer;

            // Restore nota kredit
            foreach ($pembayaran->notaKreditDetails as $nkDetail) {
                $notaKredit = NotaKredit::find($nkDetail->nota_kredit_id);
                if ($notaKredit) {
                    $notaKredit->sisa_kredit += $nkDetail->jumlah_digunakan;
                    if ($notaKredit->status === 'processed') {
                        $notaKredit->status = 'approved';
                    }
                    $notaKredit->save();
                }
            }

            // Reverse Kas for tunai
            if (strtolower((string) $pembayaran->metode_pembayaran) === 'tunai') {
                Kas::create([
                    'name' => "Batal Pembayaran Piutang: {$pembayaran->no_pembayaran}",
                    'description' => "Pembatalan pembayaran piutang dari " . ($customer->nama ?? '-'),
                    'qty' => $pembayaran->total_bayar,
                    'type' => 'Kredit',
                    'saldo' => 0,
                    'is_manual' => false,
                ]);
                $this->adjustKasSaldo();
            }

            $pembayaran->cancel(auth()->id());

            // Restore transaksi piutang status (confirmed-only totals will exclude this payment)
            foreach ($pembayaran->details as $detail) {
                PembayaranDetail::updateTransaksiPiutangStatus($detail->transaksi_id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dibatalkan'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling pembayaran:', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get laporan piutang
     */
    public function laporanPiutang(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        $kodeCustomer = $request->get('kode_customer');
        $statusPiutang = $request->get('status_piutang');
    
        $query = Transaksi::with(['customer'])
            ->byDateRange($startDate, $endDate);
    
        // Filter berdasarkan kode_customer
        if ($kodeCustomer) {
            $query->where('kode_customer', $kodeCustomer);
        }
    
        // Filter status piutang
        if ($statusPiutang) {
            $query->where('status_piutang', $statusPiutang);
        }
    
        $transaksi = $query->orderBy('tanggal', 'desc')->get();
    
        $summary = [
            'total_faktur' => $transaksi->count(),
            'total_nilai_faktur' => $transaksi->sum('grand_total'),
            'total_sudah_dibayar' => $transaksi->sum('total_dibayar'),
            'total_sisa_piutang' => $transaksi->sum('sisa_piutang'),
            'total_lunas' => $transaksi->where('status_piutang', 'lunas')->count(),
            'total_sebagian' => $transaksi->where('status_piutang', 'sebagian')->count(),
            'total_belum_dibayar' => $transaksi->where('status_piutang', 'belum_dibayar')->count(),
            'total_jatuh_tempo' => $transaksi->filter(fn($t) => $t->checkJatuhTempo())->count()
        ];
    
        // Ambil daftar customer untuk filter
        $customers = \App\Models\Customer::all();
    
        return view('pembayaran_piutang.laporan', compact(
            'transaksi', 'summary', 'startDate', 'endDate', 'customers', 'kodeCustomer', 'statusPiutang'
        ));
    }


    /**
     * Adjust Kas saldo
     */
    private function adjustKasSaldo(): void
    {
        $kas = Kas::orderBy('created_at', 'desc')->first();
        if ($kas) {
            $saldo = Kas::sum(DB::raw('CASE WHEN type = "Debit" THEN qty ELSE -qty END'));
            $kas->update(['saldo' => $saldo]);
        }
    }
}
