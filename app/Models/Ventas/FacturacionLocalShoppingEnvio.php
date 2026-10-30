<?php

declare(strict_types=1);

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturacionLocalShoppingEnvio extends Model
{
    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ERROR = 'error';

    protected $table = 'facturacion_local_shopping_envio';

    protected $fillable = [
        'local_venta_id',
        'fecha_venta',
        'nombre_archivo',
        'ruta',
        'destinatarios',
        'estado',
        'comprobantes',
        'lineas',
        'mensaje',
        'enviado_at',
    ];

    protected $casts = [
        'fecha_venta' => 'date',
        'enviado_at' => 'datetime',
        'comprobantes' => 'integer',
        'lineas' => 'integer',
        'local_venta_id' => 'integer',
    ];

    public function localVenta(): BelongsTo
    {
        return $this->belongsTo(LocalVenta::class, 'local_venta_id');
    }
}
