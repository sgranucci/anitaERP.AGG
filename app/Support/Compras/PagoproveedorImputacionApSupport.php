<?php

namespace App\Support\Compras;

/**
 * Control OP a OP: CC ERP ↔ asiento ERP (trío AP/anticipo) ↔ promov Anita ↔ ctamov.
 *
 * Convenio Haber−Debe (mismo que facturas): Haber suma, Debe resta.
 * OPP/OPA abren crédito (CC < 0, Debe AP/anticipo). AOP invierte el signo.
 */
final class PagoproveedorImputacionApSupport
{
    public const TIPO_OPP = 'OPP';

    public const TIPO_OPA = 'OPA';

    public const TIPO_AOP = 'AOP';

    public const TOLERANCIA = ComprobanteProveedorImputacionApSupport::TOLERANCIA;

    public const ESTADOS_ACTIVOS = ['CONFIRMADA', 'PAGADA', 'CONCILIADA'];

    public const ESTADOS_BORRADOR = ['PRE CARGA'];

    public const ESTADOS_ANULADOS = ['REVERTIDA', 'BAJA'];

    public static function tipoDesdeComprobante(?string $tipocomprobante): string
    {
        $tipo = strtoupper(substr(trim((string) $tipocomprobante), 0, 3));

        return match ($tipo) {
            self::TIPO_OPA => self::TIPO_OPA,
            self::TIPO_AOP => self::TIPO_AOP,
            default => self::TIPO_OPP,
        };
    }

    /**
     * Signo Haber−Debe de la cabecera de la OP (crédito OPP/OPA = negativo).
     */
    public static function signoHaberNeto(string $tipo): int
    {
        return $tipo === self::TIPO_AOP ? 1 : -1;
    }

    public static function esBorrador(?string $estado): bool
    {
        return in_array(strtoupper(trim((string) $estado)), self::ESTADOS_BORRADOR, true);
    }

    public static function esAnulado(?string $estado): bool
    {
        return in_array(strtoupper(trim((string) $estado)), self::ESTADOS_ANULADOS, true);
    }

    /**
     * OP nacida en Ingreso/Egreso (SP o tipo ING/EGR/TRA): sale de este control.
     */
    public static function esOrigenIngresoEgreso(?int $solicitudpagoId, ?string $abreviaturaTipoCaja): bool
    {
        if ((int) ($solicitudpagoId ?? 0) > 0) {
            return true;
        }

        $tipo = strtoupper(substr(trim((string) $abreviaturaTipoCaja), 0, 3));

        return in_array($tipo, ['ING', 'EGR', 'TRA'], true);
    }

    /**
     * Tesorería estilo I/E: asiento sin trío AP/anticipo y sin CC de proveedor.
     * Sale de este control (canon 215010, gasto, etc.) y va al de Ingreso/Egreso.
     */
    public static function esPagoSinTrioAp(
        bool $tieneCc,
        bool $tieneAsiento,
        float $asientoTrioArs,
        float $tolerancia = self::TOLERANCIA,
    ): bool {
        if ($tieneCc || ! $tieneAsiento) {
            return false;
        }

        return abs($asientoTrioArs) <= $tolerancia;
    }

    /**
     * Cabecera importada desde Anita solo como documento (sin CC / asiento ERP).
     * La contabilidad vive en Anita; no es un desvío de las cuatro patas ERP.
     */
    public static function esCabeceraAnitaSinContabilidad(
        bool $tieneCc,
        bool $tieneAsiento,
        ?string $detalle = null,
        ?string $observacionEstado = null,
    ): bool {
        if ($tieneCc || $tieneAsiento) {
            return false;
        }

        return self::marcaImportAnitaSinCc($detalle)
            || self::marcaImportAnitaSinCc($observacionEstado);
    }

    public static function marcaImportAnitaSinCc(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return str_contains($t, 'importado desde anita')
            && str_contains($t, 'sin cuenta corriente');
    }

    /**
     * @return array{
     *     ok: bool,
     *     alertas: list<string>,
     *     diff_cc_asiento: float,
     *     diff_asiento_ctamov: float,
     *     diff_cc_ctamov: float,
     *     diff_cc_promov: float,
     *     diff_promov_asiento: float,
     *     diff_promov_ctamov: float,
     *     diff_cc_op: float|null
     * }
     */
    public static function evaluarCuatroPatas(
        float $ccErpArs,
        float $asientoArs,
        float $ccAnitaArs,
        float $ctamovArs,
        bool $tieneCc,
        bool $tieneAsiento,
        bool $tienePromov,
        bool $tieneCtamov,
        float $tolerancia = self::TOLERANCIA,
        float $asientoAnticipoArs = 0.0,
        float $ctamovAnticipoArs = 0.0,
        ?float $esperadoOpArs = null,
    ): array {
        $alertas = [];
        if (! $tieneCc) {
            $alertas[] = 'Sin CC';
        }
        if (! $tieneAsiento) {
            $alertas[] = 'Sin asiento';
        }
        if (! $tienePromov) {
            $alertas[] = 'Sin promov Anita';
        }
        if (! $tieneCtamov) {
            $alertas[] = 'Sin ctamov Anita';
        }

        if ($tieneCc && $tieneAsiento
            && ComprobanteProveedorImputacionApSupport::desvia($asientoArs, $ccErpArs, $tolerancia)) {
            $alertas[] = 'CC ≠ asiento';
        }
        if ($tieneAsiento && $tieneCtamov
            && ComprobanteProveedorImputacionApSupport::desvia($ctamovArs, $asientoArs, $tolerancia)) {
            $alertas[] = 'Asiento ≠ ctamov';
        }
        if ($tieneCc && $tieneCtamov
            && ComprobanteProveedorImputacionApSupport::desvia($ctamovArs, $ccErpArs, $tolerancia)) {
            $alertas[] = 'CC ≠ ctamov';
        }
        if ($tienePromov && $esperadoOpArs !== null
            && ComprobanteProveedorImputacionApSupport::desvia($ccAnitaArs, $esperadoOpArs, $tolerancia)) {
            $alertas[] = 'Promov ≠ OP';
        }
        if ($tieneAsiento && $tieneCtamov
            && ComprobanteProveedorImputacionApSupport::desvia($asientoAnticipoArs, $ctamovAnticipoArs, $tolerancia)) {
            $alertas[] = 'Anticipo asiento ≠ ctamov';
        }

        return [
            'ok' => $alertas === [],
            'alertas' => $alertas,
            'diff_cc_asiento' => round($asientoArs - $ccErpArs, 2),
            'diff_asiento_ctamov' => round($ctamovArs - $asientoArs, 2),
            'diff_cc_ctamov' => round($ctamovArs - $ccErpArs, 2),
            'diff_cc_promov' => round($ccAnitaArs - $ccErpArs, 2),
            'diff_promov_asiento' => round($ccAnitaArs - $asientoArs, 2),
            'diff_promov_ctamov' => round($ccAnitaArs - $ctamovArs, 2),
            'diff_cc_op' => $esperadoOpArs === null ? null : round($ccErpArs - $esperadoOpArs, 2),
        ];
    }

    /**
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

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_OPA => 'OPA',
            self::TIPO_AOP => 'AOP',
            default => 'OPP',
        };
    }
}
