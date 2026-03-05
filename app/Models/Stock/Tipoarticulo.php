<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Tipoarticulo extends Model
{
    protected $fillable = ['nombre', 'abreviatura'];
    protected $table = 'tipoarticulo';
}
