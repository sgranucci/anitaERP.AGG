<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Stock\Articulo;

class Plvista extends Model
{
    protected $fillable = ['nombre', 'articulo_id'];
    protected $table = 'plvista';

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}

