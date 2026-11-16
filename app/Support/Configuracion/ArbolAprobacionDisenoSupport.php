<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Support\Collection;

/**
 * Grafo visual del árbol: carriles (CC + rama) y nodos (niveles).
 * La aprobación real sigue usando arbolaprobacion_nivel; esto solo arma la vista.
 */
final class ArbolAprobacionDisenoSupport
{
    public const NODE_W = 220;

    public const NODE_H = 112;

    public const GAP_X = 48;

    public const GAP_Y = 36;

    public const LANE_PAD = 28;

    /**
     * @return array{
     *   arbol: array<string, mixed>,
     *   lanes: list<array<string, mixed>>,
     *   nodes: list<array<string, mixed>>,
     *   edges: list<array{from: string, to: string}>,
     *   layout: array<string, mixed>
     * }
     */
    public static function construir(Arbolaprobacion $arbol): array
    {
        /** @var Collection<int, Arbolaprobacion_Nivel> $niveles */
        $niveles = $arbol->relationLoaded('arbolaprobacion_niveles')
            ? $arbol->arbolaprobacion_niveles
            : $arbol->arbolaprobacion_niveles()->get();

        $layout = is_array($arbol->diseno_layout) ? $arbol->diseno_layout : [];
        $posGuardadas = is_array($layout['nodes'] ?? null) ? $layout['nodes'] : [];

        $lanesMap = [];
        foreach ($niveles as $n) {
            $ccId = $n->centrocosto_id ? (int) $n->centrocosto_id : 0;
            $rama = strtoupper(trim((string) ($n->rama ?? '')));
            if (! in_array($rama, ['A', 'B'], true)) {
                $rama = '';
            }
            $laneKey = 'cc:'.$ccId.'|rama:'.($rama !== '' ? $rama : '_');
            if (! isset($lanesMap[$laneKey])) {
                $ccNombre = $n->centrocosto_ids
                    ? trim(($n->centrocosto_ids->codigo ?? '').' — '.($n->centrocosto_ids->nombre ?? ''))
                    : 'Todos los CC';
                $lanesMap[$laneKey] = [
                    'key' => $laneKey,
                    'centrocosto_id' => $ccId ?: null,
                    'rama' => $rama !== '' ? $rama : null,
                    'label' => $rama !== '' ? ($ccNombre.' · Rama '.$rama) : $ccNombre,
                    'nivel_ids' => [],
                ];
            }
            $lanesMap[$laneKey]['nivel_ids'][] = (int) $n->id;
        }

        // Orden de carriles: CC, luego rama A/B/_
        $lanes = array_values($lanesMap);
        usort($lanes, function ($a, $b) {
            $ca = (int) ($a['centrocosto_id'] ?? 0);
            $cb = (int) ($b['centrocosto_id'] ?? 0);
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }
            $ra = (string) ($a['rama'] ?? '');
            $rb = (string) ($b['rama'] ?? '');

            return $ra <=> $rb;
        });

        $nodes = [];
        $edges = [];
        $auto = self::autoLayout($lanes, $niveles->keyBy('id'));

