<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\DescuentoGastronomia;
use App\Queries\Ventas\GastronomiaDescuentoReporteQuery;
use App\Services\Stock\PrecioService;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCodigoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;

final class GastronomiaDescuentoReporteService
{
    public function __construct(
        private readonly GastronomiaDescuentoReporteQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   bloques:list<array<string,mixed>>,
     *   totales:list<array{codigo:string,sector:string,costo_total:float}>,
     *   gran_total_costo:float,
     *   gran_total_venta:float,
     *   gran_total_unidades:float,
     *   listas_costo:array<string,mixed>,
     *   codigos_sin_datos:list<string>,
     *   clientes_sin_datos:list<int>,
     *   vista_columnas:array<string,mixed>|null,
     *   periodo_texto:string,
     *   mes_etiqueta:string,
     *   agrupar_por:string
     * }
     */
    public function generar(array $filtros): array
    {
        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);
        $filasRaw = $this->query->filasAgregadas($filtros);

        $fechaCosto = trim((string) ($filtros['fecha_hasta'] ?? ''));
        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada(
            $fechaCosto !== '' ? $fechaCosto : now()->toDateString(),
        );
        $listaCostoId = $this->resolverListaprecioId((string) $listas['lista_actual']);
        $cacheCosto = [];

        $porClave = [];
        foreach ($filasRaw as $fila) {
            $meta = $this->metaAgrupacion($fila, $agruparPor);
            $clave = $meta['clave'];

            if (! isset($porClave[$clave])) {
                $porClave[$clave] = array_merge($meta, [
                    'filas_map' => [],
                    'totales' => [
                        'unidades' => 0.0,
                        'costo_total' => 0.0,
                        'total_venta' => 0.0,
                    ],
                ]);
            }

            $costoUnit = $this->resolverCostoUnitario(
                (int) $fila->articulo_id,
                $listaCostoId,
                $fechaCosto,
                $cacheCosto,
            );
            $unidades = (float) $fila->unidades;
            $totalVenta = (float) $fila->total_venta;
            $costoTotal = round($unidades * $costoUnit, 2);
            $precioVenta = $unidades > 0.0001 ? round($totalVenta / $unidades, 2) : 0.0;

            $articuloId = (int) $fila->articulo_id;
            if (! isset($porClave[$clave]['filas_map'][$articuloId])) {
                $porClave[$clave]['filas_map'][$articuloId] = [
                    'articulo_id' => $articuloId,
                    'sku' => $fila->sku,
                    'descripcion' => $fila->descripcion,
                    'unidades' => 0.0,
                    'costo_unitario' => $costoUnit,
                    'costo_total' => 0.0,
                    'precio_venta' => 0.0,
                    'total_venta' => 0.0,
                ];
            }

            $ref = &$porClave[$clave]['filas_map'][$articuloId];
            $ref['unidades'] = round($ref['unidades'] + $unidades, 4);
            $ref['costo_total'] = round($ref['costo_total'] + $costoTotal, 2);
            $ref['total_venta'] = round($ref['total_venta'] + $totalVenta, 2);
            if ($ref['unidades'] > 0.0001) {
                $ref['precio_venta'] = round($ref['total_venta'] / $ref['unidades'], 2);
            }
            if ($costoUnit > 0) {
                $ref['costo_unitario'] = $costoUnit;
            }

            $porClave[$clave]['totales']['unidades'] = round(
                $porClave[$clave]['totales']['unidades'] + $unidades,
                4,
            );
            $porClave[$clave]['totales']['costo_total'] = round(
                $porClave[$clave]['totales']['costo_total'] + $costoTotal,
                2,
            );
            $porClave[$clave]['totales']['total_venta'] = round(
                $porClave[$clave]['totales']['total_venta'] + $totalVenta,
                2,
            );
            unset($ref);
        }

        $ordenClaves = $this->ordenClavesSolicitadas($filtros, $porClave, $agruparPor);
        $bloques = [];
        $totales = [];
        $granTotalCosto = 0.0;
        $granTotalVenta = 0.0;
        $granTotalUnidades = 0.0;

