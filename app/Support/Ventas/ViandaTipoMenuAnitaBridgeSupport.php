<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Support\Stock\StockAnitaBridgeSupport;

final class ViandaTipoMenuAnitaBridgeSupport
{
    /**
     * @return list<object>
     */
    public static function listarTiposMenu(int $empresaId, ?string $whereArmado = null): array
    {
        $payload = StockAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => (string) config('vianda_anita.tabla_tipo_menu', 'tipomvianda'),
            'campos' => (string) config('vianda_anita.campos_tipo_menu'),
            'orderBy' => 'tipom_codigo',
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
            'tabla' => (string) config('vianda_anita.tabla_articulo', 'artmvianda'),
            'campos' => (string) config('vianda_anita.campos_articulo'),
            'orderBy' => 'artm_codigo, artm_dia, artm_articulo',
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
    public static function listarUsuarios(int $empresaId, ?string $whereArmado = null): array
    {
        $payload = StockAnitaBridgeSupport::mergePayload([
            'acc' => 'list',
            'tabla' => (string) config('vianda_anita.tabla_usuario', 'usuvianda'),
            'campos' => (string) config('vianda_anita.campos_usuario'),
            'orderBy' => 'usuv_usuario',
        ], $empresaId);

        if ($whereArmado !== null && trim($whereArmado) !== '') {
            $payload['whereArmado'] = $whereArmado;
        }

        $rows = json_decode((new ApiAnita)->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }
}
