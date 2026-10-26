<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Contable\PeriodoContableCierreSupport;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

/**
 * Fecha contable del comprobante de proveedor = fecha de IVA = fecha de contabilización.
 * Manda en asiento, CC y Anita (promov/ctamov/fecha IVA), y define el período del libro IVA compras.
 * La fecha que se informa en el archivo de IVA digital es la impresa del comprobante.
 * Se puede correr hacia atrás. Hacia adelante, el tope es hoy en el alta.
 * En edición arranca en la fecha ya grabada y sube hasta la fecha impresa del
 * comprobante, y hasta el primer día operable si la grabada cayó en un período
 * cerrado. Nunca posterior a hoy.
 * La fecha impresa del comprobante no puede ser posterior a la de contabilización.
 * Un período cerrado la frena igual que cualquier otra operación.
 * fechacomprobante es la fecha impresa de la factura y no define el período.
 */
final class ComprobanteProveedorFechaContableSupport
{
    public static function fechaCargaHoy(): string
    {
        return now()->format('Y-m-d');
    }

    /**
     * Alta de factura. Por defecto el día de carga.
     * En Ferli, cuando la factura entra por IA, la contabilización es la fecha impresa
     * (sin pasar de hoy: hacia adelante el tope sigue siendo el día de carga).
     */
    public static function fechaContabilizacionEnAlta(
        mixed $fechaFactura,
        bool $usarFechaFactura,
        ?string $hoy = null,
    ): string {
        $hoyYmd = self::formatear($hoy) ?? self::fechaCargaHoy();
        if (! $usarFechaFactura) {
            return $hoyYmd;
        }

        $factura = self::formatear($fechaFactura) ?? $hoyYmd;

        return $factura <= $hoyYmd ? $factura : $hoyYmd;
    }

    /**
     * Vencimiento de pago ausente: el día de contabilización.
     * Una fecha ya leída (factura, cuota u orden de compra) se conserva.
     */
    public static function vencimientoOContabilizacion(mixed $vencimiento, mixed $fechaContabilizacion): string
    {
        return self::formatear($vencimiento)
            ?? self::formatear($fechaContabilizacion)
            ?? self::fechaCargaHoy();
    }

    /**
     * Tope de la fecha de contabilización, sin pasar de hoy.
     * En edición arranca en la fecha ya grabada. Sube si la fecha impresa es posterior
     * (si no, el comprobante no se puede corregir) y si esa fecha cayó en un período
     * cerrado (hasta el primer día operable).
     */
    public static function techoContabilizacion(
        string $hoy,
        ?string $fechaIvaGuardada,
        ?string $fechaComprobante,
        ?string $fechaMinimaOperable,
    ): string {
        $hoyYmd = self::formatear($hoy) ?? self::fechaCargaHoy();
        $tope = $hoyYmd;
        $iva = self::formatear($fechaIvaGuardada);
        if ($iva !== null && $iva < $tope) {
            $tope = $iva;
        }
        $comp = self::formatear($fechaComprobante);
        if ($comp !== null && $comp > $tope && $comp <= $hoyYmd) {
            $tope = $comp;
        }
        $min = self::formatear($fechaMinimaOperable);
        if ($min !== null && $min > $tope && $min <= $hoyYmd) {
            $tope = $min;
        }

        return $tope;
    }

    /**
     * Tope: en el alta, hoy. En edición, la fecha ya grabada si es anterior a hoy,
     * elevada a la fecha del comprobante o al primer día operable cuando haga falta.
     * $fechaComprobante es la del formulario; si no viene, se usa la grabada.
     */
    public static function fechaTopeEnCarga(?Comprobante_Proveedor $existente, mixed $fechaComprobante = null): string
    {
        $hoy = self::fechaCargaHoy();
        if (! $existente) {
            return self::techoContabilizacion($hoy, null, null, null);
        }

        $comp = self::formatear($fechaComprobante) ?? self::formatear($existente->fechacomprobante ?? null);

        return self::techoContabilizacion(
            $hoy,
            self::formatear($existente->fechaiva ?? null),
            $comp,
            self::fechaMinimaOperable((int) ($existente->empresa_id ?? 0)),
        );
    }

    /**
     * La fecha impresa no puede pasar la de contabilización, y esa no puede pasar hoy.
     * $hoyYmd solo para pruebas; en carga se usa el día del servidor.
     */
    public static function assertFechasCargaCoherentes(
        mixed $fechaComprobante,
        mixed $fechaContabilizacion,
        ?string $hoyYmd = null,
    ): void {
        $hoy = self::formatear($hoyYmd) ?? self::fechaCargaHoy();
        $contab = self::formatear($fechaContabilizacion) ?? $hoy;

        if ($contab > $hoy) {
            throw new RuntimeException(
                'La fecha de contabilización ('.self::aDiaMesAnio($contab).') no puede ser posterior a hoy ('
                .self::aDiaMesAnio($hoy).').'
            );
        }

        $comp = self::formatear($fechaComprobante);
        if ($comp !== null && $comp > $contab) {
            throw new RuntimeException(
                'La fecha del comprobante ('.self::aDiaMesAnio($comp).') no puede ser posterior a la fecha de contabilización ('
                .self::aDiaMesAnio($contab).').'
            );
        }
    }

    /**
     * Fecha pedida en el formulario, sin pasar el tope.
     * Vacía = el tope. Posterior al tope = error.
     */
    public static function resolverEnCarga(
        mixed $solicitada,
        ?Comprobante_Proveedor $existente,
        mixed $fechaComprobante = null,
    ): string {
        return self::fechaNoPosterior($solicitada, self::fechaTopeEnCarga($existente, $fechaComprobante));
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

    public static function anioMinimoComprobante(): int
    {
        return max(1900, (int) config('comprobante_proveedor.fecha_comprobante_anio_minimo', 2000));
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
     * Evita un año o un mes mal tipeado: ni 30 días después de hoy, ni un año
     * anterior al mínimo (0026 en lugar de 2026). Una factura atrasada de este
     * siglo sigue siendo válida.
     */
    public static function assertFechaComprobanteNoExcesivamenteFutura(
        mixed $fechaComprobante,
        ?string $fechaReferenciaYmd = null,
        ?int $maxDias = null,
        ?int $anioMinimo = null,
    ): void {
        $fecha = self::formatear($fechaComprobante);
        if ($fecha === null) {
            return;
        }

        $anioPiso = $anioMinimo ?? self::anioMinimoComprobante();
        $anio = (int) substr($fecha, 0, 4);
        if ($anio < $anioPiso) {
            throw new RuntimeException(
                'La fecha del comprobante ('.self::aDiaMesAnio($fecha).') tiene un año anterior a '
                .$anioPiso.'. Revisá el año: parece un error de carga.'
            );
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
