<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

/**
 * Valor historico de un acumulador para un empleado en un periodo. Alimenta las
 * funciones "hacia atras" del motor (SAC, proporcionales, liquidacion final).
 */
class Liquidacion_Acumulador_Sueldos extends Model
{
    protected $table = 'liquidacion_acumulador_sueldos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'liquidacion_id',
        'periodo',
        'periodo_anio',
        'periodo_mes',
        'tipo_corrida',
        'codigo',
        'valor',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_id' => 'integer',
        'liquidacion_id' => 'integer',
        'periodo' => 'integer',
        'periodo_anio' => 'integer',
        'periodo_mes' => 'integer',
        'valor' => 'decimal:2',
    ];
}
