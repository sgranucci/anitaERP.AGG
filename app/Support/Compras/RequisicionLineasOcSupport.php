<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Requisicion_Articulo;

/**
 * Seguimiento de líneas de requisición respecto a órdenes de compra (generación parcial).
 */
class RequisicionLineasOcSupport
{
    public static function etiquetaLineaCerradaSinOc(): string
    {
        return 'Línea cerrada sin orden de compra: no se seleccionó origen de precio al generar desde la requisición.';
    }

    /**
     * @return list<int>
     */
    public static function idsLineasRequisicion(int $requisicionId): array
    {
        if ($requisicionId <= 0) {
            return [];
        }

        return Requisicion_Articulo::query()
            ->where('requisicion_id', $requisicionId)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Líneas ya incluidas en al menos una OC vinculada a la requisición.
     *
     * @return list<int>
     */
    public static function idsEnOrdencompra(int $requisicionId): array
    {
        if ($requisicionId <= 0) {
            return [];
        }

        $lineaIds = self::idsLineasRequisicion($requisicionId);
        if ($lineaIds === []) {
            return [];
        }

        return Ordencompra_Articulo::query()
            ->whereIn('requisicion_articulo_id', $lineaIds)
            ->whereNotNull('requisicion_articulo_id')
            ->pluck('requisicion_articulo_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Líneas cerradas explícitamente sin OC en el wizard.
     *
     * @return list<int>
     */
    public static function idsCerradosSinOc(int $requisicionId): array
    {
        if ($requisicionId <= 0) {
            return [];
        }

        return Requisicion_Articulo::query()
            ->where('requisicion_id', $requisicionId)
            ->where('precio_origen_etiqueta', self::etiquetaLineaCerradaSinOc())
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Ítems que aún pueden generar OC.
     *
     * @return list<int>
     */
    public static function idsPendientesOc(int $requisicionId): array
    {
        $excluir = array_unique(array_merge(
            self::idsEnOrdencompra($requisicionId),
            self::idsCerradosSinOc($requisicionId)
        ));

        return array_values(array_filter(
            self::idsLineasRequisicion($requisicionId),
            static fn (int $id) => ! in_array($id, $excluir, true)
        ));
    }

    public static function cuentaPendientesOc(int $requisicionId): int
    {
        return count(self::idsPendientesOc($requisicionId));
    }

    public static function todasLineasResueltas(int $requisicionId): bool
    {
        $lineas = self::idsLineasRequisicion($requisicionId);
        // Sin ítems no hay nada que “procesar”: no marcar GENERO ORDEN COMPRA.
        if ($lineas === []) {
            return false;
        }

        return self::cuentaPendientesOc($requisicionId) === 0;
    }
}
