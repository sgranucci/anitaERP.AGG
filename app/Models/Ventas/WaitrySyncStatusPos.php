<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class WaitrySyncStatusPos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIANDO = 'enviando';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_AGOTADO = 'agotado';

    public const ESTADO_OMITIDO = 'omitido';

    protected $table = 'waitry_sync_status_pos';

    protected $fillable = [
        'venta_id',
        'cuenta_gastronomia_id',
        'waitry_order_id',
        'empresa_id',
        'place_id',
        'estado',
        'sync_pos_ok',
        'kds_ok',
        'intentos',
        'max_intentos',
        'ultimo_error',
        'ultimo_http_code',
        'payload_json',
        'respuesta_json',
        'proximo_reintento_at',
        'enviado_at',
    ];

    protected $casts = [
        'sync_pos_ok' => 'boolean',
        'kds_ok' => 'boolean',
        'payload_json' => 'array',
        'respuesta_json' => 'array',
        'proximo_reintento_at' => 'datetime',
        'enviado_at' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cuentaGastronomia()
    {
        return $this->belongsTo(CuentaGastronomia::class, 'cuenta_gastronomia_id');
    }

    public function puedeReintentar(): bool
    {
        if ($this->estado === self::ESTADO_ENVIADO || $this->estado === self::ESTADO_OMITIDO) {
            return false;
        }

        return (int) $this->intentos < (int) $this->max_intentos;
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>
     */
    public function mediosPagoPersistidos(): array
    {
        $payload = is_array($this->payload_json) ? $this->payload_json : [];
        $medios = $payload['medios_pago'] ?? [];
        if (! is_array($medios)) {
            return [];
        }

        $out = [];
        foreach ($medios as $medio) {
            if (! is_array($medio)) {
                continue;
            }
            $out[] = $medio;
        }

        return $out;
    }
}
