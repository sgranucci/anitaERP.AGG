<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use RuntimeException;

/**
 * Compara un documento de proveedores (factura, OPA, aplicación) contra su
 * imputación en AP MN + AP ME + anticipo, todo en pesos con la cotización de la operación.
 *
 * Convenio Haber−Debe (mismo que el mayor): Haber suma, Debe resta.
 * Factura (signo S) espera Haber AP; NC (signo R) espera Debe AP; OPA espera Debe anticipo;
 * aplicación OPA↔factura espera neto 0 en el trío (reclasificación).
 */
final class ComprobanteProveedorImputacionApSupport
{
    public const TIPO_COMPROBANTE = 'comprobante';

    public const TIPO_OPA = 'opa';

    public const TIPO_APLICACION = 'aplicacion';

    public const CUBETA_MN = 'mn';

    public const CUBETA_ME = 'me';

    public const CUBETA_ANTICIPO = 'anticipo';

    public const CUBETA_MIXTA = 'mixta';

    public const CUBETA_NINGUNA = 'ninguna';

    public const TOLERANCIA = 0.05;

    public static function esNotaCredito(?string $signo): bool
    {
        return strtoupper(trim((string) $signo)) === 'R';
    }

    /**
     * Línea CC de la factura (deuda/crédito por cuota), no aplicaciones ni OPP
     * que cancelan el saldo después.
     */
    public static function esLineaCcFactura(
        ?int $comprobanteProveedorCuotaId,
        ?int $pagoproveedorId = null,
    ): bool {
        return (int) ($comprobanteProveedorCuotaId ?? 0) > 0
            && (int) ($pagoproveedorId ?? 0) <= 0;
    }

    /**
     * @param  object{comprobante_proveedor_cuota_id?: mixed, pagoproveedor_id?: mixed}  $lineaCc
     */
    public static function esLineaCcDeudaFactura(object $lineaCc): bool
    {
        return self::esLineaCcFactura(
            isset($lineaCc->comprobante_proveedor_cuota_id) ? (int) $lineaCc->comprobante_proveedor_cuota_id : null,
            isset($lineaCc->pagoproveedor_id) ? (int) $lineaCc->pagoproveedor_id : null,
        );
    }

