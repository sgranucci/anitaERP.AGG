<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Tipo de ahorro (KPI de ahorro de requisición y orden de compra).
 */
class TipoAhorro extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tipo_ahorro';

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
