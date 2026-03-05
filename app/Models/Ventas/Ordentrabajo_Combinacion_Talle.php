<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ventas\Pedido_Combinacion_Talle;

class Ordentrabajo_Combinacion_Talle extends Model
{
    protected $fillable = ['ordentrabajo_id', 'pedido_combinacion_talle_id', 'cliente_id', 'estado', 'ordentrabajo_stock_id', 'usuario_id'];
    protected $table = 'ordentrabajo_combinacion_talle';

	public function clientes()
	{
    	return $this->belongsTo(Cliente::class, 'cliente_id', 'id')->with('tipossuspensioncliente');
	}

	public function pedido_combinacion_talles()
	{
    	return $this->belongsTo(Pedido_Combinacion_Talle::class, 'pedido_combinacion_talle_id', 'id')->with("pedidos_combinacion")->with("talles");
	}
}

