<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaTipoSolicitud extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_tipo_solicitud';

    protected $fillable = ['codigo', 'nombre', 'icono', 'orden', 'activo'];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
}
