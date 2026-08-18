<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReporteSueldosDefinibleEjecucion extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PROCESANDO = 'procesando';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ADVERTENCIA = 'advertencia';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDA = 'omitida';

    protected $table = 'reporte_sueldos_definible_ejecucion';

    protected $fillable = [
        'uuid',
        'reporte_sueldos_definible_id',
        'version_id',
        'dataset_id',
        'suscripcion_id',
        'ejecucion_padre_id',
        'usuario_id',
        'origen',
        'estado',
        'filtros',
        'dimensiones',
        'burst_clave',
        'burst_etiqueta',
        'resultado_hash',
        'resultado_formato',
        'resultado',
        'cantidad_filas',
        'cantidad_columnas',
        'duracion_ms',
        'memoria_pico_bytes',
        'advertencias_count',
        'advertencias',
        'error',
        'iniciada_at',
        'finalizada_at',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'version_id' => 'integer',
        'dataset_id' => 'integer',
        'suscripcion_id' => 'integer',
        'ejecucion_padre_id' => 'integer',
        'usuario_id' => 'integer',
        'filtros' => 'array',
        'dimensiones' => 'array',
        'cantidad_filas' => 'integer',
        'cantidad_columnas' => 'integer',
        'duracion_ms' => 'integer',
        'memoria_pico_bytes' => 'integer',
        'advertencias_count' => 'integer',
        'advertencias' => 'array',
        'iniciada_at' => 'datetime',
        'finalizada_at' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleVersion::class, 'version_id');
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleSuscripcion::class, 'suscripcion_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ejecucion_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'ejecucion_padre_id');
    }

    public function paridades(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleParidad::class, 'ejecucion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function resultadoDecodificado(): array
    {
        if ($this->resultado === null || $this->resultado === '') {
            return [];
        }

        $raw = (string) $this->resultado;
        if ($this->resultado_formato === 'gzip-base64-json-v1') {
            $bin = base64_decode($raw, true);
            if ($bin === false) {
                return [];
            }
            $json = gzdecode($bin);
            if ($json === false) {
                return [];
            }
            $raw = $json;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
