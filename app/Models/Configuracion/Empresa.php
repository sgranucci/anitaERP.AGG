<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Empresa extends Model
{
    protected $fillable = ['nombre', 'domicilio', 'nroinscripcion', 'codigo', 'numeroiibb', 'fechainicioactividad'];
    protected $table = 'empresa';

}
