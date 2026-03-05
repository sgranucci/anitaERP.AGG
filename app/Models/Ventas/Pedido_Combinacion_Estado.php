<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ventas\Motivocierrepedido;
use App\Models\Ventas\Cliente;

class Pedido_Combinacion_Estado extends Model
{
    protected $fillable = ['pedido_combinacion_id', 'motivocierrepedido_id', 'cliente_id', 'estado', 
                            'observacion'];
    protected $table = 'pedido_combinacion_estado';

    public function pedido_combinaciones()
    {
        return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id')->with("articulos")->with("pedidos");;
    }

	public function motivoscierrepedido()
    {
        return $this->belongsTo(Motivocierrepedido::class, 'motivocierrepedido_id');
    }
    
	public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}

