<?php

namespace App\Support\Compras;

/**
 * Arma los datasets que consume tablero.js (Chart.js 2.9) a partir del resultado del tablero.
 */
final class ComprasTableroOcGraficosSupport
{
    /**
     * @param  array<string, mixed>  $tablero
     * @param  list<array{key: string, label: string}>  $meses
     * @return array<string, mixed>
     */
    public static function desde(array $tablero, array $meses, ?array $ahorros = null): array
    {
        $claves = array_column($meses, 'key');
        $etiquetas = array_column($meses, 'label');

        $porMes = static fn (array $mapa): array => array_map(static fn (string $k) => $mapa[$k] ?? 0, $claves);

        $estadosMes = [];
        foreach ($tablero['estados_mes'] as $estado => $mapa) {
            $estadosMes[] = ['label' => $estado, 'data' => $porMes($mapa)];
        }

        $vencidasMes = [];
        foreach ($tablero['pendientes']['vencidas_mes'] as $estado => $mapa) {
            $vencidasMes[] = ['label' => $estado, 'data' => $porMes($mapa)];
        }

        $diarias = $tablero['diarias']['por_mes'];

        $compradores = $tablero['compradores'];
        $compradoresOcMes = [];
        $compradoresGestionMes = [];
        foreach (array_slice($compradores, 0, 8) as $c) {
            $compradoresOcMes[] = ['label' => $c['nombre'], 'data' => $porMes($c['oc_mes'])];
            $compradoresGestionMes[] = [
                'label' => $c['nombre'],
                'data' => array_map(
                    static fn (string $k) => ($c['gestion_mes'][$k]['n'] ?? 0) > 0
                        ? round($c['gestion_mes'][$k]['suma'] / $c['gestion_mes'][$k]['n'], 2)
                        : null,
                    $claves
                ),
            ];
        }

        $gestionRanking = array_values(array_filter($compradores, fn ($c) => $c['gestion_prom'] !== null));
        usort($gestionRanking, fn ($a, $b) => $b['gestion_prom'] <=> $a['gestion_prom']);

        $precios = $tablero['precios'] ?? null;

        return [
            'meses' => $etiquetas,
            'estados' => [
                'labels' => array_keys($tablero['estados']),
                'values' => array_values($tablero['estados']),
            ],
            'estados_mes' => $estadosMes,
            'ciclo' => [
                'aprobacion' => round((float) ($tablero['ciclo']['aprobacion'] ?? 0), 2),
                'gestion' => round((float) ($tablero['ciclo']['gestion'] ?? 0), 2),
                'entrega' => round((float) ($tablero['ciclo']['entrega'] ?? 0), 2),
            ],
            'diarias' => [
                'req' => array_map(static fn (string $k) => round((float) ($diarias[$k]['req_dia'] ?? 0), 1), $claves),
                'oc' => array_map(static fn (string $k) => round((float) ($diarias[$k]['oc_dia'] ?? 0), 1), $claves),
            ],
            'pendientes' => [
                'labels' => ['A tiempo', 'Vencida'],
                'values' => [$tablero['pendientes']['a_tiempo'], $tablero['pendientes']['vencida']],
            ],
            'vencidas_mes' => $vencidasMes,
            'urgentes' => [
                'labels' => ['No urgente', 'Urgente'],
                'values' => [$tablero['urgentes']['no_urgente'], $tablero['urgentes']['urgente']],
            ],
            'compradores_oc' => [
                'labels' => array_column($compradores, 'nombre'),
                'values' => array_column($compradores, 'oc'),
            ],
            'compradores_oc_mes' => $compradoresOcMes,
            'compradores_gestion' => [
                'labels' => array_column($gestionRanking, 'nombre'),
                'values' => array_map(static fn ($c) => round($c['gestion_prom'], 2), $gestionRanking),
            ],
            'compradores_gestion_mes' => $compradoresGestionMes,
            'empresas' => [
                'labels' => array_keys($tablero['empresas']),
                'values' => array_values($tablero['empresas']),
            ],
            'prov_entrega' => [
                'labels' => array_column($tablero['proveedores_entrega'], 'nombre'),
                'values' => array_map(static fn ($p) => round($p['entrega_prom'], 1), $tablero['proveedores_entrega']),
            ],
            'prov_activos_mes' => $porMes($tablero['proveedores_activos_mes']),
            'prov_lineas' => [
                'labels' => array_column($tablero['proveedores_lineas'], 'nombre'),
                'values' => array_column($tablero['proveedores_lineas'], 'lineas'),
            ],
            'lineas_mes' => $porMes($tablero['lineas_mes']),
            'prov_monto' => [
                'labels' => array_column($tablero['proveedores_monto'], 'nombre'),
                'values' => array_map(static fn ($p) => round($p['monto'], 2), $tablero['proveedores_monto']),
            ],
            'monto_mes' => array_map(static fn (string $k) => round((float) ($tablero['monto_mes'][$k] ?? 0), 2), $claves),
            'ahorros' => $ahorros === null ? null : [
                'compradores' => [
                    'labels' => array_column($ahorros['compradores'], 'nombre'),
                    'values' => array_map(static fn ($c) => round($c['ahorro'], 2), $ahorros['compradores']),
                ],
                'compradores_mes' => array_map(
                    static fn (array $c) => [
                        'label' => $c['nombre'],
                        'data' => array_map(static fn (string $k) => round((float) ($c['por_mes'][$k] ?? 0), 2), $claves),
                    ],
                    $ahorros['compradores_mes']
                ),
                // La torta no admite negativos: el sobrecosto de un tipo no se dibuja como porción.
                'tipos' => [
                    'labels' => array_column(array_filter($ahorros['tipos'], static fn ($t) => $t['ahorro'] > 0), 'nombre'),
                    'values' => array_values(array_map(
                        static fn ($t) => round($t['ahorro'], 2),
                        array_filter($ahorros['tipos'], static fn ($t) => $t['ahorro'] > 0)
                    )),
                ],
                'proveedores' => [
                    'labels' => array_column($ahorros['proveedores'], 'nombre'),
                    'values' => array_map(static fn ($p) => round($p['ahorro'], 2), $ahorros['proveedores']),
                ],
                'mes' => array_map(static fn (string $k) => round((float) ($ahorros['ahorro_mes'][$k] ?? 0), 2), $claves),
            ],
            'precios' => $precios === null ? null : [
                'series' => $precios['series'],
                'cantidad' => array_map(static fn (string $k) => round((float) ($precios['cantidad_mes'][$k] ?? 0), 2), $claves),
            ],
        ];
    }
}
