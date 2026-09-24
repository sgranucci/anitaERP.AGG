<?php

namespace App\Models\Listado;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ListadoColumnaEtiqueta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'listado_columna_etiqueta';

    protected $fillable = [
        'recurso',
        'columna_key',
        'etiqueta',
    ];
}
