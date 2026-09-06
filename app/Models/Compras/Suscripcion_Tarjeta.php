<?php

namespace App\Models\Compras;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarjeta corporativa con la que se pagan las suscripciones.
 */
class Suscripcion_Tarjeta extends Model
{
    protected $table = 'suscripcion_tarjeta';

    protected $fillable = [
        'empresa_id',
        'ult4',
        'etiqueta',
        'emisor',
        'area',
        'centrocosto_id',
        'responsable_usuario_id',
        'moneda_id',
        'cuentacaja_id',
        'tipotransaccion_caja_id',
        'limite_mensual',
        'activo',
        'observacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'limite_mensual' => 'float',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function responsables(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_usuario_id');
    }

    public function monedas(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function cuentacajas(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Caja\Cuentacaja::class, 'cuentacaja_id');
    }

    public function tipotransaccioncajas(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Caja\Tipotransaccion_Caja::class, 'tipotransaccion_caja_id');
    }

    /** Puede imputar en Ingresos y egresos sin que haya que completar nada más. */
    public function imputable(): bool
    {
        return (int) ($this->cuentacaja_id ?? 0) > 0 && (int) ($this->tipotransaccion_caja_id ?? 0) > 0;
    }

    public function etiquetaCompleta(): string
    {
        return trim((string) $this->etiqueta).' ••'.$this->ult4;
    }
}
