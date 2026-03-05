<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Motivocierrepedido extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'motivocierrepedido';
}

