<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownFichaBanca extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_ficha_banca';

    protected $fillable = [
        'codigo_ficha',
        'cantidad',
    ];

    protected $casts = [
        'codigo_ficha' => 'integer',
        'cantidad' => 'integer',
    ];
}
