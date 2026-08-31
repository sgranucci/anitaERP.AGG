<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lsd_Concepto_Afip_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lsd_concepto_afip_sueldos';

    protected $fillable = [
        'codigo', 'tipo', 'descripcion', 'pide_cantidad', 'rango_libre', 'activo',
    ];

    protected $casts = [
        'pide_cantidad' => 'boolean',
        'rango_libre' => 'boolean',
        'activo' => 'boolean',
    ];
}
