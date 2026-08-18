<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Lote;
use App\Traits\Ventas\Remito_ArticuloTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Remito_Articulo extends Model implements Auditable
{
    use Remito_ArticuloTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'remito_id', 'articulo_id', 'unidadmedida_id', 'numeroitem', 'caja', 'pieza', 'kilo',
        'precio', 'listaprecio_id', 'incluyeimpuesto', 'moneda_id', 'descuentoventa_id',
        'descuento', 'descuentointegrado', 'lote_id', 'observacion', 'estado', 'pedido_articulo_id',
    ];

    protected $table = 'remito_articulo';

    public function lotes()
    {
        return $this->belongsTo(Lote::class, 'lote_id', 'id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id', 'id')
            ->with('lineas')
            ->with('mventas')
            ->with('unidadesdemedidas');
    }

    public function remitos()
    {
        return $this->belongsTo(Remito::class, 'remito_id', 'id');
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

    public function pedido_articulos()
    {
        return $this->belongsTo(Pedido_Articulo::class, 'pedido_articulo_id');
    }
}
