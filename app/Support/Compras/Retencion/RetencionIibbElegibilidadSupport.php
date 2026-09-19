<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\CondicionIIBB;
use App\Models\Configuracion\Condicioniva;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Quién entra a retención IIBB en OP: manda la condición IIBB del maestro de proveedores.
 *
 * formacalculo N = no retiene (catálogo CondicionIIBB).
 *
 * Provisorio AGG: los monotributistas no entran (corte por IVA) hasta que Impuestos
 * confirme el criterio. Ferli y el resto no cortan por IVA. Override:
 * COMPRAS_IIBB_OMITE_MONOTRIBUTO=true|false.
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

        if (self::omiteMonotributistas() && self::proveedorEsMonotributista($proveedor)) {
            return false;
        }

        return strtoupper((string) ($condicion->formacalculo ?? '')) !== 'N'
            && strtoupper((string) ($condicion->estado ?? 'A')) === 'A';
    }

    /**
     * Vacío en config → sí en AGG, no en el resto.
     */
    public static function omiteMonotributistas(): bool
    {
        $raw = config('compras.iibb_retencion_omite_monotributo');
        if ($raw === null || $raw === '') {
            return EntornoEmpresaSupport::esAgg();
        }
        if (is_bool($raw)) {
            return $raw;
        }

        $norm = strtolower(trim((string) $raw));
        if (in_array($norm, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($norm, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        return EntornoEmpresaSupport::esAgg();
    }

    private static function proveedorEsMonotributista(?Proveedor $proveedor): bool
    {
        if ($proveedor === null) {
            return false;
        }

        if ($proveedor->relationLoaded('condicionivas')) {
            $iva = $proveedor->getRelation('condicionivas');

            return $iva instanceof Condicioniva
                && CondicionivaLetraComprasSupport::esMonotributo($iva);
        }

        $id = (int) ($proveedor->condicioniva_id ?? 0);
        if ($id <= 0) {
            return false;
        }

        if ($id === CondicionivaLetraComprasSupport::condicionivaMonotributoId()) {
            return true;
        }

        if (! $proveedor->exists) {
            return false;
        }

        $iva = Condicioniva::query()->find($id);

        return CondicionivaLetraComprasSupport::esMonotributo($iva);
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
