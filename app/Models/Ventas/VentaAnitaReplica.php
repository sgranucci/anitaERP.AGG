<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaAnitaReplica extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OK = 'ok';

    protected $table = 'venta_anita_replica';

    protected $fillable = [
        'venta_id',
        'pedido_id',
        'estado',
        'intentos',
        'error_mensaje',
        'payload_anita',
        'payload_vencae',
        'archivos_estado',
        'ultimo_intento_at',
        'synced_at',
    ];

    protected $casts = [
        'payload_anita' => 'array',
        'payload_vencae' => 'array',
        'archivos_estado' => 'array',
        'ultimo_intento_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function estaAbierto(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_ERROR], true);
    }
}
