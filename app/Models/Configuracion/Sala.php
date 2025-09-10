<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Sala extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'sala';
}

