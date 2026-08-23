<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Contable\PeriodoContableCierreSupport;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

/**
 * Fecha contable del comprobante de proveedor = fecha de IVA = fecha de carga / contabilización.
 * Se fija el día del alta y no se puede cambiar en el formulario.
 * Manda en asiento, CC, Anita (promov/ctamov/fecha IVA) y cierre de período.
 * fechacomprobante es informativa (libro IVA compras); no define período ni posteos.
 */
final class ComprobanteProveedorFechaContableSupport
{
    public static function fechaCargaHoy(): string
    {
        return now()->format('Y-m-d');
    }

    /**
     * Alta: hoy. Edición: la fechaiva ya grabada (ignora lo que mande el form).
     */
    public static function inmodificableEnCarga(?Comprobante_Proveedor $existente): string
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
