<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Ganancia_Deduccion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'ganancia_deduccion_sueldos';

    protected $fillable = [
        'codigo', 'descripcion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
