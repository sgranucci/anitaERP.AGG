<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento (cuota) de un plan en una corrida de liquidación.
 * pendiente al calcular; confirmado al cerrar la corrida.
 */
class Empleado_Plan_Cuota_Movimiento_Sueldos extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CONFIRMADO = 'confirmado';

    protected $table = 'empleado_plan_cuota_movimiento_sueldos';

    protected $fillable = [
        'plan_id',
        'liquidacion_id',
        'empleado_id',
        'periodo',
        'numero_cuota',
        'importe',
        'fecha',
        'estado',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'liquidacion_id' => 'integer',
        'empleado_id' => 'integer',
        'periodo' => 'integer',
        'numero_cuota' => 'integer',
        'importe' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Empleado_Plan_Cuota_Sueldos::class, 'plan_id');
    }
}
