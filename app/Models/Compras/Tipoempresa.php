<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tipoempresa extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'tipoempresa';
}
