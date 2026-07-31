<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranDetail extends Model
{
    use HasFactory;

    protected $table = 'pembayaran_details';

    protected $fillable = [
        'pembayaran_id',
        'transaksi_id',
        'no_transaksi',
        'total_faktur',
        'sudah_dibayar',
        'jumlah_dilunasi',
        'sisa_tagihan',
        'status_pelunasan',
        'keterangan'
    ];

    protected $casts = [
        'total_faktur' => 'decimal:2',
        'sudah_dibayar' => 'decimal:2',
        'jumlah_dilunasi' => 'decimal:2',
        'sisa_tagihan' => 'decimal:2',
    ];

    // Relationships
    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class, 'pembayaran_id');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    // Scopes
    public function scopeLunas($query)
    {
        return $query->where('status_pelunasan', 'lunas');
    }

    public function scopeSebagian($query)
    {
        return $query->where('status_pelunasan', 'sebagian');
    }

    public function scopeBelumDibayar($query)
    {
        return $query->where('status_pelunasan', 'belum_dibayar');
    }

    public function scopeByTransaksi($query, $transaksiId)
    {
        return $query->where('transaksi_id', $transaksiId);
    }

    // Business Logic Methods
    public function isLunas(): bool
    {
        return $this->status_pelunasan === 'lunas';
    }

    public function isSebagian(): bool
    {
        return $this->status_pelunasan === 'sebagian';
    }

    public function isBelumDibayar(): bool
    {
        return $this->status_pelunasan === 'belum_dibayar';
    }

    public function getPersentasePelunasanAttribute(): float
    {
        if ($this->total_faktur == 0) return 0;
        return (($this->sudah_dibayar + $this->jumlah_dilunasi) / $this->total_faktur) * 100;
    }

    public function getTotalSudahDibayarAttribute(): float
    {
        return $this->sudah_dibayar + $this->jumlah_dilunasi;
    }

    public function updateStatusPelunasan(): void
    {
        $totalDibayar = $this->sudah_dibayar + $this->jumlah_dilunasi;
        
        if ($totalDibayar >= $this->total_faktur) {
            $this->update([
                'status_pelunasan' => 'lunas',
                'sisa_tagihan' => 0
            ]);
        } elseif ($totalDibayar > 0) {
            $this->update([
                'status_pelunasan' => 'sebagian',
                'sisa_tagihan' => $this->total_faktur - $totalDibayar
            ]);
        } else {
            $this->update([
                'status_pelunasan' => 'belum_dibayar',
                'sisa_tagihan' => $this->total_faktur
            ]);
        }
    }

    // Static Methods
    public static function getTotalPelunasanByTransaksi($transaksiId): float
    {
        return self::where('transaksi_id', $transaksiId)
            ->whereHas('pembayaran', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->sum('jumlah_dilunasi');
    }

    public static function getStatusPelunasanTransaksi($transaksiId): string
    {
        $totalFaktur = Transaksi::where('id', $transaksiId)->value('grand_total') ?? 0;
        $totalDibayar = self::getTotalPelunasanByTransaksi($transaksiId);

        if ($totalDibayar >= $totalFaktur) {
            return 'lunas';
        } elseif ($totalDibayar > 0) {
            return 'sebagian';
        } else {
            return 'belum_dibayar';
        }
    }

    public static function updateTransaksiPiutangStatus($transaksiId): void
    {
        $transaksi = Transaksi::find($transaksiId);
        if (!$transaksi) return;

        $totalDibayar = self::getTotalPelunasanByTransaksi($transaksiId);
        
        // Hitung total nota kredit yang digunakan untuk transaksi ini
        $totalNotaKreditDigunakan = \App\Models\PembayaranPiutangNotaKredit::whereHas('pembayaran', function($query) use ($transaksiId) {
            $query->where('status', 'confirmed')
                ->whereHas('details', function($q) use ($transaksiId) {
                    $q->where('transaksi_id', $transaksiId);
                });
        })->sum('jumlah_digunakan');

        // Sisa piutang = (grand_total - total_nota_kredit_digunakan) - total_dibayar
        // Nota kredit mengurangi tagihan, bukan menambah pembayaran
        $tagihanSetelahNotaKredit = $transaksi->grand_total - $totalNotaKreditDigunakan;
        $sisaPiutang = $tagihanSetelahNotaKredit - $totalDibayar;
        
        // Jika sisa piutang negatif, berarti ada kelebihan pembayaran
        if ($sisaPiutang < 0) {
            $sisaPiutang = 0; // Set ke 0 untuk display
        }
        
        $statusPiutang = $sisaPiutang <= 0 ? 'lunas' : ($totalDibayar > 0 || $totalNotaKreditDigunakan > 0 ? 'sebagian' : 'belum_dibayar');

        $transaksi->update([
            'total_dibayar' => $totalDibayar,
            'sisa_piutang' => $sisaPiutang,
            'status_piutang' => $statusPiutang,
            'tanggal_pelunasan' => $statusPiutang === 'lunas' ? now() : null
        ]);
    }
}
