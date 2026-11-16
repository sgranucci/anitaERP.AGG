<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaResidualRegla extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_residual_regla';

    protected $fillable = [
        'clave',
        'descripcion',
        'patrones',
        'dh',
        'cuenta_codigo',
        'concepto_ivacompra_codigo',
        'tipo_documento',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
