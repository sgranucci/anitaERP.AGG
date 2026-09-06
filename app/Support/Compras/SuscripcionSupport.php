<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use Carbon\Carbon;

/**
 * Estados y helpers de negocio del módulo Suscripciones (OC contrato).
 */
final class SuscripcionSupport
{
    public const PERIODICIDAD_MENSUAL = 'mensual';

    public const PERIODICIDAD_ANUAL = 'anual';

    public const ESTADO_VIGENTE = 'vigente';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_RECHAZADA = 'rechazada';

    /** Vigente, pero con un cargo conciliado por encima del tope sin resolver. */
    public const ESTADO_DESVIO = 'desvio';

    public const TOLERANCIA_DEFAULT_PCT = 10.0;

    public const AVISO_DIAS_DEFAULT = '60,30,15';

    /**
     * Áreas solicitantes del circuito (misma lista en solicitud y maestros).
     *
     * @return list<string>
     */
    public static function areas(): array
    {
        return [
            'Sistemas',
            'Marketing',
            'Logística',
            'Mantenimiento',
            'Administración',
            'Tesorería',
            'VIP',
            'Máquinas',
            'Técnica',
            'Capital Humano',
            'Legales',
            'Seguridad',
            'Gastronomía',
        ];
    }

    /** @return list<string> */
    public static function periodicidades(): array
    {
        return [self::PERIODICIDAD_MENSUAL, self::PERIODICIDAD_ANUAL];
    }

    public static function etiquetaPeriodicidad(?string $valor): string
    {
        return match (strtolower(trim((string) $valor))) {
            self::PERIODICIDAD_ANUAL => 'Anual',
            default => 'Mensual',
        };
    }

    public static function normalizarPeriodicidad(?string $valor): string
    {
        $v = strtolower(trim((string) $valor));

        return $v === self::PERIODICIDAD_ANUAL
            ? self::PERIODICIDAD_ANUAL
            : self::PERIODICIDAD_MENSUAL;
    }

    /**
     * Tope autorizado por cargo = monto período × (1 + tolerancia%).
     */
    public static function topeAutorizado(float $montoPeriodo, float $toleranciaPct): float
    {
        $tol = max(0.0, $toleranciaPct);

        return round($montoPeriodo * (1 + $tol / 100), 4);
    }

    /**
     * Mapea el estado de negocio del módulo a partir de la OC.
     */
    public static function estadoNegocio(Ordencompra $oc, ?Carbon $hoy = null): string
    {
        $hoy = $hoy?->copy()->startOfDay() ?? Carbon::today();

        if ((bool) ($oc->suscripcion_borrador ?? false)) {
            return self::ESTADO_BORRADOR;
        }

        $estadoOc = strtoupper(trim((string) ($oc->estadoordencompra ?? '')));
        if ($estadoOc === OrdencompraEstados::SUSPENDIDA || $estadoOc === OrdencompraEstados::CERRADA) {
            return self::ESTADO_RECHAZADA;
        }

        if ($estadoOc !== OrdencompraEstados::APROBADA && $estadoOc !== OrdencompraEstados::CUMPLIDA) {
            return self::ESTADO_PENDIENTE;
        }

        $hasta = $oc->contrato_vigencia_hasta;
        if ($hasta) {
            $fin = Carbon::parse($hasta)->startOfDay();
            if ($fin->lt($hoy)) {
                return self::ESTADO_VENCIDA;
            }
        }

        if ((bool) ($oc->suscripcion_desvio_abierto ?? false)) {
            return self::ESTADO_DESVIO;
        }

        return self::ESTADO_VIGENTE;
    }

    /** @return array<string, string> valor => etiqueta, para selectores de filtro */
    public static function estadosNegocio(): array
    {
        return [
            self::ESTADO_VIGENTE => 'Vigente',
            self::ESTADO_DESVIO => 'Desvío',
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_VENCIDA => 'Vencida',
            self::ESTADO_BORRADOR => 'Borrador',
            self::ESTADO_RECHAZADA => 'Rechazada / cerrada',
        ];
    }

    public static function etiquetaEstado(string $estado): string
    {
        return self::estadosNegocio()[$estado] ?? $estado;
    }

    public static function clasePillEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_VIGENTE => 'badge badge-success',
            self::ESTADO_PENDIENTE, self::ESTADO_BORRADOR => 'badge badge-warning',
            self::ESTADO_DESVIO => 'badge badge-danger',
            self::ESTADO_VENCIDA, self::ESTADO_RECHAZADA => 'badge badge-danger',
            default => 'badge badge-secondary',
        };
    }

    /**
     * Importe anualizado, para poder sumar mensuales y anuales en un mismo total.
     */
    public static function montoAnualizado(float $montoPeriodo, ?string $periodicidad): float
    {
        return self::normalizarPeriodicidad($periodicidad) === self::PERIODICIDAD_ANUAL
            ? round($montoPeriodo, 4)
            : round($montoPeriodo * 12, 4);
    }

    /**
     * Importe mensualizado: el anual se prorratea para el total del listado.
     */
    public static function montoMensualizado(float $montoPeriodo, ?string $periodicidad): float
    {
        return self::normalizarPeriodicidad($periodicidad) === self::PERIODICIDAD_ANUAL
            ? round($montoPeriodo / 12, 4)
            : round($montoPeriodo, 4);
    }

    /**
     * Desvío del cargo real contra el monto autorizado, en porcentaje con signo.
     */
    public static function desvioPct(float $montoCargo, float $montoPeriodo): float
    {
        if ($montoPeriodo <= 0.0) {
            return 0.0;
        }

        return round((($montoCargo - $montoPeriodo) / $montoPeriodo) * 100, 2);
    }

    public static function etiquetaEstadoCargo(string $estado): string
    {
        return match ($estado) {
            'CONCILIADO' => 'Conciliado',
            'DESVIO' => 'Desvío',
            'SIN_IDENTIFICAR' => 'Sin identificar',
            'PENDIENTE_APROBACION' => 'En re-aprobación',
            'REGULARIZAR' => 'A regularizar',
            'DESCARTADO' => 'Descartado',
            default => $estado,
        };
    }

    public static function clasePillEstadoCargo(string $estado): string
    {
        return match ($estado) {
            'CONCILIADO' => 'badge badge-success',
            'DESVIO' => 'badge badge-warning',
            'SIN_IDENTIFICAR' => 'badge badge-danger',
            'PENDIENTE_APROBACION' => 'badge badge-info',
            'REGULARIZAR' => 'badge badge-danger',
            'DESCARTADO' => 'badge badge-secondary',
            default => 'badge badge-secondary',
        };
    }
}
