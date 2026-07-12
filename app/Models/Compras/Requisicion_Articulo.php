<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Requisicion_Articulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'requisicion_id', 'fechaentrega', 'articulo_id', 'cantidad', 'cantidadentregada', 'precio', 'moneda_id', 'cantidadalternativa',
        'detalle', 'centrocostodestino_id', 'preciooriginal', 'motivoahorro', 'partidagasto_id', 'capex_id',
        'precio_origen_etiqueta',
        'anita_nro_interno', 'anita_nro_orden',
    ];

    protected $table = 'requisicion_articulo';

    public function requisiciones()
    {
        return $this->belongsTo(Requisicion::class, 'requisicion_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function cambiosArticulo()
    {
        return $this->hasMany(RequisicionArticuloCambio::class, 'requisicion_articulo_id');
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
        return $this->belongsTo(Partidagasto::class, 'partidagasto_id')->with('articulos');
    }

    public function capexs()
    {
        return $this->belongsTo(Capex::class, 'capex_id');
    }
}
