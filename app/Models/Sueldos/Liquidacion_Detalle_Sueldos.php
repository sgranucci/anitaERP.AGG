<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Renglon (concepto liquidado) de un recibo.
 */
class Liquidacion_Detalle_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'liquidacion_detalle_sueldos';

    protected $fillable = [
        'recibo_id',
        'liquidacion_id',
        'empleado_id',
        'concepto_id',
        'concepto_codigo',
        'concepto_descripcion',
        'tipo',
        'nro_linea',
        'columna',
        'cantidad',
        'valor',
        'base_calculo',
        'importe',
        'remunerativo',
        'va_recibo',
        'concepto_afip',
        'leyenda',
        'origen_tabla',
        'origen_serial',
        'origen_nro_interno',
        'origen_clave',
    ];

    protected $casts = [
        'recibo_id' => 'integer',
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'concepto_id' => 'integer',
        'concepto_codigo' => 'integer',
        'nro_linea' => 'integer',
        'cantidad' => 'decimal:4',
        'valor' => 'decimal:4',
        'base_calculo' => 'decimal:2',
        'importe' => 'decimal:2',
        'remunerativo' => 'boolean',
        'va_recibo' => 'boolean',
        'origen_serial' => 'integer',
        'origen_nro_interno' => 'integer',
    ];

    public const COLUMNAS = [
        'haber' => 'Haber',
        'descuento' => 'Descuento',
        'neto' => 'Neto',
        'informativo' => 'Informativo',
        'contribucion' => 'Contrib. empleador',
    ];

    public function recibo()
    {
        return $this->belongsTo(Liquidacion_Recibo_Sueldos::class, 'recibo_id');
    }

    public function concepto()
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }
}
