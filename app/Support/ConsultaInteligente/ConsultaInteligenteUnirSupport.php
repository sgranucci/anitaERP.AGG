<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Grilla unificada: filas de la entidad origen + columnas de la relacionada
 * (vía JoinCatalogo, sin SQL libre).
 */
final class ConsultaInteligenteUnirSupport
{
    public const FILAS = 10;

    public const COLS_ORIGEN = 4;

    public const COLS_DESTINO = 3;

    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   desde?: EntidadSemantica,
     *   hacia?: EntidadSemantica,
     *   relacion?: array<string, mixed>,
     *   columnas: list<array{key: string, label: string, lado: string}>,
     *   filas: list<array{celdas: array<string, string>, url_origen: ?string, url_destino: ?string}>,
     *   total: int,
     *   paginator?: LengthAwarePaginator
     * }
     */
    public static function unir(
        string $desdeClave,
        string $haciaClave,
        array $filtrosOrigen,
        int $page = 1
    ): array {
        if (! EntidadSemanticaCatalogo::existe($desdeClave) || ! EntidadSemanticaCatalogo::existe($haciaClave)) {
            return ['ok' => false, 'mensaje' => 'Entidad desconocida.', 'columnas' => [], 'filas' => [], 'total' => 0];
        }
        $desde = EntidadSemanticaCatalogo::porClave($desdeClave);
        $hacia = EntidadSemanticaCatalogo::porClave($haciaClave);
        if (! can($desde->permisoListar(), false) || ! can($hacia->permisoListar(), false)) {
            return ['ok' => false, 'mensaje' => 'Sin permiso para una de las entidades.', 'columnas' => [], 'filas' => [], 'total' => 0];
        }

        $relacion = null;
        foreach (ConsultaInteligenteJoinCatalogo::desde($desdeClave) as $rel) {
            if ($rel['hacia'] === $haciaClave) {
                $relacion = $rel;
                break;
            }
        }
        if ($relacion === null) {
            return [
                'ok' => false,
                'mensaje' => 'No hay relación publicada entre esas entidades.',
                'columnas' => [],
                'filas' => [],
                'total' => 0,
            ];
        }

        $colsOrigen = array_slice($desde->columnasVisibles(), 0, self::COLS_ORIGEN);
        $colsDestino = array_slice($hacia->columnasVisibles(), 0, self::COLS_DESTINO);
        $etqOrigen = $desde->etiquetasColumnas();
        $etqDestino = $hacia->etiquetasColumnas();

        $columnas = [];
        foreach ($colsOrigen as $c) {
            $columnas[] = [
                'key' => 'o_'.$c,
                'label' => $desde->nombre().': '.($etqOrigen[$c] ?? $c),
                'lado' => 'origen',
                'campo' => $c,
            ];
        }
        foreach ($colsDestino as $c) {
            $columnas[] = [
                'key' => 'd_'.$c,
                'label' => $hacia->nombre().': '.($etqDestino[$c] ?? $c),
                'lado' => 'destino',
                'campo' => $c,
            ];
        }

        $resultado = $desde->consultar($filtrosOrigen, true);
        $total = $resultado instanceof LengthAwarePaginator
            ? (int) $resultado->total()
            : (int) (is_countable($resultado) ? count($resultado) : 0);
        $items = $resultado instanceof LengthAwarePaginator
            ? collect($resultado->items())
            : Collection::wrap($resultado)->forPage($page, self::FILAS);

        $filasOut = [];
        /** @var array<string, object|null> $cacheDestino */
        $cacheDestino = [];
        foreach ($items->take(self::FILAS) as $rowOrigen) {
            $celdas = [];
            foreach ($colsOrigen as $c) {
                $celdas['o_'.$c] = $desde->valorCelda($rowOrigen, $c);
            }
            $queryDest = ($relacion['query_desde_fila'])($rowOrigen);
            $rowDest = null;
            $urlDest = null;
            if ($queryDest !== []) {
                $queryDest['consultar'] = 1;
                $firma = md5(json_encode(self::ordenarQuery($queryDest)) ?: '');
                if (array_key_exists($firma, $cacheDestino)) {
                    $rowDest = $cacheDestino[$firma];
                } else {
                    $req = Request::create('/', 'GET', $queryDest);
                    $filtrosDest = $hacia->resolverFiltros($req);
                    try {
                        $dest = $hacia->consultar($filtrosDest, true);
                        $first = $dest instanceof LengthAwarePaginator
                            ? ($dest->items()[0] ?? null)
                            : Collection::wrap($dest)->first();
                        $rowDest = is_object($first) ? $first : null;
                    } catch (\Throwable) {
                        $rowDest = null;
                    }
                    $cacheDestino[$firma] = $rowDest;
                }
                if (is_object($rowDest)) {
                    $urlDest = $hacia->urlDetalle($rowDest);
                }
            }
            foreach ($colsDestino as $c) {
                $celdas['d_'.$c] = $rowDest
                    ? $hacia->valorCelda($rowDest, $c)
                    : '<span class="text-muted">—</span>';
            }
            $filasOut[] = [
                'celdas' => $celdas,
                'url_origen' => $desde->urlDetalle($rowOrigen),
                'url_destino' => $urlDest,
            ];
        }

        $paginator = $resultado instanceof LengthAwarePaginator
            ? $resultado
            : new Paginator($filasOut, $total, self::FILAS, $page);

        return [
            'ok' => true,
            'desde' => $desde,
            'hacia' => $hacia,
            'relacion' => [
                'titulo' => $relacion['titulo'],
                'descripcion' => $relacion['descripcion'],
            ],
            'columnas' => $columnas,
            'filas' => $filasOut,
            'total' => $total,
            'paginator' => $paginator,
        ];
    }

    /**
     * Matriz texto para export (sin HTML).
     *
     * @param  array<string, mixed>  $resultado  salida de unir()
     * @return array{columnas: list<string>, etiquetas: array<string, string>, matriz: list<array<string, string>>}
     */
    public static function aMatrizExport(array $resultado): array
    {
        $columnas = [];
        $etiquetas = [];
        foreach (($resultado['columnas'] ?? []) as $col) {
            $key = (string) ($col['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $columnas[] = $key;
            $etiquetas[$key] = (string) ($col['label'] ?? $key);
        }
        $matriz = [];
        foreach (($resultado['filas'] ?? []) as $fila) {
            $linea = [];
            foreach ($columnas as $key) {
                $raw = (string) (($fila['celdas'][$key] ?? ''));
                $linea[$key] = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $matriz[] = $linea;
        }

        return [
            'columnas' => $columnas,
            'etiquetas' => $etiquetas,
            'matriz' => $matriz,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function ordenarQuery(array $query): array
    {
        foreach ($query as $k => $v) {
            if (is_array($v)) {
                $query[$k] = self::ordenarQuery($v);
            }
        }
        ksort($query);

        return $query;
    }

    /**
     * Pares desde→hacia disponibles para el usuario.
     *
     * @return list<array{desde: string, hacia: string, titulo: string, label: string}>
     */
    public static function paresDisponibles(): array
    {
        $out = [];
        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $ent) {
            foreach (ConsultaInteligenteJoinCatalogo::desde($ent->clave()) as $rel) {
                $hacia = EntidadSemanticaCatalogo::porClave($rel['hacia']);
                $out[] = [
                    'desde' => $ent->clave(),
                    'hacia' => $rel['hacia'],
                    'titulo' => $rel['titulo'],
                    'label' => $ent->nombre().' → '.$hacia->nombre().' ('.$rel['titulo'].')',
                ];
            }
        }

        return $out;
    }
}
