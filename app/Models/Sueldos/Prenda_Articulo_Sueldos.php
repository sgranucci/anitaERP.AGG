<?php

namespace App\Models\Sueldos;

use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\Model;

class Prenda_Articulo_Sueldos extends Model
{
    protected $table = 'prenda_articulo_sueldos';

    protected $fillable = [
        'prenda_id',
        'color_id',
        'talle_id',
        'articulo_id',
        'sku',
    ];

    protected $casts = [
        'prenda_id' => 'integer',
        'color_id' => 'integer',
        'talle_id' => 'integer',
        'articulo_id' => 'integer',
    ];

    public function prenda()
    {
        return $this->belongsTo(Prenda_Sueldos::class, 'prenda_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
