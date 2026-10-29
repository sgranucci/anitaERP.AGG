<?php

declare(strict_types=1);

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Última secuencia y CBU origen de la carga de solicitudes de retiro (Crown).
 * Equivale al archivo p-cargasolret.dft de Anita.
 */
class SolicitudRetiroCrownPreferencia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitud_retiro_crown_preferencia';

    protected $fillable = [
        'ultima_secuencia',
        'cbu_origen',
    ];

    protected $casts = [
        'ultima_secuencia' => 'integer',
    ];
}
