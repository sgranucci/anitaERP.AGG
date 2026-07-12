<?php

namespace App\Models\Caja\Bingo;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class BingoConceptoRendicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    public const ESTADO_ANULADO = 'anulado';

    public const SIGNO_SUMA = '+';

    public const SIGNO_RESTA = '-';

    public const BASE_TOTAL_CARTONES = 'total_cartones';

    public const BASE_SALDO_ANTERIOR = 'saldo_anterior';

    public const BASE_MONTO_COMISION = 'monto_comision';

    public const BASE_MANUAL = 'manual';

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumEstado = [
        ['valor' => self::ESTADO_ACTIVO, 'nombre' => 'Activo'],
        ['valor' => self::ESTADO_SUSPENDIDO, 'nombre' => 'Suspendido'],
        ['valor' => self::ESTADO_ANULADO, 'nombre' => 'Anulado'],
    ];

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumSigno = [
        ['valor' => self::SIGNO_SUMA, 'nombre' => 'Suma (+)'],
        ['valor' => self::SIGNO_RESTA, 'nombre' => 'Resta (-)'],
    ];

    /** @var array<int, array{valor: string, nombre: string}> */
    public static array $enumBaseCalculo = [
        ['valor' => self::BASE_TOTAL_CARTONES, 'nombre' => 'Total cartones vendidos'],
        ['valor' => self::BASE_SALDO_ANTERIOR, 'nombre' => 'Saldo acumulado anterior'],
        ['valor' => self::BASE_MONTO_COMISION, 'nombre' => 'Monto de comisión acumulada'],
        ['valor' => self::BASE_MANUAL, 'nombre' => 'Monto manual en rendición'],
    ];

    protected $table = 'bingo_concepto_rendicion';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'codigo_anita',
        'signo',
        'detalle',
        'porcentaje',
        'base_calculo',
        'monto_fijo',
        'es_saldo_rendicion',
        'orden',
        'estado',
    ];

    protected $casts = [
        'codigo_anita' => 'integer',
        'porcentaje' => 'decimal:4',
        'monto_fijo' => 'decimal:2',
        'es_saldo_rendicion' => 'boolean',
        'orden' => 'integer',
    ];

    protected $attributes = [
        'estado' => self::ESTADO_ACTIVO,
        'signo' => self::SIGNO_RESTA,
        'base_calculo' => self::BASE_TOTAL_CARTONES,
        'orden' => 0,
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $this->estado) {
                return $row['nombre'];
            }
        }

        return (string) ($this->estado ?? '');
    }

    public function getSignoLabelAttribute(): string
    {
        foreach (self::$enumSigno as $row) {
            if ($row['valor'] === $this->signo) {
                return $row['nombre'];
            }
        }

        return (string) ($this->signo ?? '');
    }

    public function getBaseCalculoLabelAttribute(): string
    {
        foreach (self::$enumBaseCalculo as $row) {
            if ($row['valor'] === $this->base_calculo) {
                return $row['nombre'];
            }
        }

        return (string) ($this->base_calculo ?? '');
    }
}
