<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaTipoComp extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_tipo_comp';

    protected $fillable = [
        'tipo_comp',
        'patrones',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
