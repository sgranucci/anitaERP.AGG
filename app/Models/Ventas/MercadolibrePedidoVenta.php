<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MercadolibrePedidoVenta extends Model
{
    protected $table = 'mercadolibre_pedido_venta';

    protected $fillable = [
        'mercadolibre_pedido_id',
        'venta_id',
        'total',
    ];

    protected $casts = [
        'venta_id' => 'integer',
        'total' => 'float',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(MercadolibrePedido::class, 'mercadolibre_pedido_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
