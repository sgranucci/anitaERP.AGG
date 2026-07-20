<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Motivoegreso_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'motivoegreso_sueldos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'clase',
    ];

    protected $casts = [
        'codigo' => 'integer',
    ];
}
