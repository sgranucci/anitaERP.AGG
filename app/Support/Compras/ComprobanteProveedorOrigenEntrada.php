<?php

namespace App\Support\Compras;

/**
 * Origen de alta del comprobante de proveedor (independiente de modo_carga stock/contable).
 */
final class ComprobanteProveedorOrigenEntrada
{
    /** Datos desde precarga del agente/API (AGG u otro cliente). */
    public const PRECARGA = 'PRECARGA';

    /** Alta vinculada a orden de compra sin precarga previa. */
    public const ORDENCOMPRA = 'ORDENCOMPRA';

    /** Alta manual: sin precarga ni OC obligatoria al inicio. */
    public const MANUAL = 'MANUAL';

    /** Precarga generada por el modelo IA propio (PDF). */
    public const PDF_IA = 'PDF_IA';

    /** Alta desde ingresos y egresos de caja (fondo fijo, gastos banco, etc.). */
    public const INGRESO_EGRESO = 'INGRESO_EGRESO';

    /** Histórico importado desde Anita (compra/promov/aplmovp). */
    public const ANITA_IMPORT = 'ANITA_IMPORT';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PRECARGA,
            self::ORDENCOMPRA,
            self::MANUAL,
            self::PDF_IA,
            self::INGRESO_EGRESO,
            self::ANITA_IMPORT,
        ];
    }

    public static function etiqueta(string $origen): string
    {
        return match ($origen) {
            self::PRECARGA => 'Desde precarga (agente/API)',
            self::ORDENCOMPRA => 'Desde orden de compra',
            self::MANUAL => 'Sin OC (manual)',
            self::PDF_IA => 'PDF — modelo IA Anita',
            self::INGRESO_EGRESO => 'Ingresos y egresos (tesorería)',
            self::ANITA_IMPORT => 'Importado desde Anita',
            default => $origen,
        };
    }

    public static function esIngresoEgreso(?string $origen): bool
    {
        return $origen === self::INGRESO_EGRESO;
    }

    /**
     * Determina el origen al crear (precarga gana si ambos FK presentes).
     */
    public static function resolver(
        ?int $precargaComprobanteProveedorId,
        ?int $ordencompraId,
        ?string $origenForzado = null,
    ): string {
        if ($origenForzado && in_array($origenForzado, self::todos(), true)) {
            return $origenForzado;
        }

        if ($precargaComprobanteProveedorId) {
            return self::PRECARGA;
        }

        if ($ordencompraId) {
            return self::ORDENCOMPRA;
        }

        return self::MANUAL;
    }
}
