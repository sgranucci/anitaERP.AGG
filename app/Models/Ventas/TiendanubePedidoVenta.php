<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiendanubePedidoVenta extends Model
{
    protected $table = 'tiendanube_pedido_venta';

    protected $fillable = [
        'tiendanube_pedido_id',
        'venta_id',
        'total',
    ];

    protected $casts = [
        'total' => 'float',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(TiendanubePedido::class, 'tiendanube_pedido_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
