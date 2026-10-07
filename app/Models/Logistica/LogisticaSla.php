<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaSla extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_sla';

    protected $fillable = [
        'prioridad', 'horas_preparacion', 'horas_entrega',
    ];

    protected $casts = [
        'horas_preparacion' => 'integer',
        'horas_entrega' => 'integer',
    ];
}
