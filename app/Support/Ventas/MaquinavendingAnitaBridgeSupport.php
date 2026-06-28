<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Support\Stock\StockAnitaBridgeSupport;

/**
 * Bridge Anita por empresa para máquinas vending (maqvmae / ubimvending, sistema ventas).
 * Biyemas → empresa 1 (bridge central); Kandiko → 2; Rebisco → 3.
 */
final class MaquinavendingAnitaBridgeSupport
{
    /**
     * @return array{servidor?: string, path_sistema?: string, sistema: string, ifx_server?: string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        return StockAnitaBridgeSupport::parametrosBridge($empresaId);
    }

    /**
     * @return list<object>
     */
    public static function listarMaquinas(int $empresaId, ?string $whereArmado = null): array
    {
        $payload = StockAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => (string) config('maquinavending_anita.tabla_maquina', 'maqvmae'),
            'campos' => (string) config('maquinavending_anita.campos_maquina'),
            'orderBy' => 'maqvm_codigo',
        ], $empresaId);

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode((new ApiAnita)->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<object>
     */
    public static function listarArticulos(int $empresaId, ?string $whereArmado = null): array
    {
        $payload = StockAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => (string) config('maquinavending_anita.tabla_articulo', 'ubimvending'),
            'campos' => (string) config('maquinavending_anita.campos_articulo'),
            'orderBy' => 'ubimv_codigo, ubimv_ubicacion',
        ], $empresaId);

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode((new ApiAnita)->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }
}
