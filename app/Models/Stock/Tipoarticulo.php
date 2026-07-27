<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Tipoarticulo extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'usa_control_contable_cigarrillos'];

    protected $table = 'tipoarticulo';

    protected $casts = [
        'usa_control_contable_cigarrillos' => 'boolean',
    ];

    /**
     * Tipo configurado para control contable / impuesto interno de cigarrillos.
     * Prioriza el flag; fallback al nombre de config (CIGARRILLO).
     */
    public static function idControlContableCigarrillos(): ?int
    {
        $id = static::query()
            ->where('usa_control_contable_cigarrillos', true)
            ->orderBy('id')
            ->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $nombre = strtoupper(trim((string) config('facturacion.IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO')));
        if ($nombre === '') {
            return null;
        }

        $id = static::query()->whereRaw('UPPER(TRIM(nombre)) = ?', [$nombre])->value('id');

        return $id !== null ? (int) $id : null;
    }
}
