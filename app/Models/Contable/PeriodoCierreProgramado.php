<?php

namespace App\Models\Contable;

use App\Support\Contable\PeriodoContableCierreSupport;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoCierreProgramado extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EJECUTADO = 'ejecutado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADO_ERROR = 'error';

    /** Fin de día (valor de negocio; no es un HH:MM válido de HTML time). */
    public const HORA_FIN_DIA = '24:00';

    protected $table = 'contable_periodo_cierre_programado';

    protected $fillable = [
        'empresa_id',
        'anio_mes',
        'alcance',
        'fecha_ejecucion',
        'hora_ejecucion',
        'fecha_hasta',
        'estado',
        'observacion',
        'usuario_id',
        'ejecutado_en',
        'periodo_cierre_id',
        'error_mensaje',
    ];

    protected $casts = [
        'fecha_ejecucion' => 'date',
        'fecha_hasta' => 'date',
        'ejecutado_en' => 'datetime',
        'anio_mes' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function periodoCierre(): BelongsTo
    {
        return $this->belongsTo(PeriodoCierreContable::class, 'periodo_cierre_id');
    }

    public function etiquetaAlcance(): string
    {
        return PeriodoContableCierreSupport::etiquetaAlcance($this->alcance ?? '');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function horaEjecucionNormalizada(): string
    {
        $hora = trim((string) ($this->hora_ejecucion ?? ''));

        return $hora !== '' ? $hora : self::HORA_FIN_DIA;
    }

    /**
     * Momento a partir del cual el job automático puede aplicar el cierre.
     * 24:00 = fin del día (23:59:59).
     */
    public function momentoEjecucion(): ?Carbon
    {
        if ($this->fecha_ejecucion === null) {
            return null;
        }

        return self::resolverMomentoEjecucion(
            $this->fecha_ejecucion->format('Y-m-d'),
            $this->horaEjecucionNormalizada()
        );
    }

    public static function resolverMomentoEjecucion(string $fechaYmd, ?string $hora): Carbon
    {
        $fecha = Carbon::parse($fechaYmd)->startOfDay();
        $hora = trim((string) $hora);
        if ($hora === '' || $hora === self::HORA_FIN_DIA || $hora === '24:00:00') {
            return $fecha->copy()->endOfDay();
        }

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $hora, $m)) {
            return $fecha->copy()->endOfDay();
        }

        return $fecha->copy()->setTime((int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
    }

    public static function normalizarHoraEjecucion(?string $hora): string
    {
        $hora = trim((string) $hora);
        if ($hora === '' || $hora === self::HORA_FIN_DIA || $hora === '24:00:00') {
            return self::HORA_FIN_DIA;
        }

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $hora, $m)) {
            throw new \InvalidArgumentException(
                'Hora de ejecución inválida. Use HH:MM (00:00 a 23:59) o 24:00 (fin de día).'
            );
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /**
     * Botón "Aplicar ahora": disponible si la fecha de ejecución ya llegó (sin exigir la hora).
     */
    public function puedeEjecutarAhora(): bool
    {
        if ((! $this->estaPendiente() && $this->estado !== self::ESTADO_ERROR)
            || $this->fecha_ejecucion === null) {
            return false;
        }

        return $this->fecha_ejecucion->lte(now()->startOfDay());
    }

    /** El job automático ya puede correr este registro. */
    public function momentoEjecucionVencido(?Carbon $ahora = null): bool
    {
        $momento = $this->momentoEjecucion();
        if ($momento === null) {
            return false;
        }

        return ($ahora ?? now())->gte($momento);
    }
}
