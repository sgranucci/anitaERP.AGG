<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Contrato_Venta;
use App\Models\Ventas\Contrato_Venta_Periodo;
use Carbon\Carbon;

/**
 * Periodicidad, períodos a facturar y estados del abono/contrato de venta.
 */
final class ContratoVentaSupport
{
    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    public const ESTADO_FINALIZADO = 'finalizado';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_ACTIVO,
        self::ESTADO_SUSPENDIDO,
        self::ESTADO_FINALIZADO,
    ];

    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_BIMESTRAL = 'bimestral';

    public const PERIODICIDAD_TRIMESTRAL = 'trimestral';

    public const PERIODICIDAD_SEMESTRAL = 'semestral';

    public const PERIODICIDAD_ANUAL = 'anual';

    /** @var list<string> */
    public const PERIODICIDADES = [
        self::PERIODICIDAD_MENSUAL,
        self::PERIODICIDAD_BIMESTRAL,
        self::PERIODICIDAD_TRIMESTRAL,
        self::PERIODICIDAD_SEMESTRAL,
        self::PERIODICIDAD_ANUAL,
    ];

    public const PERIODO_PENDIENTE = 'pendiente';

    public const PERIODO_FACTURADO = 'facturado';

    public const PERIODO_ANULADO = 'anulado';

    public static function normalizarEstado(string $estado): string
    {
        $estado = strtolower(trim($estado));

        return in_array($estado, self::ESTADOS, true) ? $estado : self::ESTADO_ACTIVO;
    }

    public static function normalizarPeriodicidad(string $periodicidad): string
    {
        $periodicidad = strtolower(trim($periodicidad));

        return in_array($periodicidad, self::PERIODICIDADES, true)
            ? $periodicidad
            : self::PERIODICIDAD_MENSUAL;
    }

    /**
     * @return array{desde: string, hasta: string, etiqueta: string}
     */
    public static function periodoParaFecha(string $fechaYmd, string $periodicidad = self::PERIODICIDAD_MENSUAL): array
    {
        $fecha = Carbon::parse(substr($fechaYmd, 0, 10))->startOfDay();
        $periodicidad = self::normalizarPeriodicidad($periodicidad);

        return match ($periodicidad) {
            self::PERIODICIDAD_BIMESTRAL => self::rangoMeses($fecha, 2),
            self::PERIODICIDAD_TRIMESTRAL => self::rangoMeses($fecha, 3),
            self::PERIODICIDAD_SEMESTRAL => self::rangoMeses($fecha, 6),
            self::PERIODICIDAD_ANUAL => [
                'desde' => $fecha->copy()->startOfYear()->toDateString(),
                'hasta' => $fecha->copy()->endOfYear()->toDateString(),
                'etiqueta' => $fecha->format('Y'),
            ],
            default => [
                'desde' => $fecha->copy()->startOfMonth()->toDateString(),
                'hasta' => $fecha->copy()->endOfMonth()->toDateString(),
                'etiqueta' => ConceptoVentaPlantillaMotor::formatearFecha($fecha->copy()->startOfMonth()->toDateString())
                    .' al '
                    .ConceptoVentaPlantillaMotor::formatearFecha($fecha->copy()->endOfMonth()->toDateString()),
            ],
        };
    }

    /**
     * @return array{desde: string, hasta: string, etiqueta: string}
     */
    private static function rangoMeses(Carbon $fecha, int $meses): array
    {
        $bloque = (int) floor(($fecha->month - 1) / $meses) * $meses + 1;
        $desde = $fecha->copy()->month($bloque)->startOfMonth();
        $hasta = $desde->copy()->addMonths($meses - 1)->endOfMonth();

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'etiqueta' => ConceptoVentaPlantillaMotor::formatearFecha($desde->toDateString())
                .' al '
                .ConceptoVentaPlantillaMotor::formatearFecha($hasta->toDateString()),
        ];
    }

    public static function valorPeriodoTag(array $periodo): string
    {
        return ($periodo['desde'] ?? '').'|'.($periodo['hasta'] ?? '');
    }

    public static function estaVigenteEn(Contrato_Venta $contrato, string $fechaYmd): bool
    {
        if (self::normalizarEstado((string) $contrato->estado) !== self::ESTADO_ACTIVO) {
            return false;
        }
        $fecha = substr($fechaYmd, 0, 10);
        $desde = $contrato->vigencia_desde?->format('Y-m-d');
        $hasta = $contrato->vigencia_hasta?->format('Y-m-d');
        if ($desde && $fecha < $desde) {
            return false;
        }
        if ($hasta && $fecha > $hasta) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public static function datosFijosComoValores(Contrato_Venta $contrato): array
    {
        if (! $contrato->relationLoaded('datos')) {
            $contrato->load('datos');
        }
        $out = [];
        foreach ($contrato->datos as $dato) {
            $clave = ConceptoVentaPlantillaMotor::normalizarClave((string) $dato->clave);
            if ($clave === '') {
                continue;
            }
            $out[$clave] = trim((string) ($dato->valor ?? ''));
        }

        return $out;
    }

    public static function periodoYaFacturado(int $contratoId, string $desde, string $hasta): bool
    {
        return Contrato_Venta_Periodo::query()
            ->where('contrato_venta_id', $contratoId)
            ->where('periodo_desde', $desde)
            ->where('periodo_hasta', $hasta)
            ->where('estado', self::PERIODO_FACTURADO)
            ->exists();
    }

    public static function diasParaVencer(?Contrato_Venta $contrato, ?string $hoyYmd = null): ?int
    {
        if ($contrato === null || $contrato->vigencia_hasta === null) {
            return null;
        }
        $hoy = Carbon::parse($hoyYmd ?: date('Y-m-d'))->startOfDay();
        $hasta = $contrato->vigencia_hasta->copy()->startOfDay();

        return (int) $hoy->diffInDays($hasta, false);
    }
}
