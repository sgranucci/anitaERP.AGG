<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Agrupamiento_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'agrupamiento_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'fallo_tipo',
        'variable1',
        'variable2',
        'variable3',
        'variable4',
    ];

    protected $casts = [
        'codigo' => 'integer',
        'variable1' => 'float',
        'variable2' => 'float',
        'variable3' => 'float',
        'variable4' => 'float',
    ];
}
