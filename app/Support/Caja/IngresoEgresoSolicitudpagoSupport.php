<?php

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Contable\Cuentacontable;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Contable\CuentacajaCuentacontableResolverSupport;
use InvalidArgumentException;

/**
 * Pago IE vinculado a solicitud de pago: monto fijo, tipo OPP y asiento desde cuentas SP.
 */
class IngresoEgresoSolicitudpagoSupport
{
    public static function montoPendiente(?Solicitudpago $sp): float
    {
        if ($sp === null) {
            return 0.0;
        }

        return round(abs((float) $sp->monto), 2);
    }

    /**
     * Tipo de transacción configurable para pagos desde SP (default OPP).
     */
    public static function tipotransaccionCajaIdPorConfig(): int
    {
        $tipoId = (int) config('caja.ingresoegreso_sp_tipotransaccion_id', 0);
        if ($tipoId > 0) {
            return $tipoId;
        }

        $abrev = strtoupper(trim((string) config(
            'caja.ingresoegreso_sp_tipotransaccion_abreviatura',
            'OPP'
        )));
        if ($abrev === '') {
            return 0;
        }

        $tipo = Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abrev])
            ->first();

        return $tipo ? (int) $tipo->id : 0;
    }

    /**
     * Asiento TES on-the-fly desde las imputaciones de la SP.
     * Si hay cuentas de caja del pago, la pierna caja/banco (111xxx) se toma
     * de la cuenta financiera, no de la plantilla del concepto/SP.
     *
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    public static function lineasAsientoDesdeSolicitud(
        Solicitudpago $sp,
        int $monedaId,
        float|int|string $cotizacion,
        array $datosCaja = [],
        int $empresaId = 0
    ): array {
        $sp->loadMissing(['cuentas.cuentacontables']);

        $lineas = [];
        foreach ($sp->cuentas as $cta) {
            $cuentaId = (int) ($cta->cuentacontable_id ?? 0);
            $monto = round(abs((float) ($cta->monto ?? 0)), 2);
            if ($cuentaId <= 0 || $monto < 0.01) {
                continue;
            }

            $cuenta = $cta->cuentacontables;
            if ($cuenta === null) {
                continue;
            }

            $dh = strtoupper((string) ($cta->debe_haber ?? 'D')) === 'H' ? 'H' : 'D';
            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 0),
                'cotizacion' => $cotizacion === '' || $cotizacion === null ? 1 : $cotizacion,
                'centrocosto_id' => (int) ($cta->centrocosto_id ?? 0),
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        $empresaAsiento = $empresaId > 0 ? $empresaId : (int) ($sp->empresa_id ?? 0);
        $lineasCaja = self::lineasDesdeCuentacaja($datosCaja, $empresaAsiento, $monedaId, $cotizacion);
        if ($lineasCaja === []) {
            return $lineas;
        }

        $sinBanco = array_values(array_filter(
            $lineas,
            static fn (array $linea) => ! self::esCodigoCajaBanco((string) ($linea['codigo'] ?? ''))
        ));

        return array_merge($sinBanco, $lineasCaja);
    }

    /**
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    private static function lineasDesdeCuentacaja(
        array $datosCaja,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion
    ): array {
        $lineas = [];
        foreach ($datosCaja as $movimiento) {
            $cajaId = (int) ($movimiento->cuentacaja_ids ?? $movimiento->cuentacaja_id ?? 0);
            $importe = (float) ($movimiento->montos ?? $movimiento->monto ?? 0);
            if ($cajaId <= 0 || abs($importe) < 0.01) {
                continue;
            }

            $caja = Cuentacaja::query()->with('cuentacontables')->find($cajaId);
            if ($caja === null) {
                continue;
            }

            $cuentaId = (int) (CuentacajaCuentacontableResolverSupport::resolverIdParaEmpresa($caja, $empresaId) ?? 0);
            if ($cuentaId <= 0) {
                $cuentaId = (int) ($caja->cuentacontable_id ?? 0);
            }
            if ($cuentaId <= 0) {
                continue;
            }

            $cuenta = $caja->cuentacontables;
            if ($cuenta === null || (int) $cuenta->id !== $cuentaId) {
                $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre']);
            }
            if ($cuenta === null) {
                continue;
            }

            $monedaMov = (int) ($movimiento->moneda_ids ?? $monedaId);
            $cotizMov = $movimiento->cotizaciones ?? $cotizacion;
            $dh = $importe < 0 ? 'H' : 'D';
            $monto = round(abs($importe), 2);

            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaMov > 0 ? $monedaMov : $monedaId,
                'cotizacion' => $cotizMov === '' || $cotizMov === null ? 1 : $cotizMov,
                'centrocosto_id' => 0,
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        return $lineas;
    }

    public static function esCodigoCajaBanco(string $codigo): bool
    {
        $n = (int) preg_replace('/\D/', '', $codigo);

        return $n >= 111000000 && $n < 112000000;
    }

    /**
     * Réplica simple del total de operación de la pantalla (sin conversión FX).
     *
     * @param  array<string, mixed>  $data
     */
    public static function totalOperacionDesdeRequest(array $data): float
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ($data['montos'] ?? [] as $monto) {
            $valor = (float) $monto;
            if ($valor >= 0) {
                $debe += $valor;
            } else {
                $haber += abs($valor);
            }
        }

        foreach ($data['montocheque_emitidos'] ?? [] as $monto) {
            $valor = abs((float) $monto);
            if ($valor > 0) {
                $haber += $valor;
            }
        }

        foreach ($data['montocheque_recibidos'] ?? [] as $monto) {
            $valor = abs((float) $monto);
            if ($valor > 0) {
                $debe += $valor;
            }
        }

        return round(max($debe, $haber), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertMontoCoincideConSolicitud(array $data): void
    {
        $spId = (int) ($data['solicitudpago_id'] ?? 0);
        if ($spId <= 0) {
            return;
        }

        $sp = Solicitudpago::query()->find($spId);
        if ($sp === null) {
            throw new InvalidArgumentException('No se encontró la solicitud de pago vinculada.');
        }

        $esperado = self::montoPendiente($sp);
        if ($esperado < 0.01) {
            throw new InvalidArgumentException('La solicitud de pago no tiene monto pendiente a pagar.');
        }

        $actual = self::totalOperacionDesdeRequest($data);
        if (abs($actual - $esperado) > 0.02) {
            throw new InvalidArgumentException(
                'El total del pago ('.number_format($actual, 2, ',', '.').') debe ser exactamente '
                .'el monto pendiente de la solicitud ('.number_format($esperado, 2, ',', '.').').'
            );
        }
    }
}
