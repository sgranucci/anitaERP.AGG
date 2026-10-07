<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaCatalogoCategoria extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_catalogo_categoria';

    protected $fillable = ['codigo', 'nombre', 'icono', 'orden', 'activo'];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
}
