<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class MercadolibrePedido extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'mercadolibre_pedido';

    protected $fillable = [
        'seller_id',
        'order_id',
        'pack_id',
        'status',
        'status_detail',
        'estado_erp',
        'es_full',
        'total',
        'currency',
        'buyer_nickname',
        'buyer_doc',
        'buyer_doc_type',
        'buyer_name',
        'payment_id',
        'billing_info_id',
        'letra_sugerida',
        'paid_at',
        'date_created_ml',
        'buyer_json',
        'billing_json',
        'payments_json',
        'shipping_json',
        'payload_json',
        'venta_id',
        'cliente_id',
        'puntoventa_id_sugerido',
        'deposito_id_sugerido',
        'error_mensaje',
        'synced_at',
        'facturado_at',
        'facturado_por_usuario_id',
        'fiscal_publicado_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'pack_id' => 'integer',
        'es_full' => 'boolean',
        'total' => 'float',
        'paid_at' => 'datetime',
        'date_created_ml' => 'datetime',
        'synced_at' => 'datetime',
        'facturado_at' => 'datetime',
        'fiscal_publicado_at' => 'datetime',
        'buyer_json' => 'array',
        'billing_json' => 'array',
        'payments_json' => 'array',
        'shipping_json' => 'array',
        'payload_json' => 'array',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(MercadolibrePedidoLinea::class, 'mercadolibre_pedido_id')->orderBy('orden');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(MercadolibrePedidoVenta::class, 'mercadolibre_pedido_id')->orderBy('id');
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
        return $this->estado_erp === 'facturado' || (int) ($this->venta_id ?? 0) > 0;
    }

    public function estaPagado(): bool
    {
        $status = strtolower(trim((string) $this->status));

        return in_array($status, ['paid', 'confirmed'], true) || $this->paid_at !== null;
    }

    public function destinoFiscalId(): string
    {
        if ($this->pack_id) {
            return (string) $this->pack_id;
        }

        return (string) $this->order_id;
    }
}
