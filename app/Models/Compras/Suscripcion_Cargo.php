<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Moneda;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del resumen de tarjeta y su resultado de cruce contra la OC de suscripción.
 */
class Suscripcion_Cargo extends Model
{
    /** El cargo cae dentro del tope autorizado de su suscripción. */
    public const ESTADO_CONCILIADO = 'CONCILIADO';

    /** Identificado, pero por encima del tope: necesita revalidación del gerente. */
    public const ESTADO_DESVIO = 'DESVIO';

    /** No se pudo atar a ninguna suscripción vigente. */
    public const ESTADO_SIN_IDENTIFICAR = 'SIN_IDENTIFICAR';

    /** Desvío ya enviado al árbol y esperando respuesta. */
    public const ESTADO_PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';

    /** Gasto real sin OC: hay que emitir la suscripción o dar de baja el servicio. */
    public const ESTADO_REGULARIZAR = 'REGULARIZAR';

    /** Fuera del alcance del módulo (no es una suscripción). */
    public const ESTADO_DESCARTADO = 'DESCARTADO';

    protected $table = 'suscripcion_cargo';

    protected $fillable = [
        'suscripcion_conciliacion_id',
        'fecha',
        'comercio',
        'comercio_normalizado',
        'tarjeta_ult4',
        'suscripcion_tarjeta_id',
        'monto',
        'moneda_id',
        'ordencompra_id',
        'estado',
        'desvio_pct',
        'origen_match',
        'caja_movimiento_id',
        'asocio_usuario_id',
        'asociado_at',
        'observacion',
        'hash_linea',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
        'desvio_pct' => 'float',
        'asociado_at' => 'datetime',
    ];

    public function suscripcion_conciliaciones(): BelongsTo
    {
        return $this->belongsTo(Suscripcion_Conciliacion::class, 'suscripcion_conciliacion_id');
    }

    public function ordencompras(): BelongsTo
    {
        return $this->belongsTo(Ordencompra::class, 'ordencompra_id');
    }

    public function suscripcion_tarjetas(): BelongsTo
    {
        return $this->belongsTo(Suscripcion_Tarjeta::class, 'suscripcion_tarjeta_id');
    }

    public function monedas(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function asociadores(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asocio_usuario_id');
    }

    /** Cuenta para la cobertura: identificado contra una suscripción. */
    public function tieneOrden(): bool
    {
        return (int) ($this->ordencompra_id ?? 0) > 0;
    }

    public function imputado(): bool
    {
        return (int) ($this->caja_movimiento_id ?? 0) > 0;
    }
}
