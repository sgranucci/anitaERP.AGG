<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

class Ganancia_Escala_Tramo_Sueldos extends Model
{
    protected $table = 'ganancia_escala_tramo_sueldos';

    protected $fillable = [
        'anio', 'mes', 'desde', 'hasta', 'fijo', 'alicuota', 'excedente', 'nro_tramo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'desde' => 'decimal:2',
        'hasta' => 'decimal:2',
        'fijo' => 'decimal:2',
        'alicuota' => 'decimal:4',
        'excedente' => 'decimal:2',
        'nro_tramo' => 'integer',
    ];
}
