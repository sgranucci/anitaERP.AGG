<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Importe declarado en el COT (valor de la mercadería, sin IVA).
 *
 * En El Bierzo `comprob` suele traer un peso de relleno (gravado 0 + exento 1).
 * p-cot.c usa ven_gravado + ven_gravado_ot + ven_exento de la tabla venta Anita.
 */
final class CotImporteRemitoSupport
{
    public const PLACEHOLDER_MAX = 1.005;

    public static function esPlaceholder(float $importe): bool
    {
        return abs($importe) <= self::PLACEHOLDER_MAX;
    }

    public static function desdeAnitaVenta(?object $venta): float
    {
        if ($venta === null) {
            return 0.0;
        }

        return abs((float) ($venta->ven_gravado ?? 0))
            + abs((float) ($venta->ven_gravado_ot ?? 0))
            + abs((float) ($venta->ven_exento ?? 0));
    }

    public static function desdeAnitaComprob(?object $comprob): float
    {
        if ($comprob === null) {
            return 0.0;
        }

        $importe = abs((float) ($comprob->comp_gravado ?? 0))
            + abs((float) ($comprob->comp_exento ?? 0));
        if (self::esPlaceholder($importe)) {
            $importe = abs((float) ($comprob->comp_total ?? 0))
                - abs((float) ($comprob->comp_iva ?? 0));
        }

        return $importe;
    }

    public static function preferir(float ...$candidatos): float
    {
        return self::primeroValido(...$candidatos) ?? round(abs($candidatos[0] ?? 0.0), 2);
    }

    public static function esValidoParaCot(float $importe): bool
    {
        return ! self::esPlaceholder($importe);
    }

    public static function primeroValido(float ...$candidatos): ?float
    {
        foreach ($candidatos as $candidato) {
            if (self::esValidoParaCot($candidato)) {
                return round(abs($candidato), 2);
            }
        }

        return null;
    }

    /**
     * Neto gravado + exento (+ no gravado) de una factura ERP.
     *
     * @param  array<string, mixed>|null  $desglose
     */
    public static function desdeDesgloseErp(?array $desglose): float
    {
        if ($desglose === null) {
            return 0.0;
        }

        return abs((float) ($desglose['neto_gravado'] ?? 0))
            + abs((float) ($desglose['exento'] ?? 0))
            + abs((float) ($desglose['no_gravado'] ?? 0));
    }

    /**
     * Remito físico Anita sin factura: p-cot usa tot_seguro si hay, si no penm_neto.
     */
    public static function desdeAnitaPendmae(?object $pendmae): float
    {
        if ($pendmae === null) {
            return 0.0;
        }

        return self::primeroValido(
            abs((float) ($pendmae->penm_tot_seguro ?? 0)),
            abs((float) ($pendmae->penm_neto ?? 0)),
        ) ?? 0.0;
    }

    public static function esOrigenFactura(?string $origen): bool
    {
        return in_array($origen, ['factura', 'factura_anita', 'factura_erp'], true);
    }

    /**
     * @param  array<string, float>  $candidatos  origen => importe, en orden de prioridad
     * @return array{importe: float|null, origen: string|null}
     */
    public static function resolver(array $candidatos): array
    {
        foreach ($candidatos as $origen => $importe) {
            if (self::esValidoParaCot((float) $importe)) {
                return [
                    'importe' => round(abs((float) $importe), 2),
                    'origen' => (string) $origen,
                ];
            }
        }

        return ['importe' => null, 'origen' => null];
    }

    public static function etiquetaOrigen(?string $origen): string
    {
        return match ($origen) {
            'factura_anita' => 'Factura Anita',
            'factura_erp' => 'Factura ERP',
            'factura' => 'Factura',
            'remito_anita' => 'Remito Anita (sin factura)',
            'remito_lineas' => 'Líneas Anita (sin factura)',
            'remito_erp' => 'Remito ERP (sin factura)',
            default => 'Sin importe',
        };
    }

    public static function motivoSinImporte(): string
    {
        return 'Sin importe de factura ni de remito (neto/seguro/líneas). No se presenta el COT con $1.';
    }

    public static function motivoSinFactura(): string
    {
        return self::motivoSinImporte();
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public static function aplicarAFila(array $fila, ?float $importe, ?string $origen = 'factura'): array
    {
        if (self::esValidoParaCot((float) ($importe ?? 0))) {
            $fila['importe'] = round(abs((float) $importe), 2);
            $fila['importe_ok'] = true;
            $fila['importe_placeholder'] = false;
            $fila['importe_desde_factura'] = self::esOrigenFactura($origen);
            $fila['importe_origen'] = $origen;
            $fila['importe_origen_etiqueta'] = self::etiquetaOrigen($origen);
            $fila['importe_motivo'] = null;

            return $fila;
        }

        $fila['importe'] = 0.0;
        $fila['importe_ok'] = false;
        $fila['importe_placeholder'] = true;
        $fila['importe_desde_factura'] = false;
        $fila['importe_origen'] = null;
        $fila['importe_origen_etiqueta'] = self::etiquetaOrigen(null);
        $fila['importe_motivo'] = self::motivoSinImporte();

        return $fila;
    }
}
