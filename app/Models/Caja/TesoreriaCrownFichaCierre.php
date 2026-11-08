<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownFichaCierre extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_ficha_cierre';

    protected $fillable = [
        'fecha',
        'codigo_ficha',
        'reserva',
    ];

    protected $casts = [
        'fecha' => 'date',
        'codigo_ficha' => 'integer',
        'reserva' => 'integer',
    ];
}
