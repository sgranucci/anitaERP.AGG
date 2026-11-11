<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\ProveedorRetencionIibbAnitaSupport;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;

/**
 * Matriz Crown (sin CM05): condición IIBB × provincia del proveedor.
 *
 * Destino mercadería ≠ RN (flete Crown) → base 0 en el armado de bases; acá no entra.
 * Destino RN → tasas de retencionIIBB (RN) + porcentajeretencion_fuera.
 *
 * Convenio/Local en RN → % de la fila; fuera de RN → % fuera.
 * Monotributo en RN → 0%; mono fuera → % fuera.
 */
final class RetencionIibbCrownMatrizSupport
{
    public const PROVINCIA_LOCAL_ID = 3;

    public const JURISDICCION_AFIP_RN = '916';

    /**
     * @return array{
     *   aplica: bool,
     *   tasa: float,
     *   origen: string,
     *   condicion_iibb_id: ?int,
     *   proveedor_provincia_id: ?int,
     *   en_jurisdiccion_local: bool,
     *   es_monotributo: bool,
     *   minimo_imponible: float,
     *   minimo_retencion: float,
     *   motivo: ?string,
     *   regla: string
     * }
     */
    public static function resolver(
        Proveedor $proveedor,
        RetencionIIBB $catalogo,
        ?int $condicionIibbIdOverride = null,
    ): array {
        $condicionId = $condicionIibbIdOverride
            ?? ((int) ($proveedor->condicionIIBB_id ?? 0) ?: null);

        $provinciaId = (int) ($proveedor->provincia_id ?? 0) ?: null;
        $enLocal = $provinciaId !== null && $provinciaId === self::PROVINCIA_LOCAL_ID;
        $esMono = self::esMonotributo($proveedor);
        $tasaFuera = self::tasaFuera($catalogo);
        $fila = self::filaCondicion($catalogo, $condicionId);

        $base = [
            'aplica' => false,
            'tasa' => 0.0,
            'origen' => 'crown_matriz',
            'condicion_iibb_id' => $condicionId,
            'proveedor_provincia_id' => $provinciaId,
            'en_jurisdiccion_local' => $enLocal,
            'es_monotributo' => $esMono,
            'minimo_imponible' => (float) ($fila->minimoimponible ?? 0),
            'minimo_retencion' => (float) ($fila->minimoretencion ?? 0),
            'motivo' => null,
            'regla' => '',
        ];

        if ($condicionId === null || $condicionId === ProveedorRetencionIibbAnitaSupport::CONDICION_NO_RETIENE) {
            return array_merge($base, [
                'motivo' => RetencionIibbResultado::MOTIVO_NO_RETIENE,
                'regla' => 'no_alcanzado',
            ]);
        }

        // Compat: id 3 (No inscripto) se trata como convenio fuera de jurisdicción.
        if ($condicionId === ProveedorRetencionIibbAnitaSupport::CONDICION_NO_INSCRIPTO) {
            $condicionId = ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO;
            $fila = self::filaCondicion($catalogo, $condicionId) ?? $fila;
            $base['condicion_iibb_id'] = $condicionId;
            $base['minimo_imponible'] = (float) ($fila->minimoimponible ?? $base['minimo_imponible']);
            $base['minimo_retencion'] = (float) ($fila->minimoretencion ?? $base['minimo_retencion']);
        }

        if ($esMono) {
            if ($enLocal) {
                return array_merge($base, [
                    'motivo' => RetencionIibbResultado::MOTIVO_NO_RETIENE,
                    'regla' => 'monotributo_rn_0',
                ]);
            }

            if ($tasaFuera <= 0) {
                return array_merge($base, [
                    'motivo' => RetencionIibbResultado::MOTIVO_SIN_TASA,
                    'regla' => 'monotributo_fuera_sin_tasa',
                ]);
            }

            return array_merge($base, [
                'aplica' => true,
                'tasa' => $tasaFuera,
                'regla' => 'monotributo_fuera',
            ]);
        }

        if (! in_array($condicionId, [
            ProveedorRetencionIibbAnitaSupport::CONDICION_CONVENIO,
            ProveedorRetencionIibbAnitaSupport::CONDICION_LOCAL,
        ], true)) {
            return array_merge($base, [
                'motivo' => RetencionIibbResultado::MOTIVO_NO_RETIENE,
                'regla' => 'condicion_no_mapeada',
            ]);
        }

        if ($enLocal) {
            $tasa = (float) ($fila->porcentajeretencion ?? 0);
            if ($tasa <= 0) {
                return array_merge($base, [
                    'motivo' => RetencionIibbResultado::MOTIVO_SIN_TASA,
                    'regla' => $condicionId === ProveedorRetencionIibbAnitaSupport::CONDICION_LOCAL
                        ? 'local_rn_sin_tasa'
                        : 'convenio_rn_sin_tasa',
                ]);
            }

            return array_merge($base, [
                'aplica' => true,
                'tasa' => $tasa,
                'regla' => $condicionId === ProveedorRetencionIibbAnitaSupport::CONDICION_LOCAL
                    ? 'local_rn'
                    : 'convenio_rn',
            ]);
        }

        if ($tasaFuera <= 0) {
            return array_merge($base, [
                'motivo' => RetencionIibbResultado::MOTIVO_SIN_TASA,
                'regla' => 'fuera_rn_sin_tasa',
            ]);
        }

        return array_merge($base, [
            'aplica' => true,
            'tasa' => $tasaFuera,
            'regla' => $condicionId === ProveedorRetencionIibbAnitaSupport::CONDICION_LOCAL
                ? 'local_fuera'
                : 'convenio_fuera',
        ]);
    }

    public static function tasaFuera(RetencionIIBB $catalogo): float
    {
        return (float) ($catalogo->porcentajeretencion_fuera ?? 0);
    }

    private static function filaCondicion(RetencionIIBB $catalogo, ?int $condicionId): ?RetencionIIBB_Condicion
    {
        if ($condicionId === null) {
            return null;
        }

        $condiciones = $catalogo->relationLoaded('retencionIIBB_condiciones')
            ? $catalogo->retencionIIBB_condiciones
            : $catalogo->retencionIIBB_condiciones()->get();

        foreach ($condiciones as $fila) {
            if ((int) $fila->condicionIIBB_id === $condicionId) {
                return $fila;
            }
        }

        return null;
    }

    private static function esMonotributo(Proveedor $proveedor): bool
    {
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
}
