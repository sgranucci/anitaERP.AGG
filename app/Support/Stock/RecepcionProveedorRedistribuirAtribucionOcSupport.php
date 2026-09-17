<?php

namespace App\Support\Stock;

/**
 * Redistribuye atribución COM → línea OC cuando varias recepciones
 * quedaron apiladas en un solo ordencompra_articulo_id.
 *
 * No usa penvp_nro_interno como clave: si hay internos duplicados por error
 * de grabación, matching por interno es ambiguo (first-wins) y es exactamente
 * el patrón que concentra todo en una sola línea.
 */
class RecepcionProveedorRedistribuirAtribucionOcSupport
{
    /**
     * @param  list<array{id:int, articulo_id:int, penvp_orden:int, penvp_nro_interno:int, cantidad:float}>  $lineasOc
     * @param  list<array{
     *   recepcion_id:int,
     *   numerorecepcion:string|int,
     *   fecha:?string,
     *   lineas: list<array{
     *     rpa_id:int,
     *     ordencompra_articulo_id:?int,
     *     articulo_id:int,
     *     cantidad:float,
     *     penvp_orden:?int,
     *     penvp_nro_interno:?int
     *   }>
     * }>  $coms
     * @return array{
     *   cambios: list<array{
     *     rpa_id:int,
     *     recepcion_id:int,
     *     numerorecepcion:string|int,
     *     articulo_id:int,
     *     desde_oc_art:int,
     *     hacia_oc_art:int,
     *     penvp_orden:int,
     *     penvp_nro_interno:int
     *   }>,
     *   coms_revisados:int,
     *   coms_redistribuidos:int,
     *   lineas_sin_atribuir:int,
     *   internos_duplicados: list<array{penvp_nro_interno:int, cantidad:int, ordencompra_articulo_ids:list<int>}>
     * }
     */
    public static function planificar(array $lineasOc, array $coms): array
    {
        $internosDuplicados = self::detectarInternosDuplicados($lineasOc);

        $porArticulo = [];
        foreach ($lineasOc as $linea) {
            $artId = (int) $linea['articulo_id'];
            if ($artId <= 0) {
                continue;
            }
            $porArticulo[$artId][] = $linea;
        }
        foreach ($porArticulo as &$grupo) {
            usort($grupo, static fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
        }
        unset($grupo);

        $cambios = [];
        $comsRevisados = 0;
        $comsRedistribuidos = 0;
        $lineasSinAtribuir = 0;

        foreach ($coms as $com) {
            $comsRevisados++;
            $rpasPorArticulo = [];
            foreach ($com['lineas'] as $rpa) {
                $ocArtId = (int) ($rpa['ordencompra_articulo_id'] ?? 0);
                if ($ocArtId <= 0) {
                    $lineasSinAtribuir++;
                    continue;
                }
                $artId = (int) ($rpa['articulo_id'] ?? 0);
                if ($artId <= 0) {
                    continue;
                }
                $rpasPorArticulo[$artId][] = $rpa;
            }

            $huboCambioEnCom = false;
            foreach ($rpasPorArticulo as $artId => $rpas) {
                $targets = $porArticulo[$artId] ?? [];
                $plan = self::planificarGrupoArticulo(
                    $rpas,
                    $targets,
                    (int) $com['recepcion_id'],
                    $com['numerorecepcion']
                );
                if ($plan === []) {
                    continue;
                }
                $huboCambioEnCom = true;
                foreach ($plan as $cambio) {
                    $cambios[] = $cambio;
                }
            }
            if ($huboCambioEnCom) {
                $comsRedistribuidos++;
            }
        }

        return [
            'cambios' => $cambios,
            'coms_revisados' => $comsRevisados,
            'coms_redistribuidos' => $comsRedistribuidos,
            'lineas_sin_atribuir' => $lineasSinAtribuir,
            'internos_duplicados' => $internosDuplicados,
        ];
    }

    /**
     * Solo redistribuye cuando N≥2 renglones COM del mismo artículo quedaron
     * apuntando a un único ordencompra_articulo_id y la OC tiene ≥2 líneas
     * de ese artículo. Asigna 1:1 (round-robin) por id de línea OC.
     *
     * @param  list<array{rpa_id:int, ordencompra_articulo_id:?int, articulo_id:int, cantidad:float, penvp_orden:?int, penvp_nro_interno:?int}>  $rpas
     * @param  list<array{id:int, articulo_id:int, penvp_orden:int, penvp_nro_interno:int, cantidad:float}>  $targets
     * @return list<array{rpa_id:int, recepcion_id:int, numerorecepcion:string|int, articulo_id:int, desde_oc_art:int, hacia_oc_art:int, penvp_orden:int, penvp_nro_interno:int}>
     */
    public static function planificarGrupoArticulo(
        array $rpas,
        array $targets,
        int $recepcionId,
        string|int $numerorecepcion,
    ): array {
        if (count($rpas) < 2 || count($targets) < 2) {
            return [];
        }

        $idsUnicos = [];
        foreach ($rpas as $rpa) {
            $idsUnicos[(int) $rpa['ordencompra_articulo_id']] = true;
        }
        if (count($idsUnicos) !== 1) {
            return [];
        }

        $desde = (int) array_key_first($idsUnicos);
        $cambios = [];
        $n = count($targets);
        foreach (array_values($rpas) as $i => $rpa) {
            $hacia = $targets[$i % $n];
            $haciaId = (int) $hacia['id'];
            if ($haciaId === $desde) {
                continue;
            }
            $cambios[] = [
                'rpa_id' => (int) $rpa['rpa_id'],
                'recepcion_id' => $recepcionId,
                'numerorecepcion' => $numerorecepcion,
                'articulo_id' => (int) $rpa['articulo_id'],
                'desde_oc_art' => $desde,
                'hacia_oc_art' => $haciaId,
                'penvp_orden' => (int) ($hacia['penvp_orden'] ?? 0),
                'penvp_nro_interno' => (int) ($hacia['penvp_nro_interno'] ?? 0),
            ];
        }

        return $cambios;
    }

    /**
     * @param  list<array{id:int, penvp_nro_interno?:int|null}>  $lineasOc
     * @return list<array{penvp_nro_interno:int, cantidad:int, ordencompra_articulo_ids:list<int>}>
     */
    public static function detectarInternosDuplicados(array $lineasOc): array
    {
        $porInterno = [];
        foreach ($lineasOc as $linea) {
            $interno = (int) ($linea['penvp_nro_interno'] ?? 0);
            if ($interno <= 0) {
                continue;
            }
            $porInterno[$interno][] = (int) $linea['id'];
        }

        $dups = [];
        foreach ($porInterno as $interno => $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $dups[] = [
                'penvp_nro_interno' => (int) $interno,
                'cantidad' => count($ids),
                'ordencompra_articulo_ids' => array_values($ids),
            ];
        }

        return $dups;
    }

    /**
     * Matching seguro por interno: solo si es único en la OC.
     * Con duplicados devuelve null (hay que usar ordencompra_articulo_id explícito).
     *
     * @param  list<array{id:int, penvp_nro_interno?:int|null}>  $lineasOc
     */
    public static function resolverLineaPorInternoUnico(array $lineasOc, int $penvpNroInterno): ?int
    {
        if ($penvpNroInterno <= 0) {
            return null;
        }

        $matches = [];
        foreach ($lineasOc as $linea) {
            if ((int) ($linea['penvp_nro_interno'] ?? 0) === $penvpNroInterno) {
                $matches[] = (int) $linea['id'];
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return $matches[0];
    }
}
