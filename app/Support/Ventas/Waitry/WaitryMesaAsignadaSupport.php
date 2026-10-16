<?php

namespace App\Support\Ventas\Waitry;

/**
 * Mesa Waitry de la comanda: la asignada a la PC, o la mesa por empresa si la PC no tiene una.
 */
final class WaitryMesaAsignadaSupport
{
    /**
     * @return array<string, mixed>|null
     */
    public static function payload(?int $tableIdAsignado, int $empresaId): ?array
    {
        if ($tableIdAsignado !== null && $tableIdAsignado > 0) {
            return ['tableId' => $tableIdAsignado];
        }

        $map = config('waitry.table_por_empresa', []);
        if (is_array($map)) {
            $table = $map[$empresaId] ?? null;
            if (is_array($table) && $table !== []) {
                return $table;
            }
        }

        $legacy = config('waitry.table', []);
        if (is_array($legacy) && $legacy !== []) {
            return $legacy;
        }

        return null;
    }
}
