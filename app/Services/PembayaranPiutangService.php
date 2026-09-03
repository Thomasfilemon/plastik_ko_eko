<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\PembayaranDetail;
use App\Models\Transaksi;
use App\Models\Customer;
use App\Models\Kas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PembayaranPiutangService
{
    /**
     * Calculate customer's total outstanding balance
     */
    public function hitungTotalPiutangCustomer(int $customerId): array
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Customer tidak ditemukan'
            ];
        }

        $transaksi = Transaksi::where('kode_customer', $customer->kode_customer)
            ->outstandingPiutang()
            ->get();

        $totalPiutang = $transaksi->sum('sisa_piutang');
        $totalJatuhTempo = $transaksi->filter(function($t) { return $t->checkJatuhTempo(); })->sum('sisa_piutang');
        $jumlahFaktur = $transaksi->count();
        $fakturJatuhTempo = $transaksi->filter(function($t) { return $t->checkJatuhTempo(); })->count();

        return [
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'nama' => $customer->nama,
                'limit_kredit' => $customer->limit_kredit,
                'limit_hari_tempo' => $customer->limit_hari_tempo
            ],
            'piutang' => [
                'total_piutang' => $totalPiutang,
                'total_jatuh_tempo' => $totalJatuhTempo,
                'jumlah_faktur' => $jumlahFaktur,
                'faktur_jatuh_tempo' => $fakturJatuhTempo,
                'persentase_jatuh_tempo' => $totalPiutang > 0 ? ($totalJatuhTempo / $totalPiutang) * 100 : 0
            ]
        ];
    }

    /**
     * Get FIFO payment suggestions
     */
    public function getFifoPaymentSuggestion(int $customerId, float $totalBayar): array
    {
        try {
            // Get unpaid invoices ordered by date (FIFO) — skip canceled
            $invoices = Transaksi::where('kode_customer', Customer::find($customerId)->kode_customer)
                ->outstandingPiutang()
                ->orderBy('tanggal', 'asc') // Oldest first
                ->get();

            $suggestions = [];
            $remainingPayment = $totalBayar;
            $totalSuggested = 0;

            foreach ($invoices as $invoice) {
                if ($remainingPayment <= 0) break;

                $sisaTagihan = $invoice->sisa_piutang;
                $suggestedAmount = min($remainingPayment, $sisaTagihan);

                $suggestions[] = [
                    'transaksi_id' => $invoice->id,
                    'no_transaksi' => $invoice->no_transaksi,
                    'tanggal' => $invoice->tanggal->format('d/m/Y'),
                    'tanggal_jatuh_tempo' => $invoice->tanggal_jatuh_tempo ? $invoice->tanggal_jatuh_tempo->format('d/m/Y') : '-',
                    'total_faktur' => $invoice->grand_total,
                    'sudah_dibayar' => $invoice->total_dibayar,
                    'sisa_tagihan' => $sisaTagihan,
                    'suggested_payment' => $suggestedAmount,
                    'is_jatuh_tempo' => $invoice->checkJatuhTempo(),
                    'hari_keterlambatan' => $invoice->hari_keterlambatan,
                    'priority' => $invoice->checkJatuhTempo() ? 'high' : 'normal',
                    'persentase_pelunasan' => (($invoice->total_dibayar + $suggestedAmount) / $invoice->grand_total) * 100
                ];

                $remainingPayment -= $suggestedAmount;
                $totalSuggested += $suggestedAmount;
            }

            return [
                'success' => true,
                'suggestions' => $suggestions,
                'total_suggested' => $totalSuggested,
                'remaining_payment' => $remainingPayment,
                'efficiency' => $totalBayar > 0 ? ($totalSuggested / $totalBayar) * 100 : 0
            ];

        } catch (\Exception $e) {
            Log::error('Error getting FIFO payment suggestion:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process payment with FIFO allocation
     */
    public function processPayment(array $paymentData): array
    {
        DB::beginTransaction();
        try {
            $customer = Customer::find($paymentData['customer_id']);
            
            // Calculate total piutang before payment (exclude canceled)
            $totalPiutang = Transaksi::where('kode_customer', $customer->kode_customer)
                ->outstandingPiutang()
                ->sum('sisa_piutang');

            // Create pembayaran record
            $pembayaran = Pembayaran::create([
                'customer_id' => $paymentData['customer_id'],
                'no_pembayaran' => Pembayaran::generateNoPembayaran(),
                'tanggal_bayar' => $paymentData['tanggal_bayar'],
                'total_bayar' => $paymentData['total_bayar'],
                'total_piutang' => $totalPiutang,
                'sisa_piutang' => $totalPiutang - $paymentData['total_bayar'],
                'metode_pembayaran' => $paymentData['metode_pembayaran'],
                'cara_bayar' => $paymentData['cara_bayar'],
                'no_referensi' => $paymentData['no_referensi'] ?? null,
                'keterangan' => $paymentData['keterangan'] ?? null,
                'status' => 'confirmed',
                'created_by' => auth()->id(),
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now()
            ]);

            $processedInvoices = [];
            $totalProcessed = 0;

            // Process payment details
            foreach ($paymentData['payment_details'] as $detail) {
                $transaksi = Transaksi::find($detail['transaksi_id']);
                $sudahDibayar = $transaksi->total_dibayar ?? 0;
                $jumlahDilunasi = $detail['jumlah_dilunasi'];
                $sisaTagihan = $transaksi->grand_total - $sudahDibayar - $jumlahDilunasi;

                // Create payment detail
                $paymentDetail = PembayaranDetail::create([
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

                $processedInvoices[] = [
                    'no_transaksi' => $transaksi->no_transaksi,
                    'jumlah_dilunasi' => $jumlahDilunasi,
                    'status_pelunasan' => $paymentDetail->status_pelunasan,
                    'sisa_tagihan' => $sisaTagihan
                ];

                $totalProcessed += $jumlahDilunasi;
            }

            // Update Kas if cash payment
            if (strtolower($paymentData['metode_pembayaran']) === 'tunai') {
                Kas::create([
                    'name' => "Pembayaran Piutang: {$pembayaran->no_pembayaran}",
                    'description' => "Pembayaran piutang dari {$customer->nama}",
                    'qty' => $paymentData['total_bayar'],
                    'type' => 'Debit',
                    'saldo' => 0,
                    'is_manual' => false,
                ]);

                $this->adjustKasSaldo();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pembayaran berhasil diproses',
                'pembayaran' => [
                    'id' => $pembayaran->id,
                    'no_pembayaran' => $pembayaran->no_pembayaran,
                    'customer' => $customer->nama,
                    'total_bayar' => $pembayaran->total_bayar,
                    'tanggal_bayar' => $pembayaran->tanggal_bayar->format('d/m/Y')
                ],
                'processed_invoices' => $processedInvoices,
                'summary' => [
                    'total_processed' => $totalProcessed,
                    'invoices_processed' => count($processedInvoices),
                    'efficiency' => $paymentData['total_bayar'] > 0 ? ($totalProcessed / $paymentData['total_bayar']) * 100 : 0
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing payment:', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get comprehensive piutang report
     */
    public function getLaporanPiutang(array $filters = []): array
    {
        try {
            $query = Transaksi::with(['customer'])->notCanceled();

            // Apply filters
            if (isset($filters['start_date']) && isset($filters['end_date'])) {
                $query->byDateRange($filters['start_date'], $filters['end_date']);
            }

            if (isset($filters['customer_id'])) {
                $query->byCustomer($filters['customer_id']);
            }

            if (isset($filters['status_piutang'])) {
                $query->where('status_piutang', $filters['status_piutang']);
            }

            if (isset($filters['jatuh_tempo']) && $filters['jatuh_tempo']) {
                $query->jatuhTempo();
            }

            $transaksi = $query->orderBy('tanggal', 'desc')->get();

            // Calculate summary
            $summary = [
                'total_faktur' => $transaksi->count(),
                'total_nilai_faktur' => $transaksi->sum('grand_total'),
                'total_sudah_dibayar' => $transaksi->sum('total_dibayar'),
                'total_sisa_piutang' => $transaksi->sum('sisa_piutang'),
                'total_lunas' => $transaksi->where('status_piutang', 'lunas')->count(),
                'total_sebagian' => $transaksi->where('status_piutang', 'sebagian')->count(),
                'total_belum_dibayar' => $transaksi->where('status_piutang', 'belum_dibayar')->count(),
                'total_jatuh_tempo' => $transaksi->filter(function($t) { return $t->checkJatuhTempo(); })->count(),
                'total_overdue_amount' => $transaksi->filter(function($t) { return $t->checkJatuhTempo(); })->sum('sisa_piutang'),
                'average_collection_period' => $this->calculateAverageCollectionPeriod($transaksi)
            ];

            // Group by customer
            $customerSummary = $transaksi->groupBy('kode_customer')
                ->map(function ($customerInvoices, $kodeCustomer) {
                    $customer = $customerInvoices->first()->customer;
                    return [
                        'kode_customer' => $kodeCustomer,
                        'nama_customer' => $customer->nama,
                        'total_faktur' => $customerInvoices->count(),
                        'total_nilai_faktur' => $customerInvoices->sum('grand_total'),
                        'total_sudah_dibayar' => $customerInvoices->sum('total_dibayar'),
                        'total_sisa_piutang' => $customerInvoices->sum('sisa_piutang'),
                        'faktur_jatuh_tempo' => $customerInvoices->filter(function($t) { return $t->checkJatuhTempo(); })->count(),
                        'overdue_amount' => $customerInvoices->filter(function($t) { return $t->checkJatuhTempo(); })->sum('sisa_piutang'),
                        'collection_efficiency' => $customerInvoices->sum('grand_total') > 0 ? 
                            ($customerInvoices->sum('total_dibayar') / $customerInvoices->sum('grand_total')) * 100 : 0
                    ];
                })
                ->values();

            return [
                'success' => true,
                'transaksi' => $transaksi,
                'summary' => $summary,
                'customer_summary' => $customerSummary
            ];

        } catch (\Exception $e) {
            Log::error('Error getting laporan piutang:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate average collection period
     */
    private function calculateAverageCollectionPeriod($transaksi): float
    {
        $lunasInvoices = $transaksi->where('status_piutang', 'lunas');
        
        if ($lunasInvoices->count() === 0) return 0;

        $totalDays = $lunasInvoices->sum(function ($invoice) {
            if (!$invoice->tanggal_pelunasan || !$invoice->tanggal) return 0;
            return Carbon::parse($invoice->tanggal)->diffInDays($invoice->tanggal_pelunasan);
        });

        return $totalDays / $lunasInvoices->count();
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

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(): array
    {
        try {
            $today = now();
            $thisMonth = now()->startOfMonth();
            $lastMonth = now()->subMonth()->startOfMonth();

            $statistics = [
                'today' => [
                    'total_pembayaran' => Pembayaran::getTotalPembayaranHariIni(),
                    'jumlah_transaksi' => Pembayaran::whereDate('tanggal_bayar', $today)->count(),
                    'rata_rata_pembayaran' => Pembayaran::whereDate('tanggal_bayar', $today)->avg('total_bayar') ?? 0
                ],
                'this_month' => [
                    'total_pembayaran' => Pembayaran::getTotalPembayaranBulanIni(),
                    'jumlah_transaksi' => Pembayaran::whereYear('tanggal_bayar', $thisMonth->year)
                        ->whereMonth('tanggal_bayar', $thisMonth->month)->count(),
                    'rata_rata_pembayaran' => Pembayaran::whereYear('tanggal_bayar', $thisMonth->year)
                        ->whereMonth('tanggal_bayar', $thisMonth->month)->avg('total_bayar') ?? 0
                ],
                'last_month' => [
                    'total_pembayaran' => Pembayaran::whereYear('tanggal_bayar', $lastMonth->year)
                        ->whereMonth('tanggal_bayar', $lastMonth->month)->sum('total_bayar'),
                    'jumlah_transaksi' => Pembayaran::whereYear('tanggal_bayar', $lastMonth->year)
                        ->whereMonth('tanggal_bayar', $lastMonth->month)->count(),
                    'rata_rata_pembayaran' => Pembayaran::whereYear('tanggal_bayar', $lastMonth->year)
                        ->whereMonth('tanggal_bayar', $lastMonth->month)->avg('total_bayar') ?? 0
                ]
            ];

            // Calculate month-over-month growth
            $statistics['growth'] = [
                'total_pembayaran' => $statistics['last_month']['total_pembayaran'] > 0 ? 
                    (($statistics['this_month']['total_pembayaran'] - $statistics['last_month']['total_pembayaran']) / $statistics['last_month']['total_pembayaran']) * 100 : 0,
                'jumlah_transaksi' => $statistics['last_month']['jumlah_transaksi'] > 0 ? 
                    (($statistics['this_month']['jumlah_transaksi'] - $statistics['last_month']['jumlah_transaksi']) / $statistics['last_month']['jumlah_transaksi']) * 100 : 0
            ];

            return [
                'success' => true,
                'statistics' => $statistics
            ];

        } catch (\Exception $e) {
            Log::error('Error getting payment statistics:', ['message' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }
    }
}
