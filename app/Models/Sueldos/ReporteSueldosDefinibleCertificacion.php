<?php

namespace App\Models\Sueldos;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteSueldosDefinibleCertificacion extends Model
{
    public const ESTADO_CERTIFICADA = 'certificada';

    public const ESTADO_REVOCADA = 'revocada';

    public const NOMINA_NORMAL = 'normal';

    public const NOMINA_CONFIDENCIAL = 'confidencial';

    public const NOMINA_AMBOS = 'ambos';

    protected $table = 'reporte_sueldos_definible_certificacion';

    protected $fillable = [
        'reporte_sueldos_definible_id',
        'liquidacion_id',
        'ejecucion_id',
        'nomina',
        'estado',
        'max_diferencia',
        'columnas_ok',
        'columnas_dif',
        'usuario_id',
        'certificada_at',
        'comentario',
    ];

    protected $casts = [
        'reporte_sueldos_definible_id' => 'integer',
        'liquidacion_id' => 'integer',
        'ejecucion_id' => 'integer',
        'max_diferencia' => 'float',
        'columnas_ok' => 'integer',
        'columnas_dif' => 'integer',
        'usuario_id' => 'integer',
        'certificada_at' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinible::class, 'reporte_sueldos_definible_id');
    }

    public function ejecucion(): BelongsTo
    {
        return $this->belongsTo(ReporteSueldosDefinibleEjecucion::class, 'ejecucion_id');
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion_Sueldos::class, 'liquidacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function esVigente(): bool
    {
        return $this->estado === self::ESTADO_CERTIFICADA;
    }

    /**
     * @return array<string, string>
     */
    public static function nominas(): array
    {
        return [
            self::NOMINA_NORMAL => 'Nómina normal',
            self::NOMINA_CONFIDENCIAL => 'Nómina confidencial',
            self::NOMINA_AMBOS => 'Ambas nóminas',
        ];
    }
}
