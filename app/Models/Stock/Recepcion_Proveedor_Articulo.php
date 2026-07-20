<?php

namespace App\Models\Stock;

use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Impuesto;
use Illuminate\Database\Eloquent\Model;

class Recepcion_Proveedor_Articulo extends Model
{
    protected $table = 'recepcion_proveedor_articulo';

    protected $fillable = [
        'recepcion_proveedor_id', 'ordencompra_articulo_id', 'ordencompra_articulo_sustituido_id', 'tipo_linea',
        'orden', 'penvp_orden', 'penvp_nro_interno',
        'articulo_id', 'color_id', 'talle_id', 'articulo_stock_id', 'cantidad', 'cantidad_oc', 'cantidad_stock', 'cantidad_rechazada', 'unidadmedida_id', 'coeficienteconversion',
        'precio', 'precio_ordencompra', 'precio_solicitado', 'precio_stock', 'fl_precio_diferencia', 'fl_cantidad_diferencia', 'fl_articulo_distinto', 'fl_cerrar_linea_oc',
        'comentario_precio', 'comentario_diferencia', 'precio_lista_proveedor',
        'moneda_id', 'cotizacion', 'descuento', 'deposito_id', 'detalle', 'motivorechazo', 'estado',
        'impuesto_id', 'incluyeimpuesto', 'centrocosto_id', 'lote_id', 'articulo_movimiento_id',
    ];

    protected $casts = [
        'fl_precio_diferencia' => 'boolean',
        'fl_cerrar_linea_oc' => 'boolean',
    ];

    public function recepcion_proveedores()
    {
        return $this->belongsTo(Recepcion_Proveedor::class, 'recepcion_proveedor_id');
    }

    public function ordencompra_articulos()
    {
        return $this->belongsTo(Ordencompra_Articulo::class, 'ordencompra_articulo_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function talle()
    {
        return $this->belongsTo(Talle::class, 'talle_id');
    }

    public function articulo_stock()
    {
        return $this->belongsTo(Articulo::class, 'articulo_stock_id');
    }

    public function unidadesmedida()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function depositos()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function impuestos()
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function articulo_movimientos()
    {
        return $this->belongsTo(Articulo_Movimiento::class, 'articulo_movimiento_id');
    }

    public function recepcion_proveedor_partes_unicas()
    {
        return $this->hasMany(Recepcion_Proveedor_ParteUnica::class, 'recepcion_proveedor_articulo_id');
    }
}
