<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Modulo;
use App\Models\Stock\Lote;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Transporte;
use App\Models\Seguridad\Usuario;
use App\Traits\Ventas\Pedido_CombinacionTrait;

class Pedido_Combinacion extends Model
{
	use Pedido_CombinacionTrait;

    protected $fillable = ['pedido_id', 'combinacion_id', 'articulo_id', 'numeroitem', 'modulo_id', 'cantidad', 
		'precio', 'incluyeimpuesto', 'listaprecio_id', 'moneda_id', 'descuento', 'descuentointegrado', 
		'categoria_id', 'subcategoria_id', 'linea_id', 'ot_id', 'lote_id', 'observacion', 'estado'];
    protected $table = 'pedido_combinacion';
    protected $tableAnita = 'pendmov';
    protected $keyField = 'id';

	public function pedido_combinacion_talles()
	{
    	return $this->hasMany(Pedido_Combinacion_Talle::class, 'pedido_combinacion_id');
	}

    public function pedido_combinacion_estados()
	{
    	return $this->hasMany(Pedido_Combinacion_Estado::class, 'pedido_combinacion_id')
                    ->with('clientes')
                    ->with('motivoscierrepedido');
	}

	public function ordenestrabajo()
	{
    	return $this->hasOne(Ordentrabajo::class, 'id', 'ot_id');
	}

    public function lotes()
	{
    	return $this->belongsTo(Lote::class, 'lote_id', 'id');
	}

	public function articulos()
	{
    	return $this->belongsTo(Articulo::class, 'articulo_id', 'id')->with('lineas')->with('mventas');
	}

	public function combinaciones()
	{
    	return $this->belongsTo(Combinacion::class, 'combinacion_id', 'id');
	}

    public function pedidos()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }

    public function modulos()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id', 'id');
    }

    public function listasprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function categorias()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategorias()
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    public function lineas()
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

	public function getModuloIdAttribute($value)
    {
		if ($value == null || $value == 0)
			$value = 30;
        return ($value);
    }
}

