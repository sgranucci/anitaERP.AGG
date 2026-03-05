<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Idioma extends Model
{
    protected $fillable = ['nombre', 'abreviatura', 'codigo'];
    protected $table = 'idioma';
}
