<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Parametro_Sistema extends Model
{
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
