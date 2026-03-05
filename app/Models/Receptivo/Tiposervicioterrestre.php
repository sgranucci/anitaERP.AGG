<?php

namespace App\Models\Receptivo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tiposervicioterrestre extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'tiposervicioterrestre';
}
