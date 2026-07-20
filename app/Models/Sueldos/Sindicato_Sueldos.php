<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Sindicato_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'sindicato_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'numero',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
