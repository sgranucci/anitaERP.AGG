<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Services\Configuracion\CotizacionService;
use App\Support\Caja\CobranzaMontosAjusteSupport;
use InvalidArgumentException;

/**
 * Registra cobranza desde el POS estacionamiento (medios de pago en pantalla).
 */
final class EstacionamientoCobranzaService
{
    private const MONEDA_PESOS_ID = 1;

    private const TOLERANCIA_ARS = 0.02;

    public function __construct(
        private readonly CobranzaService $cobranzaService,
        private readonly CotizacionService $cotizacionService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{cobranza_id:int,caja_movimiento_id?:int}
     */
    public function registrarCobranzaPos(
        Venta $venta,
        array $mediosPago,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        bool $esDevolucion = false,
    ): array {
        if ($mediosPago === []) {
            throw new InvalidArgumentException('Indique al menos un medio de cobro en la grilla de cobranza.');
        }

        if ($esDevolucion) {
            $tipoCajaId = self::resolverTipotransaccionCajaDevolucionId();
            if ($tipoCajaId <= 0) {
                throw new InvalidArgumentException(
                    'Configure el tipo de transacción de caja de devolución en config/estacionamiento.php'
                    .' o defina ESTACIONAMIENTO_TIPO_TRANSACCION_CAJA_DEVOLUCION_ID en .env.'
                );
            }
            self::asegurarTipotransaccionCajaEsEgreso($tipoCajaId);
        } else {
            $tipoCajaId = self::resolverTipotransaccionCajaId($cfg);
            if ($tipoCajaId <= 0) {
                throw new InvalidArgumentException(
                    'Configure el tipo de transacción de caja (cobranza) en Caja → Configuración punto de venta estacionamiento'
                    .' o defina ESTACIONAMIENTO_TIPO_TRANSACCION_CAJA_ID en .env.'
                );
            }
        }

        $lineas = [];
        $totalArs = 0.;
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? 0);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monedaId <= 0 || $monto <= 0.) {
                throw new InvalidArgumentException('Cada renglón de cobranza debe tener cuenta de caja, moneda y monto mayor a cero.');
            }
            $cotizacion = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : $this->cotizacionParaMoneda($venta->fecha, $monedaId);
            $lineas[] = [
                'cuentacaja_id' => $cuentacajaId,
                'moneda_id' => $monedaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
                'observacion' => trim((string) ($medio['observacion'] ?? '')) ?: 'Estacionamiento',
            ];
            $totalArs += $this->montoEnPesos($monedaId, $monto, $cotizacion);
        }

        $totalFacturaArs = $this->montoEnPesos(
            (int) $venta->moneda_id,
            abs((float) $venta->total),
            (float) ($venta->cotizacion ?: 1.)
        );

        if (abs($totalArs - $totalFacturaArs) > self::TOLERANCIA_ARS) {
            throw new InvalidArgumentException(
                'El total de cobranza ($ '.number_format($totalArs, 2, ',', '.')
                .') no coincide con el total de la factura ($ '.number_format($totalFacturaArs, 2, ',', '.').').'
            );
        }

        $lineas = CobranzaMontosAjusteSupport::ajustarMediosPagoAlTotal(
            $lineas,
            $totalFacturaArs,
            fn (int $monedaId, float $monto, float $cotizacion): float => $this->montoEnPesos($monedaId, $monto, $cotizacion),
        );

        $codigoVenta = trim((string) ($venta->codigo ?? ''));
        $detalle = $esDevolucion
            ? 'Devolución estacionamiento'.($codigoVenta !== '' ? ' — '.$codigoVenta : '')
            : 'Cobranza estacionamiento'.($codigoVenta !== '' ? ' — '.$codigoVenta : '');

        return $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => (int) $cfg->empresa_id,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'lineas' => $lineas,
            'totalfinalcobranza' => round($totalFacturaArs, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'genera_contabilidad' => (bool) config('estacionamiento.genera_contabilidad_al_cobrar', false),
            'detalle' => $detalle,
        ]);
    }

    public static function resolverTipotransaccionCajaDevolucionId(): int
    {
        return (int) config('estacionamiento.tipotransaccion_caja_devolucion_id', 0);
    }

    private static function asegurarTipotransaccionCajaEsEgreso(int $tipoCajaId): void
    {
        $tipo = Tipotransaccion_Caja::query()->find($tipoCajaId);
        if (! $tipo) {
            throw new InvalidArgumentException(
                'El tipo de transacción de caja de devolución (id '.$tipoCajaId.') no existe en tipotransaccion_caja.'
            );
        }

        if ($tipo->signo !== 'E') {
            throw new InvalidArgumentException(
                'El tipo de transacción de caja para devolución debe tener signo Egreso.'
            );
        }
    }

    public static function resolverTipotransaccionCajaId(?ConfiguracionPuntoventaEstacionamiento $cfg = null): int
    {
        if ($cfg && (int) ($cfg->tipotransaccion_caja_id ?? 0) > 0) {
            return (int) $cfg->tipotransaccion_caja_id;
        }

        $fromEnv = (int) config('estacionamiento.tipotransaccion_caja_id', 0);
        if ($fromEnv > 0) {
            return $fromEnv;
        }

        $fallback = Tipotransaccion_Caja::query()
            ->where(function ($q) {
                $q->where('operacion', 'C')
                    ->orWhere('nombre', 'like', '%Cobranza%');
            })
            ->orderBy('id')
            ->value('id');

        return $fallback ? (int) $fallback : 0;
    }

    /**
     * @param  list<array{cuentacaja_id?:int,moneda_id?:int,monto?:float,cotizacion?:float|null}>  $mediosPago
     */
    public static function validarMediosContraTotalEsperado(
        array $mediosPago,
        float $totalFacturaArs,
        int $empresaId,
    ): ?string {
        if ($totalFacturaArs <= 0.02) {
            return null;
        }

        if ($mediosPago === []) {
            return 'Indique al menos un medio de cobro (cuenta de caja y monto) antes de facturar.';
        }

        $totalArs = 0.;
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? 0);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monedaId <= 0 || $monto <= 0.) {
                return 'Cada renglón de cobranza debe tener cuenta de caja, moneda y monto mayor a cero.';
            }

            $cot = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : 1.;
            if ($monedaId <= self::MONEDA_PESOS_ID) {
                $totalArs += $monto;
            } elseif ($monedaId > self::MONEDA_PESOS_ID) {
                $totalArs += $monto * $cot;
            } else {
                $totalArs += $monto / max($cot, 0.0001);
            }

            $existe = \App\Models\Caja\Cuentacaja::existeParaEmpresa($cuentacajaId, $empresaId);
            if (! $existe) {
                return 'La cuenta de caja id '.$cuentacajaId.' no existe o no pertenece a la empresa '.$empresaId.'.';
            }
        }

        if (abs($totalArs - $totalFacturaArs) > self::TOLERANCIA_ARS) {
            return 'El total de cobranza ($ '.number_format($totalArs, 2, ',', '.')
                .') no coincide con el total a facturar ($ '.number_format($totalFacturaArs, 2, ',', '.').').';
        }

        return null;
    }

    public static function mensajeConfigCobranzaFaltante(?ConfiguracionPuntoventaEstacionamiento $cfg): ?string
    {
        if (self::resolverTipotransaccionCajaId($cfg) > 0) {
            return null;
        }

        return 'Falta el tipo de transacción de caja (cobranza). Configúrelo en el ABM de punto de venta estacionamiento'
            .' o en .env: ESTACIONAMIENTO_TIPO_TRANSACCION_CAJA_ID=1';
    }

    private function cotizacionParaMoneda(string $fecha, int $monedaId): float
    {
        if ($monedaId <= self::MONEDA_PESOS_ID) {
            return 1.;
        }
        $cot = $this->cotizacionService->leeCotizacionDiaria($fecha, $monedaId);
        $valor = $cot && isset($cot['cotizacionventa']) ? (float) $cot['cotizacionventa'] : 0.;

        return $valor > 0. ? $valor : 1.;
    }

    private function montoEnPesos(int $monedaId, float $monto, float $cotizacion): float
    {
        if ($monedaId <= self::MONEDA_PESOS_ID) {
            return $monto;
        }
        if ($monedaId > self::MONEDA_PESOS_ID) {
            return $monto * $cotizacion;
        }

        return $monto / max($cotizacion, 0.0001);
    }
}
