<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cai extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cai';

    protected $fillable = [
        'orden',
        'tipo',
        'descripcion',
        'letra',
        'sucursal',
        'numero_cai',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'orden' => 'integer',
        'sucursal' => 'integer',
        'fecha_vencimiento' => 'date',
    ];
}
