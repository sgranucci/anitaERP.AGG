<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Talle;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;

class Pedido_Combinacion_Talle extends Model
{
    protected $fillable = ['pedido_combinacion_id', 'talle_id', 'cantidad', 'precio'];
    protected $table = 'pedido_combinacion_talle';

    public function pedidos_combinacion()
    {
        return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id')->with("articulos")->with("pedidos");;
    }

	// Por error en nomenclatura
    public function pedido_combinaciones()
    {
        return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id')->with("articulos")->with("pedidos");;
    }

    public function talles()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function pedidos_combinacion_ordenes()
    {
        return $this->hasOne(Ordentrabajo_Combinacion_Talle::class, 'pedido_combinacion_talle_id', 'id');
    }

}

