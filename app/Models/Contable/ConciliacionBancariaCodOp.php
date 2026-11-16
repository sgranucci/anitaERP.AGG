<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaCodOp extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_cod_op';

    protected $fillable = [
        'cod_op',
        'codigo',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
