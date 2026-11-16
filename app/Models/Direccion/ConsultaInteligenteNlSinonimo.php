<?php

namespace App\Models\Direccion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConsultaInteligenteNlSinonimo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'consulta_inteligente_nl_sinonimo';

    protected $fillable = [
        'frase',
        'entidad',
        'metrica',
        'prioridad',
        'activo',
    ];

    protected $casts = [
        'prioridad' => 'integer',
        'activo' => 'boolean',
    ];
}
