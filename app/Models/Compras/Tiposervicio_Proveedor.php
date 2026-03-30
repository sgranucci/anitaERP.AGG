<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tiposervicio_Proveedor extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'tiposervicio_proveedor';
}
