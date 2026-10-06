<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Contable\PeriodoContableCierreSupport;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

/**
 * Fecha contable del comprobante de proveedor = fecha de IVA = fecha de contabilización.
 * Manda en asiento, CC, Anita (promov/ctamov/fecha IVA) y en el libro IVA compras.
 * Se puede correr hacia atrás. Nunca hacia adelante del tope (hoy en el alta, la fecha
 * ya grabada en la edición). Un período cerrado la frena igual que cualquier otra operación.
 * fechacomprobante es la fecha impresa de la factura y no define el período.
 */
final class ComprobanteProveedorFechaContableSupport
{
    public static function fechaCargaHoy(): string
    {
        return now()->format('Y-m-d');
    }

    /**
     * Tope: en el alta, hoy. En edición, la fecha de contabilización ya grabada.
     */
    public static function fechaTopeEnCarga(?Comprobante_Proveedor $existente): string
    {
        if ($existente) {
            $iva = self::formatear($existente->fechaiva ?? null);
            if ($iva !== null) {
                return $iva;
            }
        }

        return self::fechaCargaHoy();
    }

    /**
     * Fecha pedida en el formulario, sin pasar el tope.
     * Vacía = el tope. Posterior al tope = error.
     */
    public static function resolverEnCarga(mixed $solicitada, ?Comprobante_Proveedor $existente): string
    {
        return self::fechaNoPosterior($solicitada, self::fechaTopeEnCarga($existente));
    }

    /**
     * Primer día operable de cuentas a pagar: el día siguiente al cierre vigente.
     * Null si no hay cierre, o si el usuario puede operar en período cerrado.
     */
    public static function fechaMinimaOperable(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }

        if (function_exists('can') && can(PeriodoContableCierreSupport::SLUG_OPERAR_CERRADO, false)) {
            return null;
        }

        $cierre = PeriodoContableCierreSupport::fechaCierreVigente(
            $empresaId,
            PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR
        );
        if ($cierre === null) {
            return null;
        }

        return $cierre->copy()->addDay()->format('Y-m-d');
    }

    public static function fechaNoPosterior(mixed $solicitada, string $anclaYmd): string
    {
        $ancla = self::formatear($anclaYmd) ?? self::fechaCargaHoy();
        $fecha = self::formatear($solicitada) ?? $ancla;

        if ($fecha > $ancla) {
            throw new RuntimeException(
                'La fecha de contabilización ('.self::aDiaMesAnio($fecha).') no puede ser posterior a '
                .self::aDiaMesAnio($ancla).'. Solo se puede correr hacia atrás.'
            );
        }

        return $fecha;
    }

    /**
     * Fecha de contabilización. Nunca usa fechacomprobante.
     */
    public static function fechaYmd(Comprobante_Proveedor $comprobante): string
    {
        return self::formatear($comprobante->fechaiva ?? null) ?? self::fechaCargaHoy();
    }

    /**
     * Fecha de contabilización desde un payload de alta/edición.
     * Si no hay fechaiva, es el día de carga (no la del comprobante).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fechaYmdDesdePayload(array $payload): string
    {
        return self::formatear($payload['fechaiva'] ?? null) ?? self::fechaCargaHoy();
    }

    public static function maxDiasFuturoComprobante(): int
    {
        return max(0, (int) config('comprobante_proveedor.fecha_comprobante_max_dias_futuro', 30));
    }

    public static function fechaComprobanteMaximaYmd(?string $fechaReferenciaYmd = null, ?int $maxDias = null): string
    {
        $ref = self::formatear($fechaReferenciaYmd) ?? self::fechaCargaHoy();
        $dias = $maxDias ?? self::maxDiasFuturoComprobante();

        return (new DateTimeImmutable($ref))
            ->modify('+'.$dias.' days')
            ->format('Y-m-d');
    }

    /**
     * Evita cargar una factura con fecha muy a futuro (año/mes mal tipeado).
     * El pasado no se limita: una factura atrasada es válida.
     */
    public static function assertFechaComprobanteNoExcesivamenteFutura(
        mixed $fechaComprobante,
        ?string $fechaReferenciaYmd = null,
        ?int $maxDias = null,
    ): void {
        $fecha = self::formatear($fechaComprobante);
        if ($fecha === null) {
            return;
        }

        $dias = $maxDias ?? self::maxDiasFuturoComprobante();
        $limite = self::fechaComprobanteMaximaYmd($fechaReferenciaYmd, $dias);
        if ($fecha > $limite) {
            $ref = self::formatear($fechaReferenciaYmd) ?? self::fechaCargaHoy();
            throw new RuntimeException(
                'La fecha del comprobante ('.self::aDiaMesAnio($fecha).') no puede ser más de '
                .$dias.' días posterior a hoy ('.self::aDiaMesAnio($ref).'). '
                .'Revisá el año o el mes: parece un error de carga.'
            );
        }
    }

    private static function aDiaMesAnio(string $ymd): string
    {
        return (new DateTimeImmutable($ymd))->format('d/m/Y');
    }

    public static function assertPeriodoContablePermitido(int $empresaId, string $fechaContabilizacion): void
    {
        if ($empresaId <= 0) {
            return;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaContabilizacion,
            PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR
        );
    }

    public static function formatear(mixed $fecha): ?string
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $fecha);

        return $texto !== '' ? substr($texto, 0, 10) : null;
    }
}
