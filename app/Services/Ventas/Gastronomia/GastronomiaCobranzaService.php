<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Services\Configuracion\CotizacionService;
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
        ConfiguracionPuntoventaGastronomia $cfg,
    ): array {
        if ($mediosPago === []) {
            throw new InvalidArgumentException('Indique al menos un medio de cobro en la grilla de cobranza.');
        }

        $tipoCajaId = self::resolverTipotransaccionCajaId($cfg);
        if ($tipoCajaId <= 0) {
            throw new InvalidArgumentException(
                'Configure el tipo de transacción de caja (cobranza) en Ventas → Configuración punto de venta gastronomía'
                .' o defina GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID en .env (ej. 1 = Cobranza).'
            );
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
                : $this->cotizacionParaMoneda($venta->fecha, $monedaId);
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

        return $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => (int) $cfg->empresa_id,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'lineas' => $lineas,
            'totalfinalcobranza' => round($totalArs, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'genera_contabilidad' => (bool) config('gastronomia.genera_contabilidad_al_cobrar', true),
        ]);
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

            $existe = \App\Models\Caja\Cuentacaja::query()
                ->whereKey($cuentacajaId)
                ->where('empresa_id', $empresaId)
                ->exists();
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
