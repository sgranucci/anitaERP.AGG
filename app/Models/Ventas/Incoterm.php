<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Incoterm extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'incoterm';
}

