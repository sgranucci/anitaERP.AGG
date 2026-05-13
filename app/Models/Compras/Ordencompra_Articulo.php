<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;

class Ordencompra_Articulo extends Model
{
    protected $table = 'ordencompra_articulo';

    protected $fillable = [
        'ordencompra_id', 'requisicion_articulo_id', 'fechaentrega', 'articulo_id', 'cantidad', 'precio', 'moneda_id', 'cotizacion',
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
}
