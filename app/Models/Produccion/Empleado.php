<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Empleado extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'empleado';

}
