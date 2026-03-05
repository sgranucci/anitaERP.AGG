<?php

namespace App\Models\Produccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tarea extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'tarea';

}
