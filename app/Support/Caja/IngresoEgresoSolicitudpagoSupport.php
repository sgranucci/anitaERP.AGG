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

        $abrev = self::abreviaturaTipoPago();
        if ($abrev === '') {
            return 0;
        }

        $tipo = Tipotransaccion_Caja::query()
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abrev])
            ->first();

        return $tipo ? (int) $tipo->id : 0;
    }

    public static function abreviaturaTipoPago(): string
    {
        $abrev = strtoupper(trim((string) config(
            'caja.ingresoegreso_sp_tipotransaccion_abreviatura',
            'OPP'
        )));

        return $abrev !== '' ? $abrev : 'OPP';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function solicitudpagoIdDesdeData(array $data): int
    {
        $raw = $data['solicitudpago_id'] ?? 0;
        $spId = (int) $raw;
        if ($spId <= 0 && is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            $spId = (int) ($decoded ?? 0);
        }

        return $spId;
    }

    /**
     * Pago desde SP: el comprobante Anita (pago/tesmov/ctamov) cierra siempre OPP,
     * aunque el usuario haya elegido COB u otro tipo en pantalla.
     *
     * @param  array<string, mixed>  $data
     */
    public static function aplicarPagoDesdeSolicitud(array &$data): void
    {
        $spId = self::solicitudpagoIdDesdeData($data);
        if ($spId <= 0) {
            return;
        }

        $data['solicitudpago_id'] = $spId;

        $tipoId = self::tipotransaccionCajaIdPorConfig();
        if ($tipoId <= 0) {
            throw new InvalidArgumentException(
                'No hay tipo de transacción '.self::abreviaturaTipoPago()
                .' configurado para pagar solicitudes de pago.'
            );
        }
        $data['tipotransaccion_caja_id'] = $tipoId;

        $proveedorId = (int) ($data['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            $sp = Solicitudpago::query()->find($spId);
            if ($sp && (int) ($sp->proveedor_id ?? 0) > 0) {
                $data['proveedor_id'] = (int) $sp->proveedor_id;
            }
        }
    }

    /**
     * Asiento TES on-the-fly desde las imputaciones de la SP.
     * Si hay cuentas de caja del pago, la pierna caja/banco (111xxx) se toma
     * de la cuenta financiera, no de la plantilla del concepto/SP.
     *
     * $signoOperacion: mismo criterio que IE sin SP (+1 ingreso / −1 egreso-OPP).
     * Los montos de caja en pantalla suelen venir en valor absoluto; se firman acá.
     *
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    public static function lineasAsientoDesdeSolicitud(
        Solicitudpago $sp,
        int $monedaId,
        float|int|string $cotizacion,
        array $datosCaja = [],
        int $empresaId = 0,
        int $signoOperacion = -1
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
                'cotizacion' => self::cotizacionParaMoneda($monedaId > 0 ? $monedaId : (int) ($sp->moneda_id ?? 0), $cotizacion),
                'centrocosto_id' => (int) ($cta->centrocosto_id ?? 0),
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        $empresaAsiento = $empresaId > 0 ? $empresaId : (int) ($sp->empresa_id ?? 0);
        $lineasCaja = self::lineasDesdeCuentacaja(
            $datosCaja,
            $empresaAsiento,
            $monedaId,
            $cotizacion,
            $signoOperacion,
            self::centrocostoPiernaFinanciera($lineas)
        );
        if ($lineasCaja === []) {
            return $lineas;
        }

        $sinBanco = array_values(array_filter(
            $lineas,
            static fn (array $linea) => ! self::esCodigoCajaBanco((string) ($linea['codigo'] ?? ''))
        ));
        $sinBanco = self::ajustarLineasNoBancoAlCaja($sinBanco, $lineasCaja);

        return array_merge($sinBanco, $lineasCaja);
    }

    /**
     * La pierna de caja es el importe real del pago. Si el asiento de la SP
     * no coincidía con el monto (p. ej. importado de Anita), escala el Debe
     * no-banco para que cierre. Conserva retenciones (Haber no-banco).
     *
     * @param  list<array<string, mixed>>  $sinBanco
     * @param  list<array<string, mixed>>  $lineasCaja
     * @return list<array<string, mixed>>
     */
    public static function ajustarLineasNoBancoAlCaja(array $sinBanco, array $lineasCaja): array
    {
        if ($sinBanco === [] || $lineasCaja === []) {
            return $sinBanco;
        }

        $debeCaja = 0.0;
        $haberCaja = 0.0;
        foreach ($lineasCaja as $linea) {
            $debeCaja += (float) ($linea['debe'] ?: 0);
            $haberCaja += (float) ($linea['haber'] ?: 0);
        }

        $debeNoBanco = 0.0;
        $haberNoBanco = 0.0;
        foreach ($sinBanco as $linea) {
            $debeNoBanco += (float) ($linea['debe'] ?: 0);
            $haberNoBanco += (float) ($linea['haber'] ?: 0);
        }

        $debeTotal = round($debeCaja + $debeNoBanco, 2);
        $haberTotal = round($haberCaja + $haberNoBanco, 2);
        $dif = round($haberTotal - $debeTotal, 2);
        if (abs($dif) < 0.01) {
            return $sinBanco;
        }

        if ($dif > 0 && $debeNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'debe', $debeNoBanco + $dif);
        }
        if ($dif < 0 && $haberNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'haber', $haberNoBanco - $dif);
        }
        if ($dif < 0 && $debeNoBanco >= 0.01) {
            return self::escalarLadoAsiento($sinBanco, 'debe', $debeNoBanco + $dif);
        }

        return $sinBanco;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function escalarLadoAsiento(array $lineas, string $lado, float $destino): array
    {
        $actual = 0.0;
        $indices = [];
        foreach ($lineas as $i => $linea) {
            $valor = (float) ($linea[$lado] ?: 0);
            if ($valor >= 0.01) {
                $actual += $valor;
                $indices[] = $i;
            }
        }
        if ($actual < 0.01 || $indices === []) {
            return $lineas;
        }

        $destino = round($destino, 2);
        $acum = 0.0;
        $ultimo = $indices[count($indices) - 1];
        $otro = $lado === 'debe' ? 'haber' : 'debe';
        foreach ($indices as $i) {
            if ($i === $ultimo) {
                $nuevo = round($destino - $acum, 2);
            } else {
                $nuevo = round(((float) ($lineas[$i][$lado] ?: 0)) * $destino / $actual, 2);
                $acum += $nuevo;
            }
            $lineas[$i][$lado] = $nuevo;
            $lineas[$i][$otro] = $lineas[$i][$otro] ?: '';
        }

        return $lineas;
    }

    /**
     * @param  list<object>  $datosCaja
     * @return list<array<string, mixed>>
     */
    private static function lineasDesdeCuentacaja(
        array $datosCaja,
        int $empresaId,
        int $monedaId,
        float|int|string $cotizacion,
        int $signoOperacion = -1,
        int $centrocostoId = 0
    ): array {
        $signo = $signoOperacion < 0 ? -1 : 1;
        $lineas = [];
        foreach ($datosCaja as $movimiento) {
            $cajaId = (int) ($movimiento->cuentacaja_ids ?? $movimiento->cuentacaja_id ?? 0);
            $importeOriginal = (float) ($movimiento->montos ?? $movimiento->monto ?? 0);
            $importeAbs = abs($importeOriginal);
            if ($cajaId <= 0 || $importeAbs < 0.01) {
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
            $cotizMov = self::cotizacionParaMoneda(
                $monedaMov > 0 ? $monedaMov : $monedaId,
                $movimiento->cotizaciones ?? $cotizacion
            );
            // Tipo de egreso (OPP/EGR): la pantalla carga el monto en positivo,
            // la pierna financiera siempre va al Haber. TRA/ingresos respetan el signo.
            $importeFirmado = $signo < 0 ? -$importeAbs : $importeOriginal;
            $dh = $importeFirmado < 0 ? 'H' : 'D';
            $monto = round($importeAbs, 2);

            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'moneda_id' => $monedaMov > 0 ? $monedaMov : $monedaId,
                'cotizacion' => $cotizMov,
                'centrocosto_id' => $centrocostoId,
                'debe' => $dh === 'D' ? $monto : '',
                'haber' => $dh === 'H' ? $monto : '',
                'observacion' => '',
                'carga_cuentacontable_manual' => 'N',
            ];
        }

        return $lineas;
    }

    /**
     * Centro de costo a usar en la pierna financiera del pago: el que trae la SP
     * en su cuenta de caja/banco (o cualquiera de sus imputaciones).
     *
     * @param  list<array<string, mixed>>  $lineasSolicitud
     */
    private static function centrocostoPiernaFinanciera(array $lineasSolicitud): int
    {
        $fallback = 0;
        foreach ($lineasSolicitud as $linea) {
            $cc = (int) ($linea['centrocosto_id'] ?? 0);
            if ($cc <= 0) {
                continue;
            }
            if (self::esCodigoCajaBanco((string) ($linea['codigo'] ?? ''))) {
                return $cc;
            }
            if ($fallback <= 0) {
                $fallback = $cc;
            }
        }

        return $fallback;
    }

    /**
     * Moneda local: cotización 1. Evita que leercotizacion (que para id=1 devuelve USD)
     * deje 1515 en la pierna en pesos y rompa controles posteriores.
     */
    private static function cotizacionParaMoneda(int $monedaId, float|int|string|null $cotizacion): float|int|string
    {
        if ($monedaId <= 1) {
            return 1;
        }
        if ($cotizacion === '' || $cotizacion === null) {
            return 1;
        }

        return $cotizacion;
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
