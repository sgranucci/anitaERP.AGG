<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ganancia_Linea_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ganancia_linea_sueldos';

    protected $fillable = [
        'codigo', 'descripcion', 'orden', 'origen', 'formula',
        'deduccion_codigo', 'concepto_afip', 'concepto_id', 'activo', 'va_planilla',
    ];

    protected $casts = [
        'orden' => 'integer',
        'concepto_id' => 'integer',
        'activo' => 'boolean',
        'va_planilla' => 'boolean',
    ];

    public const ORIGENES = [
        'entrada' => 'Entrada (liquidación / movimiento)',
        'formula' => 'Fórmula',
        'deduccion_art30' => 'Deducción Art. 30',
    ];
}
