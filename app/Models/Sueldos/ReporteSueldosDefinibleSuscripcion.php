<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ReporteSueldosDefinibleSuscripcion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_SEMANAL = 'semanal';

    public const PERIODICIDAD_DIARIA = 'diaria';

    public const PERIODO_ULTIMA_LIQUIDACION = 'ultima_liquidacion';

    public const PERIODO_FIJO = 'fijo';

    public const BURST_NINGUNA = 'ninguna';

    public const BURST_CENTROCOSTO = 'centrocosto';

    public const BURST_LUGARTRABAJO = 'lugartrabajo';

    public const BURST_AGRUPAMIENTO = 'agrupamiento';

    public const BURST_EMPLEADO = 'empleado';

    protected $table = 'reporte_sueldos_definible_suscripcion';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'nombre',
        'usuario_id',
        'email',
        'destinatarios',
        'usuario_ids',
        'formato',
        'activo',
        'periodicidad',
        'dia_mes',
        'dia_semana',
        'hora',
        'periodo_relativo',
        'publicar',
        'solo_si_alertas',
        'burst_dimension',
        'filtros_default',
        'mensaje',
        'ultima_ejecucion',
        'next_run_at',
        'lease_until',
        'lease_token',
        'ultimo_estado',
        'ultimo_mensaje',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'usuario_id' => 'integer',
        'activo' => 'boolean',
        'publicar' => 'boolean',
        'solo_si_alertas' => 'boolean',
        'dia_mes' => 'integer',
        'dia_semana' => 'integer',
        'usuario_ids' => 'array',
        'filtros_default' => 'array',
        'ultima_ejecucion' => 'datetime',
        'next_run_at' => 'datetime',
        'lease_until' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function destinatariosBurst(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleSuscripcionDestinatario::class, 'suscripcion_id');
    }

    public function envios(): HasMany
    {
        return $this->hasMany(ReporteSueldosDefinibleEnvio::class, 'suscripcion_id');
    }

    /**
     * @return array<string, string>
     */
    public static function periodicidades(): array
    {
        return [
            self::PERIODICIDAD_MENSUAL => 'Mensual',
            self::PERIODICIDAD_SEMANAL => 'Semanal',
            self::PERIODICIDAD_DIARIA => 'Diaria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dimensionesBurst(): array
    {
        return [
            self::BURST_NINGUNA => 'Sin segmentar',
            self::BURST_CENTROCOSTO => 'Un archivo por centro de costo',
            self::BURST_LUGARTRABAJO => 'Un archivo por lugar de trabajo',
            self::BURST_AGRUPAMIENTO => 'Un archivo por agrupamiento',
            self::BURST_EMPLEADO => 'Un archivo por empleado (email del legajo)',
        ];
    }
}