        foreach ($ordenClaves as $clave) {
            if (! isset($porClave[$clave])) {
                continue;
            }

            $item = $porClave[$clave];
            $filas = array_values($item['filas_map']);
            usort($filas, fn (array $a, array $b) => strcmp((string) $a['sku'], (string) $b['sku']));

            $bloque = [
                'clave' => $item['clave'],
                'tipo_agrupacion' => $item['tipo_agrupacion'],
                'codigo' => $item['codigo'],
                'nombre' => $item['nombre'],
                'filas' => $filas,
                'totales' => $item['totales'],
            ];

            if (($bloque['totales']['unidades'] ?? 0) <= 0.0001 || $filas === []) {
                continue;
            }

            $bloques[] = $bloque;

            $totales[] = [
                'clave' => $bloque['clave'],
                'tipo_agrupacion' => $bloque['tipo_agrupacion'],
                'codigo' => $bloque['codigo'],
                'sector' => $bloque['nombre'],
                'costo_total' => $bloque['totales']['costo_total'],
            ];

            $granTotalCosto += $bloque['totales']['costo_total'];
            $granTotalVenta += $bloque['totales']['total_venta'];
            $granTotalUnidades += $bloque['totales']['unidades'];
        }

        $codigosSinDatos = [];
        $clientesSinDatos = [];
        if (empty($filtros['listar_todos'])) {
            $codigosSolicitados = $filtros['codigos_descuento_resueltos'] ?? [];
            if ($codigosSolicitados !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO) {
                $codigosConDatos = array_map(fn (array $b) => $b['codigo'], $bloques);
                $codigosSinDatos = array_values(array_diff($codigosSolicitados, $codigosConDatos));
            }

            $clientesSolicitados = $filtros['clientes_descuento_ids'] ?? [];
            if ($clientesSolicitados !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
                $clientesConDatos = [];
                foreach ($bloques as $bloque) {
                    if (preg_match('/^c_(\d+)$/', (string) $bloque['clave'], $m)) {
                        $clientesConDatos[] = (int) $m[1];
                    }
                }
                $clientesSinDatos = array_values(array_diff($clientesSolicitados, $clientesConDatos));
            }
        }

