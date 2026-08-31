<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Concepto_Venta_Tag_Catalogo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'concepto_venta_tag_catalogo';

    protected $fillable = [
        'clave',
        'etiqueta',
        'tipo',
        'es_sistema',
        'largo_max',
        'opciones',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'largo_max' => 'integer',
    ];
}
