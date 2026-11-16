<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaDocGasto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_doc_gasto';

    protected $fillable = [
        'codigo',
        'cuenta_codigo',
        'concepto_ivacompra_codigo',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];
}
