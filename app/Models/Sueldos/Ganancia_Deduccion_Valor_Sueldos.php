<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

class Ganancia_Deduccion_Valor_Sueldos extends Model
{
    protected $table = 'ganancia_deduccion_valor_sueldos';

    protected $fillable = [
        'codigo', 'anio', 'mes', 'valor_acumulado',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'valor_acumulado' => 'decimal:2',
    ];
}
