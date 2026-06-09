<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Stock\Articulo;

class Venta_Emision extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['venta_id','numeroitem', 'pedido_combinacion_id', 'ordentrabajo_id', 'lotestock',
                        'articulo_id', 'combinacion_id', 'detalle', 'comentario_cocina', 'modulo_id', 'talle_id', 
                        'cantidad', 'pieza', 'caja',
                        'precio', 
                        'impuesto_id', 'incluyeimpuesto', 
                        'moneda_id', 'descuento', 'descuentointegrado', 'deposito_id', 'loteimportacion_id'];
    protected $table = 'venta_emision';

    public function ventas()
	{
    	return $this->belongsTo(Venta::class, 'venta_id');
	}

    public function ordenestrabajo()
	{
    	return $this->belongsTo(Ordentrabajo::class, 'ordentrabajo_id', 'id');
	}

    public function pedidos_combinacion()
	{
    	return $this->belongsTo(Pedido_Combinacion::class, 'pedido_combinacion_id', 'id')->with('articulos');
	}

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function combinaciones()
    {
        return $this->hasOne(Combinacion::class, 'combinacion_id', 'id');
    }

    public function modulos()
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function impuestos()
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

}