        return $this->armarResultado(
            $filtros,
            $agruparPor,
            $bloques,
            $totales,
            $granTotalCosto,
            $granTotalVenta,
            $granTotalUnidades,
            $listas,
            $codigosSinDatos,
            $clientesSinDatos,
        );
    }

    /**
     * Garantiza vista_columnas cuando la presentación consolidada está activa
     * (incluye Listar todos: una columna por cada bloque con ventas).
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function enriquecerVistaColumnas(array $filtros, array $resultado): array
    {
        if (empty($filtros['presentacion_columnas'])) {
            $resultado['vista_columnas'] = null;

            return $resultado;
        }

        $bloques = $resultado['bloques'] ?? [];
        if ($bloques === []) {
            $resultado['vista_columnas'] = null;

            return $resultado;
        }

        $vista = $resultado['vista_columnas'] ?? null;
        if (! is_array($vista) || ($vista['columnas'] ?? []) === []) {
            $resultado['vista_columnas'] = $this->construirVistaColumnas($bloques);
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  list<array{codigo:string,sector:string,costo_total:float}>  $totales
     * @param  array<string, mixed>  $listas
     * @param  list<string>  $codigosSinDatos
     * @param  list<int>  $clientesSinDatos
     * @return array<string, mixed>
     */
    private function armarResultado(
        array $filtros,
        string $agruparPor,
        array $bloques,
        array $totales,
        float $granTotalCosto,
        float $granTotalVenta,
        float $granTotalUnidades,
        array $listas,
        array $codigosSinDatos,
        array $clientesSinDatos,
    ): array {
        $resultado = [
            'bloques' => $bloques,
            'totales' => $totales,
            'gran_total_costo' => round($granTotalCosto, 2),
            'gran_total_venta' => round($granTotalVenta, 2),
            'gran_total_unidades' => round($granTotalUnidades, 4),
            'listas_costo' => $listas,
            'codigos_sin_datos' => $codigosSinDatos,
            'clientes_sin_datos' => $clientesSinDatos,
            'vista_columnas' => null,
            'periodo_texto' => GastronomiaDescuentoReporteFiltros::formatearPeriodoTexto($filtros),
            'mes_etiqueta' => GastronomiaDescuentoReporteFiltros::etiquetaMes($filtros),
            'agrupar_por' => $agruparPor,
        ];

        return $this->enriquecerVistaColumnas($filtros, $resultado);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public function advertencias(array $filtros): array
    {
        $avisos = [];
        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $codigosFiltro = $filtros['codigos_descuento_cliente_resueltos'] ?? [];
            if ($codigosFiltro !== []) {
                $existentes = GastronomiaDescuentoReporteCodigoSupport::resolverExistentes($codigosFiltro);
                $faltantes = array_values(array_diff($codigosFiltro, $existentes));
                if ($faltantes !== []) {
                    $avisos[] = 'Códigos de filtro no registrados en descuentos gastronomía: '.implode(', ', $faltantes).'.';
                }
            }
        }

        if (! empty($filtros['listar_todos'])) {
            return $avisos;
        }

        $codigos = $filtros['codigos_descuento_resueltos'] ?? [];
        $clienteIds = $filtros['clientes_descuento_ids'] ?? [];

        if ($codigos === [] && $clienteIds === []) {
            return array_merge($avisos, ['Seleccione al menos un descuento, un cliente interno de descuento, o marque Listar todos.']);
        }

        if ($codigos !== []) {
            $existentes = GastronomiaDescuentoReporteCodigoSupport::resolverExistentes($codigos);
            $faltantes = array_values(array_diff($codigos, $existentes));
            if ($faltantes !== []) {
                $avisos[] = 'Códigos no registrados en descuentos gastronomía: '.implode(', ', $faltantes).'.';
            }
        }

        if ($clienteIds !== []) {
            $existentesIds = Cliente::query()->whereIn('id', $clienteIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $faltantesIds = array_values(array_diff($clienteIds, $existentesIds));
            if ($faltantesIds !== []) {
                $avisos[] = 'Clientes internos no registrados: ID '.implode(', ', $faltantesIds).'.';
            }
        }

        return $avisos;
    }

    /**
     * @param  object  $fila
     * @return array{clave:string,tipo_agrupacion:string,codigo:string,nombre:string}
     */
    private function metaAgrupacion(object $fila, string $agruparPor): array
    {
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $clienteId = (int) ($fila->cliente_interno_id ?? 0);

            return [
                'clave' => 'c_'.$clienteId,
                'tipo_agrupacion' => GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE,
                'codigo' => $clienteId > 0 ? ($fila->cliente_codigo ?: (string) $clienteId) : '—',
                'nombre' => $clienteId > 0 ? $fila->cliente_nombre : 'Sin cliente interno',
            ];
        }

        $codigo = trim((string) $fila->descuento_codigo);

        return [
            'clave' => 'd_'.$codigo,
            'tipo_agrupacion' => GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO,
            'codigo' => $codigo,
            'nombre' => trim((string) $fila->descuento_nombre),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, array<string, mixed>>  $porClave
     * @return list<string>
     */
    private function ordenClavesSolicitadas(array $filtros, array $porClave, string $agruparPor): array
    {
        if (! empty($filtros['listar_todos'])) {
            $claves = array_keys($porClave);
            usort($claves, function (string $a, string $b) use ($porClave) {
                $ca = (string) ($porClave[$a]['codigo'] ?? '');
                $cb = (string) ($porClave[$b]['codigo'] ?? '');
                if (ctype_digit($ca) && ctype_digit($cb)) {
                    return (int) $ca <=> (int) $cb;
                }

                return strcmp($ca, $cb);
            });

            return $claves;
        }

        if ($porClave === []) {
            return [];
        }

        $orden = [];
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            foreach ($filtros['clientes_descuento_ids'] ?? [] as $clienteId) {
                $clave = 'c_'.(int) $clienteId;
                if (isset($porClave[$clave])) {
                    $orden[] = $clave;
                }
            }

            return $orden;
        }

        foreach ($filtros['codigos_descuento_resueltos'] ?? [] as $codigo) {
            $clave = 'd_'.trim((string) $codigo);
            if (isset($porClave[$clave])) {
                $orden[] = $clave;
            }
        }

        return $orden;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return array{columnas:list<array<string,mixed>>,filas:list<array<string,mixed>>,totales_por_columna:list<array<string,mixed>>}
     */
    private function construirVistaColumnas(array $bloques): array
    {
        $columnas = [];
        $articulos = [];
        $totalesPorColumna = [];

        foreach ($bloques as $bloque) {
            $columnas[] = [
                'clave' => $bloque['clave'],
                'codigo' => $bloque['codigo'],
                'nombre' => $bloque['nombre'],
                'titulo' => ($bloque['codigo'] ?? '').' — '.($bloque['nombre'] ?? ''),
            ];
            $totalesPorColumna[] = [
                'clave' => $bloque['clave'],
                'totales' => $bloque['totales'] ?? [],
            ];

            foreach ($bloque['filas'] ?? [] as $fila) {
                $articuloId = (int) ($fila['articulo_id'] ?? 0);
                if (! isset($articulos[$articuloId])) {
                    $articulos[$articuloId] = [
                        'articulo_id' => $articuloId,
                        'sku' => $fila['sku'] ?? '',
                        'descripcion' => $fila['descripcion'] ?? '',
                        'costo_unitario' => (float) ($fila['costo_unitario'] ?? 0),
                        'precio_venta' => (float) ($fila['precio_venta'] ?? 0),
                        'celdas' => [],
                    ];
                }
                if ((float) ($articulos[$articuloId]['costo_unitario'] ?? 0) <= 0 && (float) ($fila['costo_unitario'] ?? 0) > 0) {
                    $articulos[$articuloId]['costo_unitario'] = (float) $fila['costo_unitario'];
                }
                if ((float) ($articulos[$articuloId]['precio_venta'] ?? 0) <= 0 && (float) ($fila['precio_venta'] ?? 0) > 0) {
                    $articulos[$articuloId]['precio_venta'] = (float) $fila['precio_venta'];
                }
                $articulos[$articuloId]['celdas'][$bloque['clave']] = $fila;
            }
        }

        $filas = array_values($articulos);
        usort($filas, fn (array $a, array $b) => strcmp((string) $a['sku'], (string) $b['sku']));

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'totales_por_columna' => $totalesPorColumna,
        ];
    }

    /**
     * @param  array<string, float>  $cache
     */
    private function resolverCostoUnitario(
        int $articuloId,
        ?int $listaprecioId,
        string $fechaReferencia,
        array &$cache,
    ): float {
        if ($articuloId <= 0 || $listaprecioId === null || $fechaReferencia === '') {
            return 0.0;
        }

        $key = $articuloId.'|'.$listaprecioId.'|'.$fechaReferencia;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaprecioId, $fechaReferencia);
        $cache[$key] = $precios !== []
            ? round((float) (end($precios)['precio'] ?? 0), 2)
            : 0.0;

        return $cache[$key];
    }

    private function resolverListaprecioId(string $codigoLista): ?int
    {
        $codigoLista = trim($codigoLista);
        if ($codigoLista === '') {
            return null;
        }

        $id = Listaprecio::query()->where('codigo', $codigoLista)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  list<mixed>  $items
     */
    public function paginarItems(array $items, int $perPage, int $page = 1, string $pageName = 'page'): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return new PaginatorImpl(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => PaginatorImpl::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        );
    }
}
