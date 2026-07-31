<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransaksiItem extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaksi_items';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaksi_id',
        'no_transaksi',
        'kode_barang',
        'nama_barang',
        'keterangan',
        'harga',
        'panjang',
        'lebar',
        'qty',
        'qty_return',
        'qty_sisa',
        'satuan',
        'satuan_besar',
        'qty_besar',
        'diskon',
        'total',
        'ongkos_kuli',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'qty' => 'decimal:2',
        'qty_return' => 'decimal:2',
        'qty_sisa' => 'decimal:2',
        'diskon' => 'decimal:2',
        'total' => 'decimal:2',
        'ongkos_kuli' => 'decimal:2',
    ];
    
    /**
     * Get the transaction that owns the item.
     */
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'no_transaksi', 'no_transaksi');
    }

    public function itemsTransaksiId(){
        return $this->belongsTo(Transaksi::class, 'transaksi_id', 'id');
    }

    /**
     * Get the kode barang associated with the item.
     */
    public function kodeBarang()
    {
        return $this->belongsTo(KodeBarang::class, 'kode_barang', 'kode_barang');
    }

    public function suratJalanItems()
    {
        return $this->hasMany(SuratJalanItem::class, 'transaksi_item_id', 'id');
    }

    /**
     * Relasi ke TransaksiItemSumber untuk tracking FIFO
     */
    public function transaksiItemSumber(): HasMany
    {
        return $this->hasMany(TransaksiItemSumber::class, 'transaksi_item_id');
    }

    /**
     * Update qty_sisa setelah return
     */
    public function updateQtySisa(): void
    {
        $this->qty_sisa = $this->qty - $this->qty_return;
        $this->save();
    }

    /**
     * Add return quantity
     */
    public function addReturnQty(float $qty): void
    {
        $this->qty_return += $qty;
        $this->updateQtySisa();
    }

    /**
     * Check if item can be returned
     */
    public function canBeReturned(float $qty): bool
    {
        return $this->qty_sisa >= $qty;
    }

    /**
     * Get return percentage
     */
    public function getReturnPercentage(): float
    {
        if ($this->qty == 0) return 0;
        return ($this->qty_return / $this->qty) * 100;
    }

    /**
     * Check if item is fully returned
     */
    public function isFullyReturned(): bool
    {
        return $this->qty_sisa == 0;
    }

    /**
     * Check if item is partially returned
     */
    public function isPartiallyReturned(): bool
    {
        return $this->qty_return > 0 && $this->qty_sisa > 0;
    }
}

