<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Lugartrabajo_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'lugartrabajo_sueldos';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
