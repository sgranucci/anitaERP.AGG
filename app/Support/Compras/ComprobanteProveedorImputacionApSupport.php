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
     * Control diario: CC ERP vs haber AP del asiento vs haber AP de ctamov (Haber−Debe en $).
     * El anticipo de una factura anticipada se controla aparte (no entra al neto vs CC).
     *
     * @return array{
     *     ok: bool,
     *     alertas: list<string>,
     *     diff_cc_asiento: float,
     *     diff_asiento_ctamov: float,
     *     diff_cc_ctamov: float
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
