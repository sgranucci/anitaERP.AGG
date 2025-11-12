<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Articulo;
use App\Models\Stock\Lote;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Descuentoventa;
use App\Models\Ventas\Transporte;
use App\Traits\Ventas\Pedido_ArticuloTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Pedido_Articulo extends Model implements Auditable
{
	use Pedido_ArticuloTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['pedido_id', 'articulo_id', 'numeroitem', 'caja', 'pieza', 'kilo', 'pesada', 
		'precio', 'incluyeimpuesto', 'listaprecio_id', 'moneda_id', 'descuento', 'descuentointegrado', 
		'lote_id', 'observacion', 'estado', 'unidadmedida_id', 'descuentoventa_id'];
    protected $table = 'pedido_articulo';
    protected $tableAnita = 'pendmov';
    protected $keyField = 'id';

    public function pedido_articulo_estados()
	{
    	return $this->hasMany(Pedido_Articulo_Estado::class, 'pedido_articulo_id')
                    ->with('clientes')
                    ->with('motivoscierrepedido');
	}

    public function pedido_articulo_cajas()
	{
    	return $this->hasMany(Pedido_Articulo_Caja::class, 'pedido_articulo_id');
	}

    public function lotes()
	{
    	return $this->belongsTo(Lote::class, 'lote_id', 'id');
	}

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id')->with('lineas')->with('mventas')->with('unidadesdemedidas');
	}

    public function pedidos()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }

    public function descuentoventa_ids()
    {
        return $this->belongsTo(Descuentoventa::class, 'descuentoventa_id');
    }

    public function listasprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}

