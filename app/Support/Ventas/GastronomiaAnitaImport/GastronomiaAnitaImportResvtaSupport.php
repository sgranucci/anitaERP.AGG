<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\ApiAnita;

/**
 * Cabeceras Anita con fila en resvta provienen del POS legacy (Anita), no de AnitaERP gastronomía.
 */
final class GastronomiaAnitaImportResvtaSupport
{
    /**
     * @param  list<int>  $numeros  Si vacío, no consulta Anita.
     * @return array<int, true> Números de comprobante con al menos un resvta en la sucursal.
     */
    public static function numerosConResvtaEnSucursal(
        int $sucursal,
        string|int|null $empresaCodigo = null,
        array $numeros = [],
        int $empresaId = 0,
    ): array {
        if ($sucursal <= 0 || $numeros === []) {
            return [];
        }

        $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros), static fn (int $n): bool => $n > 0)));
        if ($numeros === []) {
            return [];
        }

        $api = new ApiAnita;
        $map = [];

        foreach (array_chunk($numeros, 200) as $lote) {
            $in = implode(',', $lote);
            // Sin filtro ven_empresa/resv_empresa: legacy POS suele grabar resv_empresa=-1.
            // Los números ya provienen de cabeceras venta filtradas por PV/empresa en la auditoría.
            $where = " WHERE resv_sucursal = '".$sucursal."'"
                ." AND resv_letra = 'B' "
                ." AND resv_nro IN (".$in.") ";

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall(
                GastronomiaAnitaImportBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => 'resvta',
                'campos' => 'resv_tipo,resv_nro',
                'whereArmado' => $where,
                'orderBy' => 'resv_nro',
            ], $empresaId)));

            foreach ($parsed['filas'] ?? [] as $row) {
                $n = (int) ($row->resv_nro ?? 0);
                if ($n > 0) {
                    $map[$n] = true;
                }
            }
        }

        return $map;
    }

    public static function tieneResvtaEnSucursal(
        int $sucursal,
        int $numero,
        string|int|null $empresaCodigo = null,
        int $empresaId = 0,
    ): bool {
        $map = self::numerosConResvtaEnSucursal($sucursal, $empresaCodigo, [$numero], $empresaId);

        return isset($map[$numero]);
    }
}
