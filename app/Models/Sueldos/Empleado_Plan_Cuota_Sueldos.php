<?php

namespace App\Models\Sueldos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Plan de cuotas de un empleado: un concepto que se liquida N veces y cae solo.
 */
class Empleado_Plan_Cuota_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_SUSPENDIDA = 'suspendida';

    public const ESTADO_FINALIZADA = 'finalizada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADOS = [
        self::ESTADO_ACTIVA => 'Activa',
        self::ESTADO_SUSPENDIDA => 'Suspendida',
        self::ESTADO_FINALIZADA => 'Finalizada',
        self::ESTADO_CANCELADA => 'Cancelada',
    ];

    public const TIPO_FIJO = 'fijo';

    public const TIPO_FORMULA = 'formula';

    protected $table = 'empleado_plan_cuota_sueldos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'concepto_id',
        'descripcion',
        'tipo_valor',
        'cuota_valor',
        'cuota_formula',
        'importe_total',
        'cuotas_totales',
        'cuotas_liquidadas',
        'periodo_inicio',
        'corridas_afecta',
        'estado',
        'observacion',
        'usuario_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_id' => 'integer',
        'concepto_id' => 'integer',
        'cuota_valor' => 'decimal:2',
        'importe_total' => 'decimal:2',
        'cuotas_totales' => 'integer',
        'cuotas_liquidadas' => 'integer',
        'periodo_inicio' => 'integer',
        'corridas_afecta' => 'array',
        'usuario_id' => 'integer',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto_Sueldos::class, 'concepto_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Empleado_Plan_Cuota_Movimiento_Sueldos::class, 'plan_id');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }

    /** Cuotas que restan por liquidar. */
    public function cuotasRestantes(): int
    {
        return max(0, (int) $this->cuotas_totales - (int) $this->cuotas_liquidadas);
    }

    /** Tipos de corrida donde descuenta (por defecto solo mensual). */
    public function corridasAfectadas(): array
    {
        $c = $this->corridas_afecta;

        return (is_array($c) && $c !== []) ? array_map('strval', $c) : ['mensual'];
    }

    /**
     * ¿Corresponde liquidar una cuota en esta corrida/período?
     */
    public function aplicaEn(int $periodo, string $tipoCorrida): bool
    {
        return $this->estado === self::ESTADO_ACTIVA
            && $this->cuotasRestantes() > 0
            && (int) $this->periodo_inicio <= $periodo
            && in_array($tipoCorrida, $this->corridasAfectadas(), true);
    }
}
