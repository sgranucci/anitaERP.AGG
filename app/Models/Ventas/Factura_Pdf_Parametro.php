<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Factura_Pdf_Parametro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'factura_pdf_parametro';

    protected $fillable = [
        'empresa_id',
        'clave',
        'valor',
        'etiqueta',
        'ayuda',
        'orden',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'orden' => 'integer',
    ];
}
