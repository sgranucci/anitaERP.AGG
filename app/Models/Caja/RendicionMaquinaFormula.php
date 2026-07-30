<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RendicionMaquinaFormula extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'rendicion_maquina_formula';

    protected $fillable = [
        'codigo',
        'destino',
        'expresion',
        'seccion',
        'orden',
        'activo',
        'solo_completo',
        'detalle',
        'version_catalogo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
        'solo_completo' => 'boolean',
        'version_catalogo' => 'integer',
    ];

    protected $attributes = [
        'activo' => true,
        'solo_completo' => false,
        'orden' => 0,
        'version_catalogo' => 1,
    ];
}
