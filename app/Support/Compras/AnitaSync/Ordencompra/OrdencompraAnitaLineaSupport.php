<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;

/**
 * Asigna penvp_orden y penvp_nro_interno en líneas ERP antes de escribir Anita.
 *
 * penvp_orden debe ser único en la OC: ocvley indexa por (ocvl_nro_orden, ocvl_linea)
 * y un INSERT duplicado aborta la confirmación de la COM.
 */
final class OrdencompraAnitaLineaSupport
{
    public static function asignarClavesLineas(Ordencompra $oc): void
    {
        $oc->loadMissing('ordencompra_articulos');

        $lineas = $oc->ordencompra_articulos
            ->sortBy([['penvp_orden', 'asc'], ['id', 'asc']])
            ->values();

        if ($lineas->isEmpty()) {
            throw new \RuntimeException('La orden de compra no tiene ítems para grabar en Anita.');
        }

        $siguienteInterno = OrdencompraAnitaNumeracionSupport::reservarSiguienteNroInterno(
            (int) ($oc->empresa_id ?? 0)
        );
        $ordenesUsados = [];
        $internosUsados = [];

        foreach ($lineas as $linea) {
            $cambios = [];

            $ordenActual = $linea->penvp_orden === null ? null : (int) $linea->penvp_orden;
            $ordenUnico = self::siguienteOrdenUnico($ordenActual, $ordenesUsados);
            if ($ordenActual === null || $ordenActual !== $ordenUnico) {
                $cambios['penvp_orden'] = $ordenUnico;
            }

            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            // Vacío o repetido en esta OC: la primera línea conserva el interno, las demás reservan uno nuevo.
            if ($nroInterno <= 0 || isset($internosUsados[$nroInterno])) {
                $cambios['penvp_nro_interno'] = $siguienteInterno;
                $siguienteInterno++;
            } else {
                $internosUsados[$nroInterno] = true;
            }

            if ($cambios !== []) {
                Ordencompra_Articulo::query()
                    ->whereKey($linea->id)
                    ->where('ordencompra_id', $oc->id)
                    ->update($cambios);
                foreach ($cambios as $k => $v) {
                    $linea->{$k} = $v;
                }
            }
        }

        $oc->unsetRelation('ordencompra_articulos');
        $oc->load('ordencompra_articulos');
    }

    /**
     * Anula penvp_nro_interno de líneas cuyo interno no está en el set válido de esta OC
     * (p. ej. copiados por error de otras OC) para que asignarClavesLineas reserve nuevos.
     *
     * @param  array<int, true>  $internosValidosEnEstaOc  penvp_nro_interno => true
     * @return array<int, array{antes: int, despues: int}> ordencompra_articulo.id => cambio
     */
    public static function liberarNroInternosInvalidos(Ordencompra $oc, array $internosValidosEnEstaOc): array
    {
        $oc->loadMissing('ordencompra_articulos');
        $cambios = [];

        foreach ($oc->ordencompra_articulos as $linea) {
            $nro = (int) ($linea->penvp_nro_interno ?? 0);
            if ($nro <= 0) {
                continue;
            }
            if (isset($internosValidosEnEstaOc[$nro])) {
                continue;
            }

            Ordencompra_Articulo::query()
                ->whereKey($linea->id)
                ->where('ordencompra_id', $oc->id)
                ->update(['penvp_nro_interno' => null]);
            $linea->penvp_nro_interno = null;
            $cambios[(int) $linea->id] = ['antes' => $nro, 'despues' => 0];
        }

        if ($cambios !== []) {
            $oc->unsetRelation('ordencompra_articulos');
            $oc->load('ordencompra_articulos');
        }

        return $cambios;
    }

    /**
     * Conserva el orden actual si no está usado; si es null o está repetido, toma el menor libre ≥ 0.
     *
     * @param  array<int, true>  $usados
     */
    public static function siguienteOrdenUnico(?int $ordenActual, array &$usados): int
    {
        if ($ordenActual !== null && ! isset($usados[$ordenActual])) {
            $usados[$ordenActual] = true;

            return $ordenActual;
        }

        $candidato = 0;
        while (isset($usados[$candidato])) {
            $candidato++;
        }
        $usados[$candidato] = true;

        return $candidato;
    }
}
