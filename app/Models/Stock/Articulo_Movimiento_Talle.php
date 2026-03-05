<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Articulo_Movimiento_Talle extends Model
{
    //use SoftDeletes;
    protected $fillable = ['articulo_movimiento_id', 'pedido_combinacion_talle_id', 'talle_id', 'cantidad', 'precio'];
    protected $table = 'articulo_movimiento_talle';

    public function articulo_movimientos()
    {
        return $this->belongsTo(Articulo_Movimiento::class, 'articulo_movimiento_id', 'id');
    }

    public function talles()
    {
        return $this->belongsTo(Talle::class, 'talle_id', 'id');
    }

}
