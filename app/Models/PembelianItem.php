<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pembelian_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nota',
        'kode_barang',
        'nama_barang',
        'keterangan',
        'harga',
        'qty',
        'satuan',
        'satuan_besar',
        'qty_besar',
        'diskon',
        'total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'harga' => 'decimal:2',
        'diskon' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the purchase that owns the item.
     */
    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'nota', 'nota');
    }

    /**
     * Get the kode barang associated with the item.
     */
    public function kodeBarang()
    {
        return $this->belongsTo(KodeBarang::class, 'kode_barang', 'kode_barang');
    }
}