<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Support\Caja\CobranzaMontosAjusteSupport;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use InvalidArgumentException;

/**
 * Registra cobranza desde el POS gastronomía (medios de pago en pantalla).
 * No genera movimientos en cuenta corriente del cliente (misma regla que la factura gastronomía).
 */
final class GastronomiaCobranzaService
{
    private const MONEDA_PESOS_ID = 1;

    private const TOLERANCIA_ARS = 0.02;

    public function __construct(
        private readonly CobranzaService $cobranzaService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @param  bool  $esDevolucion  true cuando la venta es una NC: usa el tipotransaccion de devolución
     *                              (signo Egreso) para grabar importes negativos en caja.
     * @return array{cobranza_id:int,caja_movimiento_id?:int}
     */
    public function registrarCobranzaPos(
        Venta $venta,
        array $mediosPago,
        ConfiguracionPuntoventaGastronomia $cfg,
        bool $esDevolucion = false,
    ): array {
        if ($mediosPago === []) {
            throw new InvalidArgumentException('Indique al menos un medio de cobro en la grilla de cobranza.');
        }

        if ($esDevolucion) {
            $tipoCajaId = self::resolverTipotransaccionCajaDevolucionId();
            if ($tipoCajaId <= 0) {
                throw new InvalidArgumentException(
                    'Configure el tipo de transacción de caja de devolución en config/gastronomia.php'
                    .' o defina GASTRONOMIA_TIPO_TRANSACCION_CAJA_DEVOLUCION_ID en .env (ej. 3 = Devolución de factura).'
                );
            }
            self::asegurarTipotransaccionCajaEsEgreso($tipoCajaId);
        } else {
            $tipoCajaId = self::resolverTipotransaccionCajaId($cfg);
            if ($tipoCajaId <= 0) {
                throw new InvalidArgumentException(
                    'Configure el tipo de transacción de caja (cobranza) en Ventas → Configuración punto de venta gastronomía'
                    .' o defina GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID en .env (ej. 1 = Cobranza).'
                );
            }
        }

        $lineas = [];
        $totalArs = 0.;
        foreach ($mediosPago as $i => $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? 0);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monedaId <= 0 || $monto <= 0.) {
                throw new InvalidArgumentException('Cada renglón de cobranza debe tener cuenta de caja, moneda y monto mayor a cero.');
            }
            $cotizacion = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : $this->cotizacionParaMoneda($venta->fecha, $monedaId, (int) $cfg->empresa_id);
            $lineas[] = [
                'cuentacaja_id' => $cuentacajaId,
                'moneda_id' => $monedaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
                'observacion' => trim((string) ($medio['observacion'] ?? '')) ?: 'Gastronomía',
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
        $totalArs = $totalFacturaArs;

        $codigoVenta = trim((string) ($venta->codigo ?? ''));
        $detalle = $esDevolucion
            ? 'Devolución gastronomía'.($codigoVenta !== '' ? ' — '.$codigoVenta : '')
            : 'Cobranza gastronomía'.($codigoVenta !== '' ? ' — '.$codigoVenta : '');

        return $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => (int) $cfg->empresa_id,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'lineas' => $lineas,
            'totalfinalcobranza' => round($totalFacturaArs, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'genera_contabilidad' => (bool) config('gastronomia.genera_contabilidad_al_cobrar', true),
            'detalle' => $detalle,
        ]);
    }

    /**
     * Tipo de transacción de caja para devolución de factura (NC) en gastronomía.
     * Solo respaldo por config/env: no depende del PV porque la operación es transversal
     * (todas las terminales de gastronomía usan la misma devolución).
     */
    public static function resolverTipotransaccionCajaDevolucionId(): int
    {
        return (int) config('gastronomia.tipotransaccion_caja_devolucion_id', 0);
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
                'El tipo de transacción de caja para devolución debe tener signo Egreso para que los importes'
                .' se graben en negativo. Revisar tipotransaccion_caja id '.$tipoCajaId.'.'
            );
        }
    }

    public static function resolverTipotransaccionCajaId(?ConfiguracionPuntoventaGastronomia $cfg = null): int
    {
        if ($cfg && (int) ($cfg->tipotransaccion_caja_id ?? 0) > 0) {
            return (int) $cfg->tipotransaccion_caja_id;
        }

        $fromEnv = (int) config('gastronomia.tipotransaccion_caja_id', 0);
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

    public static function mensajeConfigCobranzaFaltante(?ConfiguracionPuntoventaGastronomia $cfg): ?string
    {
        if (self::resolverTipotransaccionCajaId($cfg) > 0) {
            return null;
        }

        return 'Falta el tipo de transacción de caja (cobranza). Configúrelo en el ABM de punto de venta gastronomía'
            .' o en .env: GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID=1';
    }

    private function cotizacionParaMoneda(string $fecha, int $monedaId, int $empresaId): float
    {
        return CotizacionTesoreriaConsultaSupport::calculaVenta($fecha, $monedaId, $empresaId);
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
