<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Parametro_Sistema extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'parametro_sistema';

    protected $fillable = [
        'clave',
        'grupo',
        'etiqueta',
        'ayuda',
        'tipo',
        'valor',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
