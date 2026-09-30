<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class MotivoDevolucion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'motivo_devolucion';

    protected $fillable = [
        'codigo',
        'nombre',
        'vuelve_stock',
        'activo',
        'orden',
    ];

    protected $casts = [
        'vuelve_stock' => 'boolean',
        'activo' => 'boolean',
        'orden' => 'integer',
    ];
}
