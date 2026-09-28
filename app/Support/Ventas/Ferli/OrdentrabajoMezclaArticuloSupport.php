<?php

namespace App\Support\Ventas\Ferli;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Una OT no puede pasar a otro artículo.
 * El caso 31178: se imprimió el ZIMBA del pedido 4816 y después las tallas
 * quedaron en el NELLIE de otro pedido.
 */
final class OrdentrabajoMezclaArticuloSupport
{
    /**
     * @param  list<int>  $pedidoCombinacionIds
     */
    public static function assertActualizacionNoIncorporaOtroArticulo(int $otId, array $pedidoCombinacionIds): void
    {
        if ($otId <= 0) {
            return;
        }

        $permitidos = self::ids($pedidoCombinacionIds);
        $existentes = self::pedidoCombinacionIds($otId);
        if ($existentes === []) {
            return;
        }

        $nuevos = array_values(array_diff($permitidos, $existentes));
        if ($nuevos === []) {
            return;
        }

        throw new RuntimeException(
            'La OT '.$otId.' ya tiene otro artículo. No se puede cargar un renglón distinto sobre la misma OT.'
        );
    }

    public static function talleMezclaOtroArticulo(int $otId, int $pedidoCombinacionTalleId): bool
    {
        if ($otId <= 0 || $pedidoCombinacionTalleId <= 0) {
            return false;
        }

        $pcId = (int) DB::table('pedido_combinacion_talle')
            ->where('id', $pedidoCombinacionTalleId)
            ->value('pedido_combinacion_id');
        if ($pcId <= 0) {
            return false;
        }

        $existentes = self::pedidoCombinacionIds($otId);
        if ($existentes === []) {
            return false;
        }

        return ! in_array($pcId, $existentes, true);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id) => $id > 0
        )));
    }

    /**
     * @return list<int>
     */
    private static function pedidoCombinacionIds(int $otId): array
    {
        return DB::table('ordentrabajo_combinacion_talle as oct')
            ->join('pedido_combinacion_talle as pct', 'pct.id', '=', 'oct.pedido_combinacion_talle_id')
            ->where('oct.ordentrabajo_id', $otId)
            ->distinct()
            ->pluck('pct.pedido_combinacion_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
