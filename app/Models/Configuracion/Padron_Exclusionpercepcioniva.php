<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Padron_Exclusionpercepcioniva extends Model
{
    protected $fillable = ['id', 'nombre', 'cuit', 'desdefecha', 'hastafecha'];
    protected $table = 'padron_exclusionpercepcioniva';
}

