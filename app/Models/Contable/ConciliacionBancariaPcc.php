<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaPcc extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_pcc';

    protected $fillable = [
        'pcc',
        'codigo',
        'descripcion',
        'patrones_concepto',
        'patrones_descripcion',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
