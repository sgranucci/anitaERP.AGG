<?php

namespace App\Support\Ventas\Ferli;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Una OT puede juntar varios pedidos de la misma pieza.
 * No puede mezclar artículo o combinación: el caso 31178 imprimió el ZIMBA
 * del pedido 4816 y después las tallas quedaron en el NELLIE de otro pedido.
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

        $existentes = self::pedidoCombinacionIds($otId);
        if ($existentes === []) {
            return;
        }

        $permitidos = self::ids($pedidoCombinacionIds);
        $nuevos = array_values(array_diff($permitidos, $existentes));
        if ($nuevos === []) {
            return;
        }

        if (self::lineasMezclanOtraPieza($existentes, $nuevos)) {
            throw new RuntimeException(
                'La OT '.$otId.' ya tiene otro artículo. No se puede cargar un renglón distinto sobre la misma OT.'
            );
        }
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
        if ($existentes === [] || in_array($pcId, $existentes, true)) {
            return false;
        }

        return self::lineasMezclanOtraPieza($existentes, [$pcId]);
    }

    /**
     * True cuando la OT ya no tiene una sola pieza, o el renglón nuevo no es esa pieza.
     * Varios pedidos del mismo artículo y combinación no mezclan.
     *
     * @param  list<int>  $existentes
     * @param  list<int>  $nuevos
     */
    private static function lineasMezclanOtraPieza(array $existentes, array $nuevos): bool
    {
        $firmasExistentes = self::firmasDeLineas($existentes);
        if (count($firmasExistentes) !== 1) {
            return true;
        }

        $firmasNuevas = self::firmasDeLineas($nuevos);
        if ($firmasNuevas === []) {
            return true;
        }

        $esperada = $firmasExistentes[0];
        foreach ($firmasNuevas as $firma) {
            if ($firma !== $esperada) {
                return true;
            }
        }

        return false;
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
     * Artículo y combinación. Dos pedidos de la misma pieza comparten firma.
     *
     * @param  list<int>  $pedidoCombinacionIds
     * @return list<string>
     */
    private static function firmasDeLineas(array $pedidoCombinacionIds): array
    {
        $ids = self::ids($pedidoCombinacionIds);
        if ($ids === []) {
            return [];
        }

        return DB::table('pedido_combinacion')
            ->whereIn('id', $ids)
            ->get(['articulo_id', 'combinacion_id'])
            ->map(static fn ($linea) => (int) $linea->articulo_id.'|'.(int) $linea->combinacion_id)
            ->unique()
            ->values()
            ->all();
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
