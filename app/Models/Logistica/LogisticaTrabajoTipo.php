<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaTrabajoTipo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_trabajo_tipo';

    protected $fillable = [
        'codigo', 'nombre', 'icono', 'responsable', 'email', 'prioridad_piso', 'orden', 'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
}
