<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MercadolibrePedidoLinea extends Model
{
    protected $table = 'mercadolibre_pedido_linea';

    protected $fillable = [
        'mercadolibre_pedido_id',
        'tipo',
        'sku',
        'seller_sku',
        'item_id',
        'nombre',
        'quantity',
        'unit_price',
        'full_unit_price',
        'articulo_id',
        'combinacion_id',
        'talle_id',
        'color_id',
        'orden',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'full_unit_price' => 'float',
        'articulo_id' => 'integer',
        'combinacion_id' => 'integer',
        'talle_id' => 'integer',
        'color_id' => 'integer',
        'orden' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(MercadolibrePedido::class, 'mercadolibre_pedido_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function combinacion(): BelongsTo
    {
        return $this->belongsTo(Combinacion::class, 'combinacion_id');
    }

    public function subtotal(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
