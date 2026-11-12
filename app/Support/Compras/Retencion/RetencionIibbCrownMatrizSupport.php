<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cm05;
use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\ProveedorRetencionIibbAnitaSupport;
use App\Support\Configuracion\CondicionivaLetraComprasSupport;

/**
 * Matriz Crown: condición IIBB × inscripción en RN.
 *
 * Destino mercadería ≠ RN (flete Crown) → base 0 en el armado de bases; acá no entra.
 * Destino RN → tasas de retencionIIBB (RN) + porcentajeretencion_fuera.
 *
 * Inscripto en RN = domicilio RN o provincia RN en grilla CM05 del proveedor.
 * Convenio/Local inscripto → % de la fila; sin inscripción RN → % fuera.
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
     *   inscrito_cm05_rn: bool,
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
        $inscritoCm05Rn = self::tieneCm05EnProvincia($proveedor, self::PROVINCIA_LOCAL_ID);
        $enLocal = ($provinciaId !== null && $provinciaId === self::PROVINCIA_LOCAL_ID)
            || $inscritoCm05Rn;
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
            'inscrito_cm05_rn' => $inscritoCm05Rn,
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

    public static function tieneCm05EnProvincia(Proveedor $proveedor, int $provinciaId): bool
    {
        if ($provinciaId <= 0) {
            return false;
        }

        if ($proveedor->relationLoaded('proveedor_cm05s')) {
            return $proveedor->proveedor_cm05s->contains(
                static fn ($fila) => (int) ($fila->provincia_id ?? 0) === $provinciaId
            );
        }

        if (! $proveedor->exists || ! (int) ($proveedor->id ?? 0)) {
            return false;
        }

        return Proveedor_Cm05::query()
            ->where('proveedor_id', (int) $proveedor->id)
            ->where('provincia_id', $provinciaId)
            ->exists();
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
