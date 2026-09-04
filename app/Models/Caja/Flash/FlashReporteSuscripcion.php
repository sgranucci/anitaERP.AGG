<?php

namespace App\Models\Caja\Flash;

use App\Models\Seguridad\Usuario;
use App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport;
use App\Support\Caja\Flash\FlashReporteAggSmtpReintentoSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashReporteSuscripcion extends Model
{
    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_SEMANAL = 'semanal';

    public const PERIODICIDAD_DIARIA = 'diaria';

    public const PERIODO_MES_ANTERIOR = 'mes_anterior';

    public const PERIODO_MES_ACTUAL = 'mes_actual';

    public const PERIODO_FIJO = 'fijo';

    public const ESTADO_OK = 'ok';

    public const ESTADO_ERROR = 'error';

    public const ESTADO_OMITIDA = 'omitida';

    protected $table = 'flash_reporte_suscripcion';

    protected $fillable = [
        'nombre',
        'activo',
        'periodicidad',
        'dia_mes',
        'dia_semana',
        'hora',
        'periodo_relativo',
        'mes_fijo',
        'perfil_vista',
        'destinatarios',
        'mensaje',
        'ultima_ejecucion',
        'ultimo_estado',
        'ultimo_mensaje',
        'usuario_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dia_mes' => 'integer',
        'dia_semana' => 'integer',
        'ultima_ejecucion' => 'datetime',
    ];

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
            self::PERIODICIDAD_DIARIA => 'Diaria (MTD del mes en curso)',
            self::PERIODICIDAD_SEMANAL => 'Semanal (un día de la semana)',
            self::PERIODICIDAD_MENSUAL => 'Mensual (un día fijo del mes)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function periodosRelativos(): array
    {
        return [
            self::PERIODO_MES_ACTUAL => 'Mes en curso (hasta fecha de producción = ayer)',
            self::PERIODO_MES_ANTERIOR => 'Mes anterior completo',
            self::PERIODO_FIJO => 'El mes fijo cargado en el envío',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function perfilesVista(): array
    {
        return FlashReporteAggPerfilVistaSupport::etiquetas();
    }

    public function perfilVistaTexto(): string
    {
        $perfil = FlashReporteAggPerfilVistaSupport::normalizar($this->perfil_vista ?? null);

        return FlashReporteAggPerfilVistaSupport::etiquetas()[$perfil] ?? $perfil;
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

    public function esperaReintentoSmtp(): bool
    {
        return $this->ultimo_estado === self::ESTADO_ERROR
            && FlashReporteAggSmtpReintentoSupport::esErrorTransporte((string) $this->ultimo_mensaje);
    }

    public function minutosReintentoSmtp(): int
    {
        return FlashReporteAggSmtpReintentoSupport::esperaMinutos();
    }

    public function periodicidadTexto(): string
    {
        return match ($this->periodicidad) {
            self::PERIODICIDAD_SEMANAL => 'Cada '.(self::diasSemana()[(int) $this->dia_semana] ?? 'lunes').' a las '.$this->hora,
            self::PERIODICIDAD_MENSUAL => 'El día '.(int) $this->dia_mes.' de cada mes a las '.$this->hora,
            default => 'Todos los días a las '.$this->hora,
        };
    }
};
