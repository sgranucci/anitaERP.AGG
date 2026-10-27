<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Model;

class BuscadorEleccion extends Model
{
    protected $table = 'buscador_eleccion';

    protected $fillable = [
        'rol_id',
        'consulta',
        'tipo',
        'clave',
        'veces',
    ];
}
