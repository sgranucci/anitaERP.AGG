<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\Models\Ventas\MesaGastronomia;

/**
 * Punto único de normalización/validación del `layout_json` de un plano de mesas.
 *
 * Estructura: {version:1, width, height, grid:20, nodes:[{id, mesa_id, x, y, w, h, rotation, shape}]}
 */
class GastroPlanoMesaLayoutSupport
{
    public const VERSION = 1;

    public const GRID = 20;

    public const ANCHO_DEFAULT = 1200;

    public const ALTO_DEFAULT = 800;

    /** @var list<string> */
    public const SHAPES = ['rect', 'round', 'oval'];

    public static function vacio(): array
    {
        return [
            'version' => self::VERSION,
            'width' => self::ANCHO_DEFAULT,
            'height' => self::ALTO_DEFAULT,
            'grid' => self::GRID,
            'nodes' => [],
        ];
    }

    /**
     * Decodifica el JSON que llega del editor (string o array) sin validar mesas.
     */
    public static function decodificar(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }
        if (! is_array($valor)) {
            return self::vacio();
        }

        return $valor;
    }

    /**
     * Normaliza el layout y descarta nodos cuya mesa no pertenezca a la empresa/sala del plano.
     *
     * @return array{layout: array, descartados: int}
     */
    public static function normalizarParaPlano(mixed $valor, int $empresaId, ?int $salaId): array
    {
        $crudo = self::decodificar($valor);

        $layout = self::vacio();
        $layout['width'] = self::entero($crudo['width'] ?? null, self::ANCHO_DEFAULT, 200, 6000);
        $layout['height'] = self::entero($crudo['height'] ?? null, self::ALTO_DEFAULT, 200, 6000);
        $layout['grid'] = self::entero($crudo['grid'] ?? null, self::GRID, 5, 100);

        $nodos = $crudo['nodes'] ?? [];
        if (! is_array($nodos)) {
            $nodos = [];
        }

        $mesaIds = collect($nodos)
            ->pluck('mesa_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validas = $mesaIds->isEmpty()
            ? collect()
            : MesaGastronomia::query()
                ->whereIn('id', $mesaIds->all())
                ->where('empresa_id', $empresaId)
                ->when($salaId, fn ($q) => $q->where('sala_id', $salaId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        $validas = collect($validas);

        $descartados = 0;
        $vistos = [];
        $limpios = [];
        foreach ($nodos as $i => $nodo) {
            if (! is_array($nodo)) {
                $descartados++;

                continue;
            }
            $mesaId = (int) ($nodo['mesa_id'] ?? 0);
            if ($mesaId <= 0 || ! $validas->contains($mesaId) || isset($vistos[$mesaId])) {
                $descartados++;

                continue;
            }
            $vistos[$mesaId] = true;

            $shape = (string) ($nodo['shape'] ?? 'rect');
            if (! in_array($shape, self::SHAPES, true)) {
                $shape = 'rect';
            }

            $limpios[] = [
                'id' => self::idNodo($nodo['id'] ?? null, $mesaId, $i),
                'mesa_id' => $mesaId,
                'x' => self::entero($nodo['x'] ?? null, 0, -10000, 10000),
                'y' => self::entero($nodo['y'] ?? null, 0, -10000, 10000),
                'w' => self::entero($nodo['w'] ?? null, 80, 20, 1200),
                'h' => self::entero($nodo['h'] ?? null, 80, 20, 1200),
                'rotation' => self::entero($nodo['rotation'] ?? null, 0, -360, 360),
                'shape' => $shape,
            ];
        }

        $layout['nodes'] = $limpios;

        return ['layout' => $layout, 'descartados' => $descartados];
    }

    /**
     * Layout listo para el visor del POS (sin datos de edición).
     */
    public static function paraVisor(mixed $valor): array
    {
        $crudo = self::decodificar($valor);
        $layout = self::vacio();
        $layout['width'] = self::entero($crudo['width'] ?? null, self::ANCHO_DEFAULT, 200, 6000);
        $layout['height'] = self::entero($crudo['height'] ?? null, self::ALTO_DEFAULT, 200, 6000);
        $layout['grid'] = self::entero($crudo['grid'] ?? null, self::GRID, 5, 100);

        $nodos = is_array($crudo['nodes'] ?? null) ? $crudo['nodes'] : [];
        $layout['nodes'] = collect($nodos)
            ->filter(fn ($n) => is_array($n) && (int) ($n['mesa_id'] ?? 0) > 0)
            ->map(fn ($n, $i) => [
                'id' => self::idNodo($n['id'] ?? null, (int) $n['mesa_id'], (int) $i),
                'mesa_id' => (int) $n['mesa_id'],
                'x' => self::entero($n['x'] ?? null, 0, -10000, 10000),
                'y' => self::entero($n['y'] ?? null, 0, -10000, 10000),
                'w' => self::entero($n['w'] ?? null, 80, 20, 1200),
                'h' => self::entero($n['h'] ?? null, 80, 20, 1200),
                'rotation' => self::entero($n['rotation'] ?? null, 0, -360, 360),
                'shape' => in_array((string) ($n['shape'] ?? 'rect'), self::SHAPES, true)
                    ? (string) $n['shape']
                    : 'rect',
            ])
            ->values()
            ->all();

        return $layout;
    }

    private static function idNodo(mixed $id, int $mesaId, int $indice): string
    {
        $id = trim((string) ($id ?? ''));
        if ($id !== '') {
            return mb_substr($id, 0, 40);
        }

        return 'n'.$mesaId.'_'.$indice;
    }

    private static function entero(mixed $valor, int $default, int $min, int $max): int
    {
        if (! is_numeric($valor)) {
            return $default;
        }

        return (int) max($min, min($max, round((float) $valor)));
    }
}
