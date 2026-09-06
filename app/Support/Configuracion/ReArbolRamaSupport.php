<?php

namespace App\Support\Configuracion;

use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_CuentaExcepcion;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve si un CC opera en dual-rama y cuál rama aplica a una requisición.
 */
class ReArbolRamaSupport
{
    /**
     * Dual-rama activo si hay niveles con rama A/B para el CC, o allowlist para ese CC.
     */
    public static function centrocostoTieneDualRama(Arbolaprobacion $arbol, int $centrocostoId): bool
    {
        if ($centrocostoId <= 0) {
            return false;
        }

        foreach ($arbol->arbolaprobacion_niveles as $nivel) {
            if ((int) $nivel->centrocosto_id !== $centrocostoId) {
                continue;
            }
            if (ReArbolRamaCatalog::esRamaValida($nivel->rama ?? null)) {
                return true;
            }
        }

        try {
            if (! Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
                return false;
            }

            return Arbolaprobacion_CuentaExcepcion::query()
                ->where('arbolaprobacion_id', (int) $arbol->id)
                ->where('centrocosto_id', $centrocostoId)
                ->where('activo', 'S')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<int> IDs de cuentacontable activos en allowlist del CC/empresa
     */
    public static function allowlistCuentacontableIds(Arbolaprobacion $arbol, int $centrocostoId, int $empresaId): array
    {
        try {
            if (! Schema::hasTable('arbolaprobacion_cuenta_excepcion') || $centrocostoId <= 0 || $empresaId <= 0) {
                return [];
            }

            return Arbolaprobacion_CuentaExcepcion::query()
                ->where('arbolaprobacion_id', (int) $arbol->id)
                ->where('centrocosto_id', $centrocostoId)
                ->where('empresa_id', $empresaId)
                ->where('activo', 'S')
                ->pluck('cuentacontable_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cuentas contables de las líneas válidas de la requisición (vía partidagasto).
     * Líneas sin partida/cuenta quedan como 0 (fuerzan rama B).
     *
     * @return list<int>
     */
    public static function cuentacontableIdsDesdeRequisicion(Requisicion $requisicion): array
    {
        $requisicion->loadMissing(['requisicion_articulos.partidagastos']);

        $ids = [];
        foreach ($requisicion->requisicion_articulos as $linea) {
            if (empty($linea->articulo_id) || (float) ($linea->cantidad ?? 0) <= 0) {
                continue;
            }
            $cuentaId = (int) (optional($linea->partidagastos)->cuentacontable_id ?? 0);
            $ids[] = $cuentaId;
        }

        return $ids;
    }

    /**
     * null = circuito único (sin dual). A/B = rama elegida.
     */
    public static function resolverRama(Arbolaprobacion $arbol, Requisicion $requisicion, int $centrocostoArbol): ?string
    {
        if (! static::centrocostoTieneDualRama($arbol, $centrocostoArbol)) {
            return null;
        }

        $empresaId = (int) ($requisicion->empresa_id ?? 0);
        $allowlist = static::allowlistCuentacontableIds($arbol, $centrocostoArbol, $empresaId);
        $cuentasLinea = static::cuentacontableIdsDesdeRequisicion($requisicion);

        if ($cuentasLinea === []) {
            return ReArbolRamaCatalog::RAMA_B;
        }

        if ($allowlist === []) {
            return ReArbolRamaCatalog::RAMA_B;
        }

        $allowSet = array_fill_keys($allowlist, true);
        foreach ($cuentasLinea as $cuentaId) {
            if ($cuentaId <= 0 || ! isset($allowSet[$cuentaId])) {
                return ReArbolRamaCatalog::RAMA_B;
            }
        }

        return ReArbolRamaCatalog::RAMA_A;
    }
}
