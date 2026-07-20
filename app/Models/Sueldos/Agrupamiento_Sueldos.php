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
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
