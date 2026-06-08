<?php

namespace App\Support\Ventas\Waitry;

/**
 * Punto de acceso Waitry en órdenes getOrdersPOS / getordersdetails ({@code table} + {@code layout}).
 */
final class WaitryTableAccesoSupport
{
    /**
     * @param  array<string, mixed>  $orden
     * @return array{
     *     table_id:?int,
     *     table_name:?string,
     *     layout_id:?int,
     *     layout_name:?string
     * }
     */
    public static function extraerDesdeOrden(array $orden): array
    {
        $table = $orden['table'] ?? null;
        if (! is_array($table)) {
            $tableId = (int) ($orden['tableId'] ?? 0);

            return [
                'table_id' => $tableId > 0 ? $tableId : null,
                'table_name' => null,
                'layout_id' => null,
                'layout_name' => null,
            ];
        }

        $tableId = (int) ($table['tableId'] ?? $table['id'] ?? 0);
        $tableName = trim((string) ($table['name'] ?? ''));
        $layout = is_array($table['layout'] ?? null) ? $table['layout'] : [];
        $layoutId = (int) ($layout['id'] ?? 0);
        $layoutName = trim((string) ($layout['name'] ?? ''));

        return [
            'table_id' => $tableId > 0 ? $tableId : null,
            'table_name' => $tableName !== '' ? $tableName : null,
            'layout_id' => $layoutId > 0 ? $layoutId : null,
            'layout_name' => $layoutName !== '' ? $layoutName : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerTableId(array $orden): ?int
    {
        return self::extraerDesdeOrden($orden)['table_id'];
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerLayoutId(array $orden): ?int
    {
        return self::extraerDesdeOrden($orden)['layout_id'];
    }
}
