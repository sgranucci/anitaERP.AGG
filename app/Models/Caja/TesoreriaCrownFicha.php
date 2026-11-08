<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TesoreriaCrownFicha extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tesoreria_crown_ficha';

    protected $fillable = [
        'codigo',
        'serie',
        'valor',
        'orden_carga',
        'ficha_canje',
        'desc_color',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'valor' => 'decimal:2',
        'orden_carga' => 'integer',
        'ficha_canje' => 'boolean',
    ];
}
