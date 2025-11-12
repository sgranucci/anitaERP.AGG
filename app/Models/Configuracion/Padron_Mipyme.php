<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Padron_Mipyme extends Model
{
    protected $fillable = ['id', 'nombre', 'cuit', 'actividad', 'fechainicio'];
    protected $table = 'padron_mipyme';
}

