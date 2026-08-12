<?php

namespace App\Models\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Envío programado de un informe definible por mail (distribución automática).
 */
class ReporteContableSuscripcion extends Model
{
    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_SEMANAL = 'semanal';

    public const PERIODICIDAD_DIARIA = 'diaria';

    public const PERIODO_MES_ANTERIOR = 'mes_anterior';

    public const PERIODO_MES_ACTUAL = 'mes_actual';

    public const PERIODO_FIJO = 'fijo';

    public const FORMATO_PDF = 'pdf';

    public const FORMATO_EXCEL = 'excel';

    public const FORMATO_AMBOS = 'ambos';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDA = 'omitida';

    protected $table = 'reporte_contable_suscripcion';

    protected $fillable = [
        'reporte_contable_id',
        'nombre',
        'activo',
        'periodicidad',
        'dia_mes',
        'dia_semana',
        'hora',
        'filtros',
        'periodo_relativo',
        'formato',
        'publicar',
        'solo_si_alertas',
        'destinatarios',
        'usuario_ids',
        'mensaje',
        'ultima_ejecucion',
        'ultimo_estado',
        'ultimo_mensaje',
        'usuario_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'publicar' => 'boolean',
        'solo_si_alertas' => 'boolean',
        'filtros' => 'array',
        'usuario_ids' => 'array',
        'dia_mes' => 'integer',
        'dia_semana' => 'integer',
        'ultima_ejecucion' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteContable::class, 'reporte_contable_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return array<string, string>
     */
    public static function periodicidades(): array
    {
        return [
            self::PERIODICIDAD_MENSUAL => 'Mensual (un día fijo del mes)',
            self::PERIODICIDAD_SEMANAL => 'Semanal (un día de la semana)',
            self::PERIODICIDAD_DIARIA => 'Diaria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function periodosRelativos(): array
    {
        return [
            self::PERIODO_MES_ANTERIOR => 'Mes anterior al envío',
            self::PERIODO_MES_ACTUAL => 'Mes en curso',
            self::PERIODO_FIJO => 'El período guardado en los filtros',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formatos(): array
    {
        return [
            self::FORMATO_PDF => 'PDF',
            self::FORMATO_EXCEL => 'Excel',
            self::FORMATO_AMBOS => 'PDF + Excel',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function diasSemana(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    public function periodicidadTexto(): string
    {
        return match ($this->periodicidad) {
            self::PERIODICIDAD_SEMANAL => 'Cada '.(self::diasSemana()[(int) $this->dia_semana] ?? 'lunes').' a las '.$this->hora,
            self::PERIODICIDAD_DIARIA => 'Todos los días a las '.$this->hora,
            default => 'El día '.(int) $this->dia_mes.' de cada mes a las '.$this->hora,
        };
    }
}
