<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ventas\Motivocierrepedido;
use App\Models\Ventas\Cliente;
use OwenIt\Auditing\Contracts\Auditable;

class Pedido_Articulo_Estado extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['pedido_articulo_id', 'motivocierrepedido_id', 'cliente_id', 'estado', 
                            'observacion'];
    protected $table = 'pedido_articulo_estado';

    public function pedido_articulos()
    {
        return $this->belongsTo(Pedido_Articulo::class, 'pedido_articulo_id')->with("articulos")->with("pedidos");;
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

