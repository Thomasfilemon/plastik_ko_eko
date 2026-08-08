<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'no_transaksi',
        'tanggal',
        'no_nota',
        'supplier_customer',
        'plus',
        'minus',
        'total',
        'so',
        'satuan',
        'keterangan',
        'created_by'

    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'plus' => 'float',
        'minus' => 'float',
        'total' => 'float',
    ];

    /**
     * Mutations that should drive mutasi / laporan stok:
     * - skip canceled nota
     * - skip edit reversals
     * - skip original lines superseded by edit (is_edited)
     * - keep only the latest "(updated)" batch per nota
     */
    public function scopeExcludeCanceled(Builder $query): Builder
    {
        return $query
            ->where('no_transaksi', 'not like', '%(DIBATALKAN)%')
            ->where('no_transaksi', 'not like', '%[CANCELED]%')
            // Edit reversals are intermediate — final stock is the latest (updated) / active line
            ->where(function (Builder $q) {
                $q->whereNull('no_nota')
                    ->orWhere('no_nota', '')
                    ->orWhere('no_nota', 'not like', '% (reversal)');
            })
            ->where(function (Builder $q) {
                $q->whereNull('no_nota')
                    ->orWhere('no_nota', '')
                    ->orWhere(function (Builder $q2) {
                        // Drop lines whose nota was canceled
                        $q2->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('transaksi')
                                ->whereColumn('transaksi.no_transaksi', 'stock_mutations.no_nota')
                                ->where('transaksi.status', 'canceled');
                        })->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('pembelian')
                                ->whereColumn('pembelian.nota', 'stock_mutations.no_nota')
                                ->where('pembelian.status', 'canceled');
                        })
                        // Drop original lines when nota was edited (replaced by "(updated)")
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('transaksi')
                                ->whereColumn('transaksi.no_transaksi', 'stock_mutations.no_nota')
                                ->where('transaksi.is_edited', 1);
                        })->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('pembelian')
                                ->whereColumn('pembelian.nota', 'stock_mutations.no_nota')
                                ->where('pembelian.is_edited', 1);
                        });
                    });
            })
            // If multiple edits, only the latest "(updated)" set counts
            ->where(function (Builder $q) {
                $q->whereNull('no_nota')
                    ->orWhere('no_nota', '')
                    ->orWhere('no_nota', 'not like', '% (updated)')
                    ->orWhereRaw(
                        'tanggal = (
                            SELECT MAX(sm2.tanggal)
                            FROM stock_mutations sm2
                            WHERE sm2.no_nota = stock_mutations.no_nota
                        )'
                    );
            });
    }
}
