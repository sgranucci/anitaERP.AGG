<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Feriado extends Model
{
    protected $fillable = ['nombre', 'fecha'];
    protected $table = 'feriado';
}

