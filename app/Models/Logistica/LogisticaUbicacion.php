<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaUbicacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_ubicacion';

    protected $fillable = [
        'nombre', 'icono', 'orden', 'empresa_id', 'deposito_id', 'sala_id', 'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'empresa_id' => 'integer',
        'deposito_id' => 'integer',
        'sala_id' => 'integer',
        'activo' => 'boolean',
    ];
}
