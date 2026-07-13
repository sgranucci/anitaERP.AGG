<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Moneda;
use App\Models\Stock\Depmae;
use App\Models\Seguridad\Usuario;
use App\Support\Ventas\PedidoEstadosInterforming;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera de pedido INTERFORMING (tabla pedido + columnas IF).
 * No usar desde el ABM Bierzo.
 */
class PedidoInterforming extends Pedido
{
    protected $table = 'pedido';

    protected $fillable = [
        'fecha', 'fechaentrega', 'cliente_id', 'condicionventa_id', 'vendedor_id',
        'transporte_id', 'mventa_id', 'estado', 'usuario_id', 'leyenda', 'descuento',
        'descuentointegrado', 'cliente_entrega_id', 'lugarentrega', 'codigo',
        'estadopedido', 'zonavta_id',
        // Interforming
        'orden_compra', 'deposito_id', 'moneda_id', 'cotizacion', 'razon_suspension',
        'en_stock', 'tipo_comprobante', 'letra_comprobante', 'sucursal_comprobante',
        'numero_comprobante',
    ];

    public function pedido_articulos(): HasMany
    {
        return $this->hasMany(PedidoArticuloInterforming::class, 'pedido_id')
            ->with('articulos')
            ->with('monedas')
            ->orderBy('numeroitem');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function etiquetaEstado(): string
    {
        return PedidoEstadosInterforming::etiquetaCabecera($this->estadopedido);
    }
}
