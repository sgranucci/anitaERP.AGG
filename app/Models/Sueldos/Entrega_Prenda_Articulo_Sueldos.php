<?php

namespace App\Models\Sueldos;

use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\Model;

class Entrega_Prenda_Articulo_Sueldos extends Model
{
    protected $table = 'entrega_prenda_articulo_sueldos';

    protected $fillable = [
        'entrega_id',
        'prenda_id',
        'prenda_articulo_id',
        'color_id',
        'talle_id',
        'articulo_id',
        'sku',
        'cantidad',
        'vence_el',
    ];

    protected $casts = [
        'entrega_id' => 'integer',
        'prenda_id' => 'integer',
        'prenda_articulo_id' => 'integer',
        'color_id' => 'integer',
        'talle_id' => 'integer',
        'articulo_id' => 'integer',
        'cantidad' => 'decimal:3',
        'vence_el' => 'date',
    ];

    public function entrega()
    {
        return $this->belongsTo(Entrega_Prenda_Sueldos::class, 'entrega_id');
    }

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
