<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    protected $table = 'perusahaan';

    protected $fillable = [
        'nama',
        'alamat',
        'kota',
        'kode_pos',
        'telepon',
        'fax',
        'bri_account',
        'bca_account',
        'catatan_nota',
        'catatan_surat_jalan',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Get active company
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Company shown in nota/surat jalan headers. Never returns an empty header.
     */
    public static function forNota(): self
    {
        return static::where('is_default', true)->first()
            ?? static::first()
            ?? new static([
                'nama' => 'PLASINDOMART',
                'alamat' => 'JL.IKAN DUYUNG NO 51 TELUK BETUNG SELATANG BANDAR LAMPUNG',
                'kota' => 'LAMPUNG',
                'kode_pos' => '35224',
                'telepon' => '08117230286',
                'fax' => '-',
            ]);
    }
}