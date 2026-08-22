<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorArea extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor_area';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
