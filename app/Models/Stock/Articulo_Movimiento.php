<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Ventas\Ordentrabajo;
use App\Models\Ventas\Venta;

class Articulo_Movimiento extends Model
{
    protected $fillable = ['fecha','fechajornada', 'tipotransaccion_id', 'tipotransaccion_stock_id', 'venta_id', 'venta_emision_id', 'movimientostock_id',
                        'pedido_combinacion_id', 'ordentrabajo_id', 'lote', 'articulo_id', 'color_id', 'talle_id', 'numeroparte', 'combinacion_id', 
                        'concepto', 'modulo_id', 'cantidad', 'caja', 'pieza', 
                        'precio', 'costo', 'listaprecio_id', 'incluyeimpuesto', 
                        'moneda_id', 'descuento', 'descuentointegrado', 'deposito_id', 'bien_uso_id', 'loteimportacion_id',
                        'pedido_articulo_id', 'vianda_consumo_id'];

    protected $table = 'articulo_movimiento';

    public function articulo_movimiento_talles()
	{
    	return $this->hasMany(Articulo_Movimiento_Talle::class, 'articulo_movimiento_id');
	}

    public function ordenestrabajo()
	{
    	return $this->belongsTo(Ordentrabajo::class, 'ordentrabajo_id', 'id');
	}

    public function movimientosstock()
	{
    	return $this->belongsTo(MovimientoStock::class, 'movimientostock_id', 'id');
	}

    public function pedidos_combinacion()
	{
    	return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id', 'id')->with('articulos');
	}

    public function pedido_articulos()
	{
    	return $this->belongsTo(Pedido_Articulo::class, 'pedido_articulo_id', 'id')->with('articulos');
	}

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id', 'id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function combinaciones()
    {
        return $this->belongsTo(Combinacion::class, 'combinacion_id', 'id');
    }

    public function modulos()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function listasprecio()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id', 'id');
	}

    public function venta_emisiones()
    {
        return $this->belongsTo(\App\Models\Ventas\Venta_Emision::class, 'venta_emision_id', 'id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function bienes_uso()
    {
        return $this->belongsTo(\App\Models\Contable\BienUso::class, 'bien_uso_id');
    }

    public function viandaConsumo()
    {
        return $this->belongsTo(\App\Models\Ventas\ViandaConsumo::class, 'vianda_consumo_id');
    }

}
