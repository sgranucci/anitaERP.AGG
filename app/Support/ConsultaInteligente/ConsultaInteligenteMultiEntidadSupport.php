<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Grilla multi-entidad: mismas criterios livianos (texto + período) sobre
 * hasta N entidades autorizadas, en paneles lado a lado.
 */
final class ConsultaInteligenteMultiEntidadSupport
{
    public const MAX_ENTIDADES = 3;

    public const FILAS_POR_PANEL = 10;

    public const COLUMNAS_POR_PANEL = 5;

    /**
     * @param  list<string>  $claves
     * @return array{
     *   paneles: list<array<string, mixed>>,
     *   filtros: array{filtro_valor: string, periodo: string, entidades: list<string>},
     *   periodos: array<string, string>
     * }
     */
    public static function consultar(array $claves, string $filtroValor = '', string $periodo = ''): array
    {
        $claves = self::normalizarClaves($claves);
        $filtroValor = trim($filtroValor);
        $periodo = self::periodoValido($periodo);

        $paneles = [];
        foreach ($claves as $clave) {
            $ent = EntidadSemanticaCatalogo::porClave($clave);
            $query = self::armarQuery($clave, $filtroValor, $periodo);
            $filtrosInternos = self::resolverFiltrosDesdeQuery($ent, $query);
            $resultado = $ent->consultar($filtrosInternos, true);
            $total = $resultado instanceof LengthAwarePaginator
                ? (int) $resultado->total()
                : (int) (is_countable($resultado) ? count($resultado) : 0);
            $items = $resultado instanceof LengthAwarePaginator
                ? collect($resultado->items())
                : Collection::wrap($resultado)->take(self::FILAS_POR_PANEL);

            $columnas = array_slice($ent->columnasVisibles(), 0, self::COLUMNAS_POR_PANEL);
            $etiquetas = $ent->etiquetasColumnas();
            $filas = [];
            foreach ($items->take(self::FILAS_POR_PANEL) as $row) {
                $celdas = [];
                foreach ($columnas as $col) {
                    $celdas[$col] = $ent->valorCelda($row, $col);
                }
                $filas[] = [
                    'celdas' => $celdas,
                    'url' => $ent->urlDetalle($row),
                ];
            }

            $paneles[] = [
                'clave' => $clave,
                'nombre' => $ent->nombre(),
                'modulo' => $ent->modulo(),
                'color' => $ent->color(),
                'icono' => $ent->icono(),
                'total' => $total,
                'columnas' => $columnas,
                'etiquetas' => $etiquetas,
                'filas' => $filas,
                'url_explorar' => EntidadSemanticaCatalogo::urlExplorar($clave, $query),
                'sin_periodo' => $periodo !== '' && self::campoFecha($clave) === null,
            ];
        }

        return [
            'paneles' => $paneles,
            'filtros' => [
                'filtro_valor' => $filtroValor,
                'periodo' => $periodo,
                'entidades' => $claves,
            ],
            'periodos' => self::periodosDisponibles(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function periodosDisponibles(): array
    {
        return [
            '' => 'Sin período',
            'hoy' => 'Hoy',
            'ayer' => 'Ayer',
            'esta_semana' => 'Esta semana',
            'este_mes' => 'Este mes',
            'mes_anterior' => 'Mes anterior',
            'este_anio' => 'Este año',
        ];
    }

    /**
     * @param  list<string>|array<int|string, mixed>  $claves
     * @return list<string>
     */
    public static function normalizarClaves(array $claves): array
    {
        $out = [];
        foreach ($claves as $c) {
            $c = trim((string) $c);
            if ($c === '' || ! EntidadSemanticaCatalogo::existe($c)) {
                continue;
            }
            $ent = EntidadSemanticaCatalogo::porClave($c);
            if (! can($ent->permisoListar(), false)) {
                continue;
            }
            if (! in_array($c, $out, true)) {
                $out[] = $c;
            }
            if (count($out) >= self::MAX_ENTIDADES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarQuery(string $clave, string $filtroValor, string $periodo): array
    {
        $query = ['consultar' => 1];
        if ($filtroValor !== '') {
            $query['filtro_valor'] = $filtroValor;
        }
        $campoFecha = self::campoFecha($clave);
        if ($periodo !== '' && $campoFecha !== null) {
            $query['filtro_modo'] = 'qbe';
            $query['qbe'] = [
                'entre_grupos' => 'and',
                'grupos' => [[
                    'logic' => 'and',
                    'not' => 0,
                    'criterios' => [[
                        'campo' => $campoFecha,
                        'op' => $periodo,
                        'valor' => '',
                        'valor_hasta' => '',
                    ]],
                ]],
            ];
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function resolverFiltrosDesdeQuery(EntidadSemantica $ent, array $query): array
    {
        $request = Request::create('/', 'GET', $query);

        return $ent->resolverFiltros($request);
    }

    private static function campoFecha(string $entidad): ?string
    {
        return ConsultaInteligenteNlQbeSupport::campoFecha($entidad);
    }

    private static function periodoValido(string $periodo): string
    {
        return array_key_exists($periodo, self::periodosDisponibles()) ? $periodo : '';
    }
}
