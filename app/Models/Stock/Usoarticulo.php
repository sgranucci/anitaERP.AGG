<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Usoarticulo extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'usoarticulo';

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

}
