<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Nombrebase_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'nombrebase_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
