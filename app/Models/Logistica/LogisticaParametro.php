<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaParametro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_parametro';

    protected $fillable = ['monto_aprobacion'];

    protected $casts = [
        'monto_aprobacion' => 'float',
    ];

    public static function montoAprobacion(): float
    {
        $monto = self::query()->orderBy('id')->value('monto_aprobacion');

        return $monto === null ? 0.0 : (float) $monto;
    }
}
