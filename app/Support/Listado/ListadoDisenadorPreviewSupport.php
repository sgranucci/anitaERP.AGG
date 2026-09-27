<?php

declare(strict_types=1);

namespace App\Support\Listado;

/**
 * Preview del diseñador de vistas (packs A wireframe + B muestra).
 * Contrato JSON compartido por recursos workbench.
 */
final class ListadoDisenadorPreviewSupport
{
    public const LIMITE_MUESTRA = 20;

    /**
     * @param  list<array{key: string, titulo?: string, visible?: bool, ancho?: int, alinea?: string}>  $layout
     * @param  list<array{campo: string, dir: string}>  $orden
     * @param  list<string>  $agrupar
     * @return array{columnas: list<array{key: string, titulo: string, ancho: int, alinea: string}>, orden: list, agrupar: list, chips: list<string>}
     */
    public static function wireframe(array $layout, array $orden = [], array $agrupar = [], array $etiquetas = []): array
    {
        $columnas = [];
        foreach ($layout as $fila) {
            if (! is_array($fila) || empty($fila['key'])) {
                continue;
            }
            if (array_key_exists('visible', $fila) && ! $fila['visible']) {
                continue;
            }
            $key = (string) $fila['key'];
            $columnas[] = [
                'key' => $key,
                'titulo' => (string) ($fila['titulo'] ?? $etiquetas[$key] ?? $key),
                'ancho' => (int) ($fila['ancho'] ?? 120),
                'alinea' => (string) ($fila['alinea'] ?? 'izquierda'),
            ];
        }

        $chips = [];
        foreach ($orden as $i => $o) {
            if (! is_array($o) || empty($o['campo'])) {
                continue;
            }
            $dir = (($o['dir'] ?? 'asc') === 'desc') ? '↓' : '↑';
            $label = $etiquetas[$o['campo']] ?? $o['campo'];
            $chips[] = 'Orden '.($i + 1).': '.$label.' '.$dir;
        }
        foreach ($agrupar as $i => $campo) {
            $campo = trim((string) $campo);
            if ($campo === '') {
                continue;
            }
            $label = $etiquetas[$campo] ?? $campo;
            $chips[] = 'Grupo '.($i + 1).': '.$label;
        }

        return [
            'columnas' => $columnas,
            'orden' => array_values(array_filter($orden, static fn ($o) => is_array($o) && ! empty($o['campo']))),
            'agrupar' => array_values(array_filter(array_map('strval', $agrupar))),
            'chips' => $chips,
        ];
    }

    /**
     * @param  iterable<object>  $filas
     * @param  list<string>  $keysVisibles
     * @param  callable(object, string): string  $valorCelda
     * @return list<array{cells: list<string>}>
     */
    public static function filasMuestra(iterable $filas, array $keysVisibles, callable $valorCelda): array
    {
        $out = [];
        $n = 0;
        foreach ($filas as $row) {
            if ($n >= self::LIMITE_MUESTRA) {
                break;
            }
            $cells = [];
            foreach ($keysVisibles as $key) {
                $cells[] = $valorCelda($row, $key);
            }
            $out[] = ['type' => 'row', 'cells' => $cells];
            $n++;
        }

        return $out;
    }

    /**
     * Muestra con cabeceras de grupo (como la grilla) y los cortes del universo que no entran en las primeras filas.
     *
     * @param  iterable<object>  $filas
     * @param  list<string>  $keysVisibles
     * @param  callable(object, string): string  $valorCelda
     * @param  list<string>  $agrupar
     * @param  array<string, string>  $etiquetas
     * @param  array<string, mixed>  $cortes
     * @return list<array<string, mixed>>
     */
    private static function filasPreview(
        iterable $filas,
        array $keysVisibles,
        callable $valorCelda,
        array $agrupar,
        array $etiquetas,
        array $cortes
    ): array {
        if ($agrupar === []) {
            return self::filasMuestra($filas, $keysVisibles, $valorCelda);
        }

        $segmentadas = ListadoAgrupacionSupport::segmentar(
            $filas,
            $agrupar,
            $valorCelda,
            $etiquetas,
            ! empty($cortes['por_clave']) && is_array($cortes['por_clave']) ? $cortes['por_clave'] : null
        );

        $out = [];
        $n = 0;
        foreach ($segmentadas as $item) {
            if (($item['type'] ?? '') === 'header') {
                $out[] = [
                    'type' => 'header',
                    'nivel' => (int) ($item['nivel'] ?? 0),
                    'label' => (string) ($item['label'] ?? ''),
                    'valor' => (string) ($item['valor'] ?? ''),
                    'count' => (int) ($item['count'] ?? 0),
                ];

                continue;
            }
            if ($n >= self::LIMITE_MUESTRA) {
                break;
            }
            $row = $item['row'] ?? null;
            if (! is_object($row)) {
                continue;
            }
            $cells = [];
            foreach ($keysVisibles as $key) {
                $cells[] = $valorCelda($row, $key);
            }
            $out[] = ['type' => 'row', 'cells' => $cells];
            $n++;
        }

        return $out;
    }

    /**
     * Payload completo preview B.
     *
     * @param  list<array{key: string, titulo?: string, visible?: bool, ancho?: int, alinea?: string}>  $layout
     * @param  list<array{campo: string, dir: string}>  $orden
     * @param  list<string>  $agrupar
     * @param  iterable<object>  $filas
     * @param  callable(object, string): string  $valorCelda
     * @return array<string, mixed>
     */
    public static function payload(
        array $layout,
        array $orden,
        array $agrupar,
        iterable $filas,
        callable $valorCelda,
        int $totalUniverso,
        array $etiquetas = [],
        array $cortes = []
    ): array {
        $wire = self::wireframe($layout, $orden, $agrupar, $etiquetas);
        $keys = array_column($wire['columnas'], 'key');
        $filasOut = self::filasPreview($filas, $keys, $valorCelda, $agrupar, $etiquetas, $cortes);
        $muestra = 0;
        foreach ($filasOut as $filaPreview) {
            if (($filaPreview['type'] ?? '') === 'row') {
                $muestra++;
            }
        }

        $chips = $wire['chips'];
        foreach (ListadoCortesSupport::chipsResumen($cortes) as $chip) {
            $chips[] = $chip;
        }

        return array_merge($wire, [
            'chips' => $chips,
            'filas' => $filasOut,
            'muestra' => $muestra,
            'total' => $totalUniverso,
            'limite' => self::LIMITE_MUESTRA,
            'cortes' => $cortes,
        ]);
    }
}
