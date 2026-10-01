<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Cuentacontable_Centrocosto;

/**
 * Centros de costo cargados en la cuenta (matriz cuentacontable_centrocosto).
 *
 * Un solo asignado se toma solo. Varios: vale el elegido si está en la lista.
 */
final class CuentaCentrocostoAsignadosSupport
{
    /** @var array<int, array{maneja: bool, ids: list<int>}> */
    private static array $cache = [];

    /**
     * @param  list<int>  $idsAsignados
     */
    public static function resolverDesdeLista(bool $maneja, array $idsAsignados, int $elegido, int $fallback): int
    {
        $ids = [];
        foreach ($idsAsignados as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        if (! $maneja) {
            return $elegido > 0 ? $elegido : max(0, $fallback);
        }

        if ($ids === []) {
            return $elegido > 0 ? $elegido : max(0, $fallback);
        }

        if (count($ids) === 1) {
            return $ids[0];
        }

        if ($elegido > 0 && in_array($elegido, $ids, true)) {
            return $elegido;
        }

        if ($fallback > 0 && in_array($fallback, $ids, true)) {
            return $fallback;
        }

        return 0;
    }

    public static function resolver(int $cuentaId, int $elegido, int $fallback): int
    {
        if ($cuentaId <= 0) {
            return $elegido > 0 ? $elegido : max(0, $fallback);
        }

        $datos = self::deCuenta($cuentaId);

        return self::resolverDesdeLista($datos['maneja'], $datos['ids'], $elegido, $fallback);
    }

    /**
     * @return array{maneja: bool, ids: list<int>}
     */
    private static function deCuenta(int $cuentaId): array
    {
        if (isset(self::$cache[$cuentaId])) {
            return self::$cache[$cuentaId];
        }

        $flag = Cuentacontable::query()->whereKey($cuentaId)->value('manejaccosto');
        $maneja = AsientoCentrocostoObligatorioSupport::maneja($flag);
        $ids = [];
        if ($maneja) {
            $ids = Cuentacontable_Centrocosto::query()
                ->where('cuentacontable_id', $cuentaId)
                ->orderBy('centrocosto_id')
                ->pluck('centrocosto_id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->values()
                ->all();
        }

        self::$cache[$cuentaId] = [
            'maneja' => $maneja,
            'ids' => $ids,
        ];

        return self::$cache[$cuentaId];
    }
}
