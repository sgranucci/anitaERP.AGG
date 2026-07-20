<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $table = 'feriado';

    protected $fillable = ['nombre', 'fecha', 'tipo'];
}
