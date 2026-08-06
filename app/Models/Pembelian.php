<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pembelian';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'nota',
    'tanggal',
    'kode_supplier',
    'pembayaran',
    'cara_bayar',
    'hari_tempo',
    'tanggal_jatuh_tempo',
    'subtotal',
    'diskon',
    'ppn',
    'grand_total',
    'total_dibayar',
    'sisa_utang',
    'status_utang',
    'status',
    'canceled_by',
    'canceled_at',
    'cancel_reason',
     'is_edited', 
    'edited_by', 
    'edited_at', 
    'edit_reason',
    'no_surat_jalan',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal' => 'date',
        'tanggal_jatuh_tempo' => 'date',
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ppn' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_dibayar' => 'decimal:2',
        'sisa_utang' => 'decimal:2',
    ];

    /**
     * Get the supplier associated with the purchase.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'kode_supplier', 'kode_supplier');
    }

    public function supplierRelation()
    {
        return $this->belongsTo(Supplier::class, 'kode_supplier', 'kode_supplier');
    }
    public function stokOwner()
    {
        return $this->belongsTo(StokOwner::class, 'cabang', 'kode_stok_owner');
    }

    /**
     * Get the items for the purchase.
     */
    public function items()
    {
        return $this->hasMany(PembelianItem::class, 'nota', 'nota');
    }

    public function suppliers()
    {
        return $this->hasMany(supplier::class, 'kode_su', 'nota');
    }

    /**
     * Get the payment details for this purchase.
     */
    public function pembayaranUtangSupplierDetails()
    {
        return $this->hasMany(PembayaranUtangSupplierDetail::class, 'pembelian_id');
    }

    // Scopes for payment status (exclude cancelled purchases)
    public function scopeBelumDibayar($query)
    {
        return $query->where('status', '!=', 'canceled')
            ->whereIn('status_utang', ['belum_dibayar', 'sebagian']);
    }

    public function scopeJatuhTempo($query)
    {
        return $query->where('status', '!=', 'canceled')
            ->where('tanggal', '<=', now()->subDays(30));
    }

    // Boot method untuk auto-calculate sisa_utang
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pembelian) {
            // Set default values untuk pembelian baru
            $pembelian->total_dibayar = $pembelian->total_dibayar ?: 0;
            $pembelian->sisa_utang = $pembelian->grand_total - $pembelian->total_dibayar;
            $pembelian->status_utang = $pembelian->status_utang ?: 'belum_dibayar';
        });

        static::updating(function ($pembelian) {
            // Update sisa_utang jika grand_total atau total_dibayar berubah
            if ($pembelian->isDirty(['grand_total', 'total_dibayar'])) {
                $pembelian->sisa_utang = $pembelian->grand_total - $pembelian->total_dibayar;
                
                // Update status_utang berdasarkan sisa_utang
                if ($pembelian->sisa_utang <= 0) {
                    $pembelian->status_utang = 'lunas';
                } elseif ($pembelian->total_dibayar > 0) {
                    $pembelian->status_utang = 'sebagian';
                } else {
                    $pembelian->status_utang = 'belum_dibayar';
                }
            }
        });
    }
}