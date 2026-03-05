<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tipodocumento extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'codigoexterno'];
    protected $table = 'tipodocumento';
}

