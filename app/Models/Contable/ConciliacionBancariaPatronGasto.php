<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaPatronGasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_patron_gasto';

    protected $fillable = [
        'codigo',
        'descripcion',
        'patrones',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
