<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Formapago extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'formapago';
}

