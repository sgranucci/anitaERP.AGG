<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;

class TesoreriaCrownNumerador extends Model
{
    protected $table = 'tesoreria_crown_numerador';

    protected $fillable = [
        'sala_id',
        'tipo',
        'ultimo',
    ];

    protected $casts = [
        'sala_id' => 'integer',
        'ultimo' => 'integer',
    ];
}
