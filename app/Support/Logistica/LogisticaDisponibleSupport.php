<?php

namespace App\Support\Logistica;

use App\Models\Stock\Depmae;
use Illuminate\Support\Facades\DB;

final class LogisticaDisponibleSupport
{
    /**
     * Suma el saldo de los depósitos que el usuario puede ver. No reserva.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, float>
     */
    public static function totales(array $articuloIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = 0.0;
        }
        if ($ids === []) {
            return $out;
        }

        $depositos = Depmae::query()->paraUsuarioAutorizado()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($depositos === []) {
            return $out;
        }

        $filas = DB::table('articulo_saldo_deposito')
            ->selectRaw('articulo_id, SUM(cantidad) as saldo')
            ->whereIn('articulo_id', $ids)
            ->whereIn('deposito_id', $depositos)
            ->groupBy('articulo_id')
            ->get();
        foreach ($filas as $fila) {
            $out[(int) $fila->articulo_id] = (float) $fila->saldo;
        }

        return $out;
    }
}
