<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Art_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'art_sueldos';

    protected $fillable = [
        'codigo',
        'nombre',
    ];

    protected $casts = [
        'codigo' => 'string',
    ];
}
