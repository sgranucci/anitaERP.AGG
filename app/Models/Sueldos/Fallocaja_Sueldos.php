<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Fallocaja_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'fallocaja_sueldos';

    protected $fillable = [
        'tipo',
        'orden',
        'desde',
        'hasta',
        'sancion',
    ];

    protected $casts = [
        'orden' => 'integer',
        'desde' => 'decimal:2',
        'hasta' => 'decimal:2',
    ];
}
