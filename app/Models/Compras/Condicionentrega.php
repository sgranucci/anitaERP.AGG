<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Condicionentrega extends Model
{
    protected $fillable = ['nombre', 'codigo', 'dias'];
    protected $table = 'condicionentrega';
}
