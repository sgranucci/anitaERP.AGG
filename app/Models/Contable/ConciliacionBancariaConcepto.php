<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ConciliacionBancariaConcepto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'conciliacion_bancaria_concepto';

    protected $fillable = [
        'patron',
        'codigo',
        'descripcion',
        'es_acumulado',
        'orden',
    ];

    protected $casts = [
        'es_acumulado' => 'boolean',
        'orden' => 'integer',
    ];
}
