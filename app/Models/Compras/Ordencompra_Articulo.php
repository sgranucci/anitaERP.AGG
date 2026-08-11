<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Articulo extends Model
{
    protected $table = 'ordencompra_articulo';

    protected $fillable = [
        'ordencompra_id', 'requisicion_articulo_id', 'fechaentrega', 'articulo_id', 'articulo_proveedor_id',
        'color_id', 'talle_id', 'penvp_orden', 'penvp_nro_interno', 'lote_transferencia', 'peso_unitario', 'peso_total',
        'estado_linea_oc', 'cantidad', 'precio', 'moneda_id', 'cotizacion',
        'descuento', 'cantidadalternativa', 'detalle', 'centrocostodestino_id', 'partidagasto_id', 'capex_id',
        'precio_origen_tipo', 'precio_origen_ref_id', 'precio_origen_etiqueta',
    ];

    public function ordencompras()
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function articulo_proveedor()
    {
        return $this->belongsTo(Articulo_Proveedor::class, 'articulo_proveedor_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function centrocostos_destino()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocostodestino_id');
    }

    public function partidagastos()
    {
        return $this->belongsTo(Partidagasto::class, 'partidagasto_id');
    }

    public function capexs()
    {
        return $this->belongsTo(Capex::class, 'capex_id');
    }

    public function requisicion_articulos()
    {
        return $this->belongsTo(Requisicion_Articulo::class, 'requisicion_articulo_id');
    }

    public function ordencompra_articulo_precio_historias()
    {
        return $this->hasMany(Ordencompra_Articulo_Precio_Historia::class, 'ordencompra_articulo_id');
    }

    public function entregas()
    {
        return $this->hasMany(Ordencompra_Articulo_Entrega::class, 'ordencompra_articulo_id')
            ->orderBy('orden')
            ->orderBy('fecha')
            ->orderBy('id');
    }
}
