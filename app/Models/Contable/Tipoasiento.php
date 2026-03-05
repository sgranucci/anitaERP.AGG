<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tipoasiento extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'tipoasiento';
}
