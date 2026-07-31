<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\StokOwner;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksi';
    
    protected $fillable = [
        'no_transaksi',
        'tanggal',
        'kode_customer',
        'sales_order_id',
        'sales',
        'pembayaran',
        'cara_bayar',
        'tanggal_jadi',
        'hari_tempo',
        'subtotal',
        'discount',
        'disc_rupiah',
        'ppn',
        'dp',
        'grand_total',
        'status',
        'status_piutang',
        'total_dibayar',
        'sisa_piutang',
        'tanggal_jatuh_tempo',
        'tanggal_pelunasan',
        'created_from_po',
        'is_edited',
        'edited_by',
        'edited_at',
        'edit_reason',
        'notes',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'tanggal_jadi' => 'datetime',
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_pelunasan' => 'date',
        'hari_tempo' => 'integer',
        'total_dibayar' => 'decimal:2',
        'sisa_piutang' => 'decimal:2',
        'edited_at' => 'datetime',
        'is_edited' => 'boolean',
    ];

    public static function generateNoTransaksi(): string
    {
        $prefix = 'TRX-';
        $date = now()->format('Ymd');

        // Ambil transaksi terakhir hari ini
        $lastTransaksi = self::whereDate('tanggal', now()->toDateString())
            ->orderBy('no_transaksi', 'desc')
            ->first();

        if ($lastTransaksi) {
            // ambil nomor urut terakhir
            $lastNumber = (int) substr($lastTransaksi->no_transaksi, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $prefix . $date . '-' . $newNumber;
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransaksiItem::class, 'transaksi_id');
    }

    // public function customer()
    // {
    //     return $this->belongsTo(Customer::class, 'cutomer_id', 'id');
    // }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'kode_customer', 'kode_customer');
    }

    /**
     * Relasi ke SalesOrder
     */
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * Relasi ke salesman (stok_owners) menggunakan kolom kode
     */
    public function salesman()
    {
        return $this->belongsTo(StokOwner::class, 'sales', 'kode_stok_owner');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function pembayaranDetails()
    {
        return $this->hasMany(PembayaranDetail::class, 'transaksi_id');
    }

    // Business Logic Methods
    public function isLunas(): bool
    {
        return $this->status_piutang === 'lunas';
    }

    public function isSebagian(): bool
    {
        return $this->status_piutang === 'sebagian';
    }

    public function isBelumDibayar(): bool
    {
        return $this->status_piutang === 'belum_dibayar';
    }

    public function getPersentasePelunasanAttribute(): float
    {
        if ($this->grand_total == 0) return 0;
        return ($this->total_dibayar / $this->grand_total) * 100;
    }

    public function getSisaPiutangAttribute(): float
    {
        return $this->grand_total - $this->total_dibayar;
    }

    public function checkJatuhTempo(): bool
    {
        if (!$this->tanggal_jatuh_tempo) return false;
        return now()->isAfter($this->tanggal_jatuh_tempo);
    }

    public function getHariKeterlambatanAttribute(): int
    {
        if (!$this->tanggal_jatuh_tempo || !$this->checkJatuhTempo()) return 0;
        return now()->diffInDays($this->tanggal_jatuh_tempo);
    }

    /**
     * Accessor untuk menampilkan nama salesman langsung dari model
     */
    public function getNamaSalesmanAttribute(): string
    {
        return optional($this->salesman)->keterangan ?? '-';
    }

    // Scopes
    public function scopeLunas($query)
    {
        return $query->where('status_piutang', 'lunas');
    }

    public function scopeSebagian($query)
    {
        return $query->where('status_piutang', 'sebagian');
    }

    public function scopeBelumDibayar($query)
    {
        return $query->where('status_piutang', 'belum_dibayar');
    }

    public function scopeJatuhTempo($query)
    {
        return $query->where('tanggal_jatuh_tempo', '<', now());
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('kode_customer', $customerId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }
}