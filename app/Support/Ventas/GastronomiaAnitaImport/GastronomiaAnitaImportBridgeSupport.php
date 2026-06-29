<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\Support\Stock\StockAnitaBridgeSupport;

/**
 * Bridge Informix por empresa para importación gastronomía (venta, stkmov, resvta, vengrav, vencae).
 * 1=Biyemas → ANITA_IP global; 2=Kandiko → kancadmin; 3=Rebisco → rencadmin.
 */
final class GastronomiaAnitaImportBridgeSupport
{
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
