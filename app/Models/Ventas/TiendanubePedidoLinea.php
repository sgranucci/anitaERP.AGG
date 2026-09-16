<?php

namespace App\Models\Ventas;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePedidoLinea extends Model
{
    protected $table = 'tiendanube_pedido_linea';

    protected $fillable = [
        'tiendanube_pedido_id',
        'tipo',
        'sku',
        'nombre',
        'quantity',
        'price',
        'variant_id',
        'product_id',
        'articulo_id',
        'combinacion_id',
        'talle_id',
        'color_id',
        'orden',
    ];

    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
        'variant_id' => 'integer',
        'product_id' => 'integer',
        'articulo_id' => 'integer',
        'combinacion_id' => 'integer',
        'talle_id' => 'integer',
        'color_id' => 'integer',
        'orden' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(TiendanubePedido::class, 'tiendanube_pedido_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function combinacion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Stock\Combinacion::class, 'combinacion_id');
    }

    public function talle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Stock\Talle::class, 'talle_id');
    }

    public function subtotal(): float
    {
        return round((float) $this->quantity * (float) $this->price, 4);
    }
}
