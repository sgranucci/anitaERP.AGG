<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

class ArticuloEnviaAlarmaSupport
{
    public static function enviaAlarmaActivo(?string $valor): bool
    {
        $valor = trim((string) $valor);

        return $valor === 'Envia Alarma' || strtoupper($valor) === 'S';
    }

    /**
     * @param  list<int|string>  $articuloIds
     * @return list<int>
     */
    public static function idsConAlarma(array $articuloIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        if ($ids === []) {
            return [];
        }

        return Articulo::query()
            ->whereIn('id', $ids)
            ->get(['id', 'enviaalarma'])
            ->filter(fn (Articulo $a) => self::enviaAlarmaActivo($a->enviaalarma))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
