<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Período mensual de un concepto F572 (PeriodoType).
 */
class Siradig_Concepto_Periodo_Sueldos extends Model
{
    protected $table = 'siradig_concepto_periodo_sueldos';

    protected $fillable = [
        'concepto_id',
        'mes_desde',
        'mes_hasta',
        'monto_mensual',
    ];

    protected $casts = [
        'concepto_id' => 'integer',
        'mes_desde' => 'integer',
        'mes_hasta' => 'integer',
        'monto_mensual' => 'decimal:2',
    ];

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Siradig_Concepto_Sueldos::class, 'concepto_id');
    }
}
