<?php

namespace App\Models\Stock;

/**
 * Línea de recepción Surmar: mismos registros, fillable ampliado (lote/pesos/piqueo).
 */
class RecepcionProveedorArticuloSurmar extends Recepcion_Proveedor_Articulo
{
    protected $fillable = [
        'recepcion_proveedor_id', 'ordencompra_articulo_id', 'ordencompra_articulo_sustituido_id', 'tipo_linea',
        'orden', 'penvp_orden', 'penvp_nro_interno',
        'articulo_id', 'color_id', 'talle_id', 'articulo_stock_id', 'cantidad', 'cantidad_oc', 'cantidad_stock', 'cantidad_rechazada',
        'unidadmedida_id', 'coeficienteconversion',
        'precio', 'precio_ordencompra', 'precio_solicitado', 'precio_stock',
        'fl_precio_diferencia', 'fl_cantidad_diferencia', 'fl_articulo_distinto', 'fl_cerrar_linea_oc',
        'comentario_precio', 'comentario_diferencia', 'precio_lista_proveedor',
        'moneda_id', 'cotizacion', 'descuento', 'deposito_id', 'detalle', 'motivorechazo', 'estado',
        'impuesto_id', 'incluyeimpuesto', 'centrocosto_id', 'lote_id', 'articulo_movimiento_id',
        // Surmar
        'lote_proveedor', 'certificado', 'fecha_vto', 'peso_bruto', 'peso_neto', 'cant_pieza',
        'hora_piqueo', 'piqueado_at', 'stock_etiqueta_id',
    ];

    protected $casts = [
        'fl_precio_diferencia' => 'boolean',
        'fl_cerrar_linea_oc' => 'boolean',
        'fecha_vto' => 'date',
        'piqueado_at' => 'datetime',
        'peso_bruto' => 'decimal:4',
        'peso_neto' => 'decimal:4',
        'cant_pieza' => 'decimal:4',
    ];

    public function stock_etiqueta()
    {
        return $this->belongsTo(Stock_Etiqueta::class, 'stock_etiqueta_id');
    }
}
