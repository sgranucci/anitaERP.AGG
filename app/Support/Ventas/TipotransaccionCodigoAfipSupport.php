<?php

namespace App\Support\Ventas;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;

/**
 * Tipo de comprobante AFIP efectivo a partir de tipotransaccion.codigo (base letra A o código ARCA final).
 *
 * Varios tipotransaccion_id distintos pueden compartir el mismo codigo almacenado (ej. 003): la numeración
 * fiscal es por tipo AFIP + PV, no por fila del ABM.
 */
final class TipotransaccionCodigoAfipSupport
{
    /** Códigos Anita legacy base letra A (001, 002, 003…). */
    private const LEGACY_BASE_LETRA_A_MAX = 5;

    /**
     * Código AFIP de emisión (WSFE / numeración CAEA).
     */
    public static function codigoAfipParaEmision(
        int|string $codigoAlmacenado,
        string $letra,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        $codigo = (int) preg_replace('/\D+/', '', (string) $codigoAlmacenado);

        if ($codigo <= 0) {
            return 0;
        }

        if (self::esCodigoAlmacenadoTipoAfipFinal($codigo)) {
            return $codigo;
        }

        $tipo = (int) LibroIvaDigitalMapeosSupport::tipoComprobanteVentas((string) $codigo, $letra);

        if (
            ($modoFacturacionCliente ?? '') === 'C'
            && $codigo < 200
            && ($totalComprobante ?? 0) >= (float) config('facturacion.LIMITE_FCE', 0)
        ) {
            $tipo += 200;
        }

        return $tipo;
    }

    /**
     * Tipo AFIP inferido de una venta ya grabada (sin umbral FCE por monto).
     */
    public static function codigoAfipDesdeVentaGrabada(int|string $codigoAlmacenado, string $codigoVenta): int
    {
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $codigoVenta);

        return self::codigoAfipParaEmision($codigoAlmacenado, $letra);
    }

    /**
     * @return list<int>
     */
    public static function codigosBaseAlmacenadosPosibles(int $codigoAfipObjetivo, string $letra): array
    {
        if ($codigoAfipObjetivo <= 0) {
            return [];
        }

        $offset = self::offsetLetra($letra);
        $bases = [];

        if ($codigoAfipObjetivo >= 200) {
            if (self::esCodigoAlmacenadoTipoAfipFinal($codigoAfipObjetivo)) {
                $bases[] = $codigoAfipObjetivo;
            }

            $fceBaseLetraA = $codigoAfipObjetivo - $offset;
            if ($fceBaseLetraA >= 200) {
                $bases[] = $fceBaseLetraA;
            }

            return array_values(array_unique(array_filter($bases, static fn (int $b): bool => $b > 0)));
        }

        if (self::esCodigoAlmacenadoTipoAfipFinal($codigoAfipObjetivo)) {
            $bases[] = $codigoAfipObjetivo;
        }

        $legacyBase = $codigoAfipObjetivo - $offset;
        if ($legacyBase >= 1 && $legacyBase <= self::LEGACY_BASE_LETRA_A_MAX) {
            $bases[] = $legacyBase;
        }

        return array_values(array_unique(array_filter($bases, static fn (int $b): bool => $b > 0)));
    }

    /**
     * El catálogo ARCA del ABM puede guardar el tipo final (006, 008, 201…).
     * Legacy Anita guarda solo la base letra A (001–005).
     */
    public static function esCodigoAlmacenadoTipoAfipFinal(int $codigo): bool
    {
        if ($codigo <= self::LEGACY_BASE_LETRA_A_MAX) {
            return false;
        }

        // 99 = transferencias internas (no es tipo AFIP de venta).
        return $codigo !== 99;
    }

    private static function offsetLetra(string $letra): int
    {
        $map = [
            'A' => 0,
            'B' => 5,
            'C' => 10,
            'M' => 50,
            'E' => 18,
            'T' => 194,
        ];

        return $map[strtoupper(trim($letra))] ?? 0;
    }
}
