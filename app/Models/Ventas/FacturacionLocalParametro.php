<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class FacturacionLocalParametro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'facturacion_local_parametro';

    protected $fillable = [
        'clave',
        'valor',
        'etiqueta',
        'ayuda',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
