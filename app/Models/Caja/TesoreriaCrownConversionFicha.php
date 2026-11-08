<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownConversionFicha extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_conversion_ficha';

    protected $fillable = [
        'fecha',
        'codigo_ficha',
        'conv_final',
    ];

    protected $casts = [
        'fecha' => 'date',
        'codigo_ficha' => 'integer',
        'conv_final' => 'integer',
    ];
}
