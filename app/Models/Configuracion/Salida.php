<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Salida extends Model
{
    protected $fillable = ['nombre', 'ubicacion', 'comando'];
    protected $table = 'salida';
    protected $keyField = 'id';
}
