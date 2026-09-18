<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\CondicionIIBB;

/**
 * Quién entra a retención IIBB en OP: manda la condición IIBB del maestro de proveedores.
 *
 * formacalculo N = no retiene (catálogo CondicionIIBB). El IVA (monotributo u otro)
 * no corta ni fuerza la retención.
 */
final class RetencionIibbElegibilidadSupport
{
    public static function correspondePorCondicionIibb(
        ?Proveedor $proveedor,
        ?int $condicionIibbIdOverride = null,
    ): bool {
        $condicion = self::condicionIibb($proveedor, $condicionIibbIdOverride);
        if ($condicion === null) {
            return false;
        }

        return strtoupper((string) ($condicion->formacalculo ?? '')) !== 'N'
            && strtoupper((string) ($condicion->estado ?? 'A')) === 'A';
    }

    private static function condicionIibb(?Proveedor $proveedor, ?int $overrideId): ?CondicionIIBB
    {
        $id = $overrideId;
        if (($id === null || $id <= 0) && $proveedor !== null && (int) ($proveedor->condicionIIBB_id ?? 0) > 0) {
            $id = (int) $proveedor->condicionIIBB_id;
        }

        if ($proveedor !== null && $proveedor->relationLoaded('condicionIIBBs')) {
            $cargada = $proveedor->getRelation('condicionIIBBs');
            if ($cargada instanceof CondicionIIBB && ($id === null || $id <= 0 || (int) $cargada->id === $id)) {
                return $cargada;
            }
        }

        if ($id === null || $id <= 0) {
            return null;
        }

        if ($proveedor !== null && ! $proveedor->exists) {
            return null;
        }

        return CondicionIIBB::query()->find($id);
    }
}
