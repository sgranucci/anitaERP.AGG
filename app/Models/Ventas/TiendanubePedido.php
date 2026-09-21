<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiendanubePedido extends Model
{
    protected $table = 'tiendanube_pedido';

    protected $fillable = [
        'store_id',
        'tiendanube_order_id',
        'order_number',
        'payment_status',
        'status',
        'estado_erp',
        'total',
        'currency',
        'customer_name',
        'customer_email',
        'customer_doc',
        'customer_doc_type',
        'gateway',
        'gateway_name',
        'paid_at',
        'created_at_tn',
        'customer_json',
        'shipping_json',
        'payment_json',
        'payload_json',
        'venta_id',
        'cliente_id',
        'puntoventa_id_sugerido',
        'deposito_id_sugerido',
        'error_mensaje',
        'synced_at',
        'facturado_at',
        'facturado_por_usuario_id',
    ];

    protected $casts = [
        'tiendanube_order_id' => 'integer',
        'total' => 'float',
        'paid_at' => 'datetime',
        'created_at_tn' => 'datetime',
        'synced_at' => 'datetime',
        'facturado_at' => 'datetime',
        'customer_json' => 'array',
        'shipping_json' => 'array',
        'payment_json' => 'array',
        'payload_json' => 'array',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(TiendanubePedidoLinea::class, 'tiendanube_pedido_id')->orderBy('orden');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(TiendanubePedidoVenta::class, 'tiendanube_pedido_id')->orderBy('id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function puntoventaSugerido(): BelongsTo
    {
        return $this->belongsTo(Puntoventa::class, 'puntoventa_id_sugerido');
    }

    public function depositoSugerido(): BelongsTo
    {
        return $this->belongsTo(Depmae::class, 'deposito_id_sugerido');
    }

    public function facturadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'facturado_por_usuario_id');
    }

    public function estaFacturado(): bool
    {
        if ($this->estado_erp === 'parcial') {
            return false;
        }

        return $this->estado_erp === 'facturado' || (int) ($this->venta_id ?? 0) > 0;
    }

    /**
     * Ya hay cantidad facturada o el pedido quedó a medio cubrir.
     * El sync no debe borrar las líneas (perdería lo ya facturado).
     */
    public function preservaStagingFacturado(): bool
    {
        if ($this->estaFacturado() || $this->estado_erp === 'parcial') {
            return true;
        }
        if ($this->relationLoaded('lineas')) {
            return $this->lineas->contains(
                static fn (TiendanubePedidoLinea $linea): bool => (float) $linea->cantidad_facturada > 0.0001
            );
        }

        return $this->lineas()->where('cantidad_facturada', '>', 0)->exists();
    }

    public function tieneAlgoFacturado(): bool
    {
        if ($this->relationLoaded('lineas')) {
            $porLinea = $this->lineas->contains(
                static fn (TiendanubePedidoLinea $linea): bool => (float) $linea->cantidad_facturada > 0.0001
            );
        } else {
            $porLinea = $this->lineas()->where('cantidad_facturada', '>', 0)->exists();
        }

        return $porLinea || (int) ($this->venta_id ?? 0) > 0;
    }

    public function totalPendiente(): float
    {
        $this->loadMissing('lineas');
        $total = 0.0;
        foreach ($this->lineas as $linea) {
            $total += $linea->cantidadPendiente() * (float) $linea->price;
        }

        return round($total, 2);
    }

    public function cubiertoPorCompleto(): bool
    {
        $this->loadMissing('lineas');
        $conCantidad = $this->lineas->filter(
            static fn (TiendanubePedidoLinea $linea): bool => abs((float) $linea->quantity) > 0.0001
        );
        if ($conCantidad->isEmpty()) {
            return false;
        }

        return $conCantidad->every(
            static fn (TiendanubePedidoLinea $linea): bool => $linea->estaCubierta()
        );
    }

    public function estaPagado(): bool
    {
        return strtolower((string) $this->payment_status) === 'paid';
    }
}