    /**
     * Lleva un importe a pesos. Si falta cotización de ME no corta el listado.
     */
    public static function aPesosTolerante(
        float $importe,
        int $monedaId,
        mixed $cotizacion,
        string|Carbon|null $fecha,
        string $contexto,
    ): float {
        try {
            return ComprobanteProveedorMonedaMotor::aMonedaLocal(
                $importe,
                $monedaId,
                $cotizacion,
                $fecha,
                $contexto
            );
        } catch (RuntimeException $e) {
            return ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
                $importe,
                (float) ($cotizacion ?: 1),
                ComprobanteProveedorMonedaMotor::normalizarMonedaId($monedaId)
            ) * ($importe < 0 ? -1 : 1);
        }
    }

    /**
     * Haber−Debe en pesos. `monto` del asiento: positivo = Debe, negativo = Haber.
     */
    public static function haberNetoArs(
        float $monto,
        int $monedaId,
        mixed $cotizacion,
        string|Carbon|null $fecha,
        string $contexto,
    ): float {
        $pesos = self::aPesosTolerante($monto, $monedaId, $cotizacion, $fecha, $contexto);

        return round(-1 * $pesos, 2);
    }

    public static function esperadoHaberNetoComprobante(
        float $total,
        int $monedaId,
        mixed $cotizacion,
        string|Carbon|null $fecha,
        bool $esNotaCredito,
        string $contexto = 'el comprobante de proveedor',
    ): float {
        $ars = self::aPesosTolerante(abs($total), $monedaId, $cotizacion, $fecha, $contexto);

        return $esNotaCredito ? round(-1 * $ars, 2) : $ars;
    }

    public static function esperadoHaberNetoOpa(
        float $total,
        int $monedaId,
        mixed $cotizacion,
        string|Carbon|null $fecha,
        string $contexto = 'el anticipo OPA',
    ): float {
        $ars = self::aPesosTolerante(abs($total), $monedaId, $cotizacion, $fecha, $contexto);

        return round(-1 * $ars, 2);
    }

    public static function esperadoHaberNetoAplicacion(): float
    {
        return 0.0;
    }

    /**
     * @param  list<array{cuentacontable_id:int, monto:float, moneda_id:int, cotizacion:mixed, fecha?:string|null}>  $movimientos
     * @param  array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>}  $catalogo
     * @return array{ap_mn: float, ap_me: float, anticipo: float, ap: float, trio: float, cubeta: string}
     */
    public static function imputacionTrio(array $movimientos, array $catalogo, string $contexto): array
    {
        $apMn = 0.0;
        $apMe = 0.0;
        $anticipo = 0.0;

        foreach ($movimientos as $mov) {
            $cuentaId = (int) ($mov['cuentacontable_id'] ?? 0);
            $cubeta = self::clasificarCuenta($cuentaId, $catalogo);
            if ($cubeta === null) {
                continue;
            }

            $neto = self::haberNetoArs(
                (float) ($mov['monto'] ?? 0),
                (int) ($mov['moneda_id'] ?? 1),
                $mov['cotizacion'] ?? 1,
                $mov['fecha'] ?? null,
                $contexto
            );

            if ($cubeta === self::CUBETA_MN) {
                $apMn += $neto;
            } elseif ($cubeta === self::CUBETA_ME) {
                $apMe += $neto;
            } else {
                $anticipo += $neto;
            }
        }

        $apMn = round($apMn, 2);
        $apMe = round($apMe, 2);
        $anticipo = round($anticipo, 2);
        $trio = round($apMn + $apMe + $anticipo, 2);

        return [
            'ap_mn' => $apMn,
            'ap_me' => $apMe,
            'anticipo' => $anticipo,
            'ap' => round($apMn + $apMe, 2),
            'trio' => $trio,
            'cubeta' => self::cubetaDesdeImportes($apMn, $apMe, $anticipo),
        ];
    }

    /**
     * Haber de la cuenta cargada en el proveedor.
     *
     * Factura: solo el haber (monto negativo). Nota de crédito: solo el debe
     * (monto positivo). Si el gasto está en la misma cuenta, ese lado no se netea:
     * es la pierna de gasto, no la de la deuda.
     *
     * @param  list<array{cuentacontable_id:int, monto:float, moneda_id:int, cotizacion:mixed, fecha?:string|null}>  $movimientos
     */
    public static function haberEnCuentaProveedor(
        array $movimientos,
        int $cuentaProveedorId,
        bool $esNotaCredito,
        string $contexto,
    ): float {
        if ($cuentaProveedorId <= 0) {
            return 0.0;
        }

        $suma = 0.0;
        foreach ($movimientos as $mov) {
            if ((int) ($mov['cuentacontable_id'] ?? 0) !== $cuentaProveedorId) {
                continue;
            }

            $monto = (float) ($mov['monto'] ?? 0);
            $esPiernaDeuda = $esNotaCredito ? $monto > 0 : $monto < 0;
            if (! $esPiernaDeuda) {
                continue;
            }

            $suma += self::haberNetoArs(
                $monto,
                (int) ($mov['moneda_id'] ?? 1),
                $mov['cotizacion'] ?? 1,
                $mov['fecha'] ?? null,
                $contexto
            );
        }

        return round($suma, 2);
    }

    /**
     * Haber neto en proveedores (MN+ME). La CC de un comprobante se compara con esto,
     * no con el trío: en factura anticipada el debe a anticipo cancela el haber a AP.
     *
     * @param  array{ap?: float, ap_mn?: float, ap_me?: float}  $imputado
     */
    public static function haberAp(array $imputado): float
    {
        if (isset($imputado['ap'])) {
            return round((float) $imputado['ap'], 2);
        }

        return round((float) ($imputado['ap_mn'] ?? 0) + (float) ($imputado['ap_me'] ?? 0), 2);
    }

    /**
     * @param  array{mn: array<int, true>, me: array<int, true>, anticipo: array<int, true>}  $catalogo
     */
    public static function clasificarCuenta(int $cuentaId, array $catalogo): ?string
    {
        if ($cuentaId <= 0) {
            return null;
        }
        if (! empty($catalogo['anticipo'][$cuentaId])) {
            return self::CUBETA_ANTICIPO;
        }
        if (! empty($catalogo['me'][$cuentaId])) {
            return self::CUBETA_ME;
        }
        if (! empty($catalogo['mn'][$cuentaId])) {
            return self::CUBETA_MN;
        }

        return null;
    }

    /**
     * @param  array{codigo_mn?: array<int, true>, codigo_me?: array<int, true>, codigo_anticipo?: array<int, true>}  $catalogo
     */
    public static function clasificarCodigo(int $codigo, array $catalogo): ?string
    {
        if ($codigo <= 0) {
            return null;
        }
        if (! empty($catalogo['codigo_anticipo'][$codigo])) {
            return self::CUBETA_ANTICIPO;
        }
        if (! empty($catalogo['codigo_me'][$codigo])) {
            return self::CUBETA_ME;
        }
        if (! empty($catalogo['codigo_mn'][$codigo])) {
            return self::CUBETA_MN;
        }

        return null;
    }

    public static function cubetaDesdeImportes(float $apMn, float $apMe, float $anticipo): string
    {
        $hits = [];
        if (abs($apMn) >= self::TOLERANCIA) {
            $hits[] = self::CUBETA_MN;
        }
        if (abs($apMe) >= self::TOLERANCIA) {
            $hits[] = self::CUBETA_ME;
        }
        if (abs($anticipo) >= self::TOLERANCIA) {
            $hits[] = self::CUBETA_ANTICIPO;
        }

        if ($hits === []) {
            return self::CUBETA_NINGUNA;
        }
        if (count($hits) > 1) {
            return self::CUBETA_MIXTA;
        }

        return $hits[0];
    }

    public static function desvia(float $a, float $b, float $tolerancia = self::TOLERANCIA): bool
    {
        return abs(round($a - $b, 2)) >= $tolerancia;
    }

    /**
     * @return array{diferencia: float, ok: bool, alertas: list<string>}
     */
    public static function evaluar(
        float $esperado,
        float $imputado,
        ?string $cubetaEsperada,
        string $cubetaImputada,
        bool $tieneAsiento,
        bool $asientoRechazado,
        string $tipo,
        float $tolerancia = self::TOLERANCIA,
    ): array {
        $alertas = [];
        if (! $tieneAsiento) {
            $alertas[] = 'Sin asiento';
        }
        if ($asientoRechazado) {
            $alertas[] = 'Asiento rechazado';
        }

        $diferencia = round($imputado - $esperado, 2);
        if (self::desvia($imputado, $esperado, $tolerancia)) {
            $alertas[] = 'Distorsión en AP/anticipo';
        }

        if ($tipo === self::TIPO_COMPROBANTE
            && $cubetaEsperada
            && $cubetaImputada !== self::CUBETA_NINGUNA
            && $cubetaImputada !== $cubetaEsperada
            && ! self::esFacturaAnticipadaMixta($cubetaImputada, $imputado, $esperado, $tolerancia)) {
            $alertas[] = 'Cuenta distinta a la esperada (MN/ME/anticipo)';
        }

        if ($tipo === self::TIPO_COMPROBANTE && $cubetaImputada === self::CUBETA_ANTICIPO) {
            $alertas[] = 'El comprobante imputó anticipo';
        }

        if ($tipo === self::TIPO_OPA
            && $cubetaEsperada === self::CUBETA_ANTICIPO
            && in_array($cubetaImputada, [self::CUBETA_MN, self::CUBETA_ME], true)) {
            $alertas[] = 'OPA imputó proveedores en vez de anticipo';
        }

        return [
            'diferencia' => $diferencia,
            'ok' => $alertas === [],
            'alertas' => $alertas,
        ];
    }

    /**
     * Control diario: CC de la factura vs haber AP del asiento vs haber AP de ctamov.
     * Opcionalmente exige que la CC coincida con el importe de la factura (esperado).
     * El anticipo de una factura anticipada se controla aparte (no entra al neto vs CC).
     *
     * @return array{
     *     ok: bool,
     *     alertas: list<string>,
     *     diff_cc_asiento: float,
     *     diff_asiento_ctamov: float,
     *     diff_cc_ctamov: float,
     *     diff_cc_factura: float|null
     * }
     */
    public static function evaluarTresPatas(
        float $ccArs,
        float $asientoArs,
        float $ctamovArs,
        bool $tieneCc,
        bool $tieneAsiento,
        bool $tieneCtamov,
        float $tolerancia = self::TOLERANCIA,
        float $asientoAnticipoArs = 0.0,
        float $ctamovAnticipoArs = 0.0,
        ?float $facturaArs = null,
    ): array {
        $alertas = [];
        if (! $tieneCc) {
            $alertas[] = 'Sin CC';
        }
        if (! $tieneAsiento) {
            $alertas[] = 'Sin asiento';
        }
        if (! $tieneCtamov) {
            $alertas[] = 'Sin ctamov Anita';
        }

        if ($tieneCc && $facturaArs !== null && self::desvia($ccArs, $facturaArs, $tolerancia)) {
            $alertas[] = 'CC ≠ factura';
        }
        if ($tieneCc && $tieneAsiento && self::desvia($asientoArs, $ccArs, $tolerancia)) {
            $alertas[] = 'CC ≠ asiento';
        }
        if ($tieneAsiento && $tieneCtamov && self::desvia($ctamovArs, $asientoArs, $tolerancia)) {
            $alertas[] = 'Asiento ≠ ctamov';
        }
        if ($tieneCc && $tieneCtamov && self::desvia($ctamovArs, $ccArs, $tolerancia)) {
            $alertas[] = 'CC ≠ ctamov';
        }
        if ($tieneAsiento && $tieneCtamov
            && self::desvia($asientoAnticipoArs, $ctamovAnticipoArs, $tolerancia)) {
            $alertas[] = 'Anticipo asiento ≠ ctamov';
        }

        return [
            'ok' => $alertas === [],
            'alertas' => $alertas,
            'diff_cc_asiento' => round($asientoArs - $ccArs, 2),
            'diff_asiento_ctamov' => round($ctamovArs - $asientoArs, 2),
            'diff_cc_ctamov' => round($ctamovArs - $ccArs, 2),
            'diff_cc_factura' => $facturaArs === null ? null : round($ccArs - $facturaArs, 2),
        ];
    }

    /**
     * Comprobante de ingresos/egresos: no tiene cuenta corriente.
     * Se compara el total de la factura contra el debe del asiento del movimiento
     * que le corresponde (líneas vinculadas o mismo código de cuenta + importe).
     * El ctamov de ese movimiento lo controla el mail de I/E.
     *
     * El cruce por código e importe es en la moneda del renglón. La suma vuelve en pesos:
     * si el asiento está en dólares, cada debe se multiplica por su cotización.
     *
     * @param  list<array{monto: float, codigo?: string, comprobante_proveedor_id?: int, moneda_id?: int, cotizacion?: mixed, fecha?: string|null}>  $lineasDebe
     * @param  list<array{monto: float, codigo?: string}>  $conceptos
     */
    public static function importeDebeIngresoEgreso(int $comprobanteId, array $lineasDebe, array $conceptos): float
    {
        $suma = 0.0;
        $usadas = [];

        foreach ($lineasDebe as $i => $linea) {
            $dueno = (int) ($linea['comprobante_proveedor_id'] ?? 0);
            if ($comprobanteId > 0 && $dueno === $comprobanteId) {
                $suma += self::lineaIngresoEgresoEnPesos($linea);
                $usadas[$i] = true;
            }
        }

        foreach ($conceptos as $concepto) {
            $codigo = trim((string) ($concepto['codigo'] ?? ''));
            $monto = round((float) ($concepto['monto'] ?? 0), 2);
            if ($codigo === '' || abs($monto) < 0.0001) {
                continue;
            }

            foreach ($lineasDebe as $i => $linea) {
                if (isset($usadas[$i])) {
                    continue;
                }
                if ((int) ($linea['comprobante_proveedor_id'] ?? 0) > 0) {
                    continue;
                }
                if (trim((string) ($linea['codigo'] ?? '')) !== $codigo) {
                    continue;
                }
                if (round((float) ($linea['monto'] ?? 0), 2) !== $monto) {
                    continue;
                }

                $suma += self::lineaIngresoEgresoEnPesos($linea);
                $usadas[$i] = true;
                break;
            }
        }

        return round($suma, 2);
    }

    /**
     * Pesos del I/E. Si el asiento está en moneda extranjera, manda su cotización
     * (la de la operación), no la del día que reemplaza un 1 guardado en la factura.
     *
     * @param  list<array{monto?: float, moneda_id?: int, cotizacion?: mixed, comprobante_proveedor_id?: int}>  $lineasDebe
     */
    public static function facturaIngresoEgresoEnPesos(
        float $totalOrigen,
        float $totalArs,
        int $monedaId,
        array $lineasDebe,
        int $comprobanteId,
    ): float {
        $cotizacion = self::cotizacionAsientoEnMoneda($lineasDebe, $comprobanteId, $monedaId);
        if ($cotizacion === null) {
            return round($totalArs, 2);
        }

        return round(abs($totalOrigen) * $cotizacion, 2);
    }

    /**
     * @param  list<array{moneda_id?: int, cotizacion?: mixed, comprobante_proveedor_id?: int}>  $lineasDebe
     */
    public static function cotizacionAsientoEnMoneda(array $lineasDebe, int $comprobanteId, int $monedaId): ?float
    {
        if (! ComprobanteProveedorMonedaMotor::esMonedaExtranjera($monedaId)) {
            return null;
        }

        $propia = null;
        $comun = null;
        foreach ($lineasDebe as $linea) {
            if ((int) ($linea['moneda_id'] ?? 1) !== $monedaId) {
                continue;
            }
            $tasa = (float) ($linea['cotizacion'] ?? 0);
            if ($tasa <= ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
                continue;
            }
            $dueno = (int) ($linea['comprobante_proveedor_id'] ?? 0);
            if ($comprobanteId > 0 && $dueno === $comprobanteId) {
                $propia = $tasa;
            } elseif ($dueno <= 0) {
                $comun = $tasa;
            }
        }

        return $propia ?? $comun;
    }

    /**
     * @param  array{monto?: float, moneda_id?: int, cotizacion?: mixed, fecha?: string|null}  $linea
     */
    private static function lineaIngresoEgresoEnPesos(array $linea): float
    {
        return self::aPesosTolerante(
            (float) ($linea['monto'] ?? 0),
            (int) ($linea['moneda_id'] ?? 1),
            $linea['cotizacion'] ?? 1,
            $linea['fecha'] ?? null,
            'asiento ingreso/egreso',
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     alertas: list<string>,
     *     diff_cc_asiento: float,
     *     diff_asiento_ctamov: float,
     *     diff_cc_ctamov: float,
     *     diff_cc_factura: float|null
     * }
     */
    public static function evaluarIngresoEgreso(
        float $facturaArs,
        float $asientoArs,
        bool $tieneAsiento,
        float $tolerancia = self::TOLERANCIA,
    ): array {
        $alertas = [];
        if (! $tieneAsiento) {
            $alertas[] = 'Sin asiento del I/E';
        } elseif (self::desvia($asientoArs, $facturaArs, $tolerancia)) {
            $alertas[] = 'Factura ≠ asiento I/E';
        }

        return [
            'ok' => $alertas === [],
            'alertas' => $alertas,
            'diff_cc_asiento' => round($asientoArs - $facturaArs, 2),
            'diff_asiento_ctamov' => 0.0,
            'diff_cc_ctamov' => 0.0,
            'diff_cc_factura' => null,
        ];
    }

    public static function esBorrador(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === ComprobanteProveedorEstados::BORRADOR;
    }

    /**
     * El control diario no trata un BORRADOR como desvío: aún no se contabilizó.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *     ok: list<array<string, mixed>>,
     *     desvios: list<array<string, mixed>>,
     *     borradores: list<array<string, mixed>>
     * }
     */
    public static function particionarControlDiario(array $filas): array
    {
        $ok = [];
        $desvios = [];
        $borradores = [];

        foreach ($filas as $fila) {
            if (self::esBorrador((string) ($fila['estado'] ?? ''))) {
                $borradores[] = $fila;
                continue;
            }
            if (! empty($fila['ok'])) {
                $ok[] = $fila;
                continue;
            }
            $desvios[] = $fila;
        }

        return [
            'ok' => $ok,
            'desvios' => $desvios,
            'borradores' => $borradores,
        ];
    }

    /**
     * Factura anticipada: haber AP + debe anticipo (cubeta mixta) y el AP cuadra con el total.
     */
    public static function esFacturaAnticipadaMixta(
        string $cubetaImputada,
        float $haberAp,
        float $esperado,
        float $tolerancia = self::TOLERANCIA,
    ): bool {
        return $cubetaImputada === self::CUBETA_MIXTA
            && ! self::desvia($haberAp, $esperado, $tolerancia);
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_OPA => 'OPA',
            self::TIPO_APLICACION => 'Aplicación',
            default => 'Comprobante',
        };
    }

    public static function etiquetaCubeta(?string $cubeta): string
    {
        return match ($cubeta) {
            self::CUBETA_MN => 'AP MN',
            self::CUBETA_ME => 'AP ME',
            self::CUBETA_ANTICIPO => 'Anticipo',
            self::CUBETA_MIXTA => 'Mixta',
            default => '—',
        };
    }
}
