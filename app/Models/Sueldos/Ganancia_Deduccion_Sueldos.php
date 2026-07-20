<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;

class Ganancia_Deduccion_Sueldos extends Model
{
    protected $table = 'ganancia_deduccion_sueldos';

    protected $fillable = [
        'codigo', 'descripcion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