        foreach ($lanes as $laneIdx => $lane) {
            $idsEnLane = $lane['nivel_ids'];
            // Orden visual: nivel, desdemonto, id
            usort($idsEnLane, function ($idA, $idB) use ($niveles) {
                /** @var Arbolaprobacion_Nivel|null $na */
                $na = $niveles->firstWhere('id', $idA);
                /** @var Arbolaprobacion_Nivel|null $nb */
                $nb = $niveles->firstWhere('id', $idB);
                if (! $na || ! $nb) {
                    return $idA <=> $idB;
                }
                if ((int) $na->nivel !== (int) $nb->nivel) {
                    return (int) $na->nivel <=> (int) $nb->nivel;
                }
                $da = (float) ($na->desdemonto ?? 0);
                $db = (float) ($nb->desdemonto ?? 0);
                if ($da != $db) {
                    return $da <=> $db;
                }

                return (int) $na->id <=> (int) $nb->id;
            });

            $prevNodeId = null;
            foreach ($idsEnLane as $nivelId) {
                /** @var Arbolaprobacion_Nivel|null $n */
                $n = $niveles->firstWhere('id', $nivelId);
                if (! $n) {
                    continue;
                }
                $nodeId = 'n'.$n->id;
                $guardada = $posGuardadas[$nodeId] ?? $posGuardadas[(string) $n->id] ?? null;
                $autoPos = $auto[$nodeId] ?? ['x' => 40, 'y' => 40];
                $x = is_array($guardada) && isset($guardada['x']) ? (float) $guardada['x'] : $autoPos['x'];
                $y = is_array($guardada) && isset($guardada['y']) ? (float) $guardada['y'] : $autoPos['y'];

                $usuario = $n->usuarios;
                $nodes[] = [
                    'id' => $nodeId,
                    'nivel_id' => (int) $n->id,
                    'lane_key' => $lane['key'],
                    'nivel' => (int) $n->nivel,
                    'rama' => $n->rama,
                    'centrocosto_id' => $n->centrocosto_id ? (int) $n->centrocosto_id : null,
                    'centrocosto_label' => $n->centrocosto_ids
                        ? trim(($n->centrocosto_ids->codigo ?? '').' '.($n->centrocosto_ids->nombre ?? ''))
                        : 'Todos',
                    'usuario_id' => $n->usuario_id ? (int) $n->usuario_id : null,
                    'usuario_codigo' => $usuario->usuario ?? '',
                    'usuario_nombre' => $usuario->nombre ?? '',
                    'auto' => ! $n->usuario_id,
                    'desdemonto' => $n->desdemonto !== null ? (float) $n->desdemonto : null,
                    'hastamonto' => $n->hastamonto !== null ? (float) $n->hastamonto : null,
                    'moneda_id' => $n->moneda_id ? (int) $n->moneda_id : null,
                    'moneda' => $n->moneda_ids->abreviatura ?? ($n->moneda_ids->nombre ?? ''),
                    'documento_estado_al_aprobar' => $n->documento_estado_al_aprobar,
                    'doble_aprobacion' => strtoupper((string) ($n->doble_aprobacion ?? 'N')) === 'S',
                    'x' => $x,
                    'y' => $y,
                ];

                if ($prevNodeId) {
                    $edges[] = ['from' => $prevNodeId, 'to' => $nodeId];
                }
                $prevNodeId = $nodeId;
            }
        }

        return [
            'arbol' => [
                'id' => (int) $arbol->id,
                'nombre' => (string) $arbol->nombre,
                'tipoarbol' => (string) $arbol->tipoarbol,
                'empresa_id' => (int) $arbol->empresa_id,
                'empresa' => $arbol->empresas->nombre ?? '',
                'estado' => (string) $arbol->estado,
            ],
            'lanes' => $lanes,
            'nodes' => $nodes,
            'edges' => $edges,
            'layout' => [
                'zoom' => (float) ($layout['zoom'] ?? 1),
                'pan_x' => (float) ($layout['pan_x'] ?? 0),
                'pan_y' => (float) ($layout['pan_y'] ?? 0),
            ],
            'meta' => [
                'node_w' => self::NODE_W,
                'node_h' => self::NODE_H,
                'usa_estado_doc' => ArbolaprobacionNivelDocumentoEstadoSupport::usaEstadoDocumento((string) $arbol->tipoarbol),
                'estados_doc' => ArbolaprobacionNivelDocumentoEstadoSupport::opcionesParaTipo((string) $arbol->tipoarbol),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lanes
     * @param  Collection<int|string, Arbolaprobacion_Nivel>  $porId
     * @return array<string, array{x: float, y: float}>
     */
    public static function autoLayout(array $lanes, Collection $porId): array
    {
        $out = [];
        $x = self::LANE_PAD;
        foreach ($lanes as $lane) {
            $ids = $lane['nivel_ids'] ?? [];
            usort($ids, function ($idA, $idB) use ($porId) {
                $na = $porId->get($idA);
                $nb = $porId->get($idB);
                if (! $na || ! $nb) {
                    return $idA <=> $idB;
                }
                if ((int) $na->nivel !== (int) $nb->nivel) {
                    return (int) $na->nivel <=> (int) $nb->nivel;
                }

                return (float) ($na->desdemonto ?? 0) <=> (float) ($nb->desdemonto ?? 0);
            });
            $y = self::LANE_PAD + 40;
            foreach ($ids as $nivelId) {
                $out['n'.$nivelId] = ['x' => (float) $x, 'y' => (float) $y];
                $y += self::NODE_H + self::GAP_Y;
            }
            $x += self::NODE_W + self::GAP_X + 24;
        }

        return $out;
    }
}
