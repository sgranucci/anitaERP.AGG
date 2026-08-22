<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorPunto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor_punto';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
