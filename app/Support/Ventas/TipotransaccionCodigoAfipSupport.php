<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\ParametroSistemaSupport;
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
            && ($totalComprobante ?? 0) >= ParametroSistemaSupport::limiteFce()
        ) {
            $tipo += 200;
        }

        return $tipo;
    }

    /**
     * Tipo AFIP inferido de una venta ya grabada (sin umbral FCE por monto).
     * El prefijo FCE/NCE/DCE del código manda: si no, FAC y FCE compartirían
     * codigo_afip 1 y chocarían el unique (PV + tipo + número).
     */
    public static function codigoAfipDesdeVentaGrabada(int|string $codigoAlmacenado, string $codigoVenta): int
    {
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $codigoVenta);
        $tipo = self::codigoAfipParaEmision($codigoAlmacenado, $letra);
        if ($tipo > 0 && $tipo < 200 && self::codigoVentaEsFce($codigoVenta)) {
            $tipo += 200;
        }

        return $tipo;
    }

    public static function codigoVentaEsFce(string $codigoVenta): bool
    {
        return (bool) preg_match('/^(FCE|NCE|DCE)\b/i', trim($codigoVenta));
    }

    /**
     * Tipo ARCA desde cabecera Anita (ven_tipo + ven_letra).
     * No usa la letra como clave: FAC A=001, FAC B=006, FCE A=201.
     * ven_tipo_comp de El Bierzo suele ser solo 2 dígitos: no alcanza para FCE.
     */
    public static function codigoAfipDesdeAnitaTipoLetra(string $venTipo, string $venLetra): int
    {
        $tipo = strtoupper(trim($venTipo));
        $letra = strtoupper(trim($venLetra));
        if ($tipo === '' || $letra === '') {
            return 0;
        }

        $base = match ($tipo) {
            'FAC', 'FAK' => 1,
            'ND' => 2,
            'NC', 'NCD', 'NCK' => 3,
            'REC' => 4,
            'FCE' => 201,
            'NDE' => 202,
            'NCE' => 203,
            'DCE' => 201,
            default => 0,
        };

        if ($base <= 0) {
            return 0;
        }

        return $base + self::offsetLetra($letra);
    }

    /**
     * Etiqueta de serie: la letra ya está en el tipo ARCA (001 FAC A, 006 FAC B).
     */
    public static function etiqueta(int $codigoAfip): string
    {
        $nombres = [
            1 => 'FAC A',
            2 => 'ND A',
            3 => 'NC A',
            4 => 'REC A',
            6 => 'FAC B',
            7 => 'ND B',
            8 => 'NC B',
            11 => 'FAC C',
            12 => 'ND C',
            13 => 'NC C',
            51 => 'FAC M',
            52 => 'ND M',
            53 => 'NC M',
            201 => 'FCE A',
            202 => 'NDE A',
            203 => 'NCE A',
            206 => 'FCE B',
            207 => 'NDE B',
            208 => 'NCE B',
            211 => 'FCE C',
        ];
        $pad = str_pad((string) $codigoAfip, 3, '0', STR_PAD_LEFT);
        if ($codigoAfip <= 0) {
            return '';
        }

        return isset($nombres[$codigoAfip])
            ? $nombres[$codigoAfip].' ('.$pad.')'
            : 'AFIP '.$pad;
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
