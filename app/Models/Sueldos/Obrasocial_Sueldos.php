<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Obrasocial_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'obrasocial_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'numero',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
