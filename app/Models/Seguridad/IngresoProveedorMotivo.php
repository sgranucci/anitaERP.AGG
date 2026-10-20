<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class IngresoProveedorMotivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'ingreso_proveedor_motivo';

    protected $fillable = ['codigo', 'nombre', 'activo', 'exige_oc'];

    protected $casts = [
        'activo' => 'boolean',
        'exige_oc' => 'boolean',
    ];
}
