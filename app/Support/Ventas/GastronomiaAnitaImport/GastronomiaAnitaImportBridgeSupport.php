<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\Support\Stock\StockAnitaBridgeSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;

/**
 * Bridge Informix por empresa para importación gastronomía (stkmov, resvta, vengrav, vencae).
 * Cabecera venta en AGG: bridge central + ven_empresa (misma ruta que grabaAnita).
 * 1=Biyemas → ANITA_IP global; 2=Kandiko → kancadmin; 3=Rebisco → rencadmin (detalle/stock).
 */
final class GastronomiaAnitaImportBridgeSupport
{
    /**
     * Bridge para lectura/auditoría de cabecera venta (tabla venta).
     * En AGG las cabeceras viven en Informix central con ven_empresa; no en kancadmin/rencadmin.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePayloadVentaCabecera(array $payload, int $empresaId): array
    {
        if (GastronomiaAnitaImportEmpresaSupport::usaFiltroEmpresaAnita()) {
            return $payload;
        }

        return self::mergePayload($payload, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePayload(array $payload, int $empresaId): array
    {
        return StockAnitaBridgeSupport::mergePayload($payload, max(1, $empresaId));
    }

    /**
     * @return array{servidor?: string, path_sistema?: string, sistema: string, ifx_server?: string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        return StockAnitaBridgeSupport::parametrosBridge(max(1, $empresaId));
    }
}
