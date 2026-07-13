<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ClienteVipGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Queries\Ventas\GastronomiaDescuentoReporteQuery;
use App\Services\Stock\PrecioService;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Ventas\GastronomiaDescuentoReporteClienteSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteCodigoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use App\Support\Ventas\GastronomiaDescuentoReporteMozoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteVipSupport;
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
     *   mozos_sin_datos:list<int>,
     *   vips_sin_datos:list<int>,
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
                $tipoId = (int) ($fila->tipoarticulo_id ?? 0);
                $porClave[$clave]['filas_map'][$articuloId] = [
                    'articulo_id' => $articuloId,
                    'sku' => $fila->sku,
                    'descripcion' => $fila->descripcion,
                    'tipoarticulo_id' => $tipoId > 0 ? $tipoId : null,
                    'tipoarticulo_nombre' => trim((string) ($fila->tipoarticulo_nombre ?? '')),
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
            $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas(
                array_values($item['filas_map']),
            );

            $bloque = [
                'clave' => $item['clave'],
                'tipo_agrupacion' => $item['tipo_agrupacion'],
                'codigo' => $item['codigo'],
                'nombre' => $item['nombre'],
                'filas' => $agrupado['filas'],
                'grupos' => $agrupado['grupos'],
                'totales' => $item['totales'],
            ];

            if (($bloque['totales']['unidades'] ?? 0) <= 0.0001 || $agrupado['filas'] === []) {
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
        $mozosSinDatos = [];
        $vipsSinDatos = [];
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

            $mozosSolicitados = $filtros['mozos_descuento_ids'] ?? [];
            if ($mozosSolicitados !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
                $mozosConDatos = [];
                foreach ($bloques as $bloque) {
                    if (preg_match('/^m_(\d+)$/', (string) $bloque['clave'], $m)) {
                        $mozosConDatos[] = (int) $m[1];
                    }
                }
                $mozosSinDatos = array_values(array_diff($mozosSolicitados, $mozosConDatos));
            }

            $vipsSolicitados = $filtros['vips_descuento_ids'] ?? [];
            if ($vipsSolicitados !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
                $vipsConDatos = [];
                foreach ($bloques as $bloque) {
                    if (preg_match('/^v_(\d+)$/', (string) $bloque['clave'], $m)) {
                        $vipsConDatos[] = (int) $m[1];
                    }
                }
                $vipsSinDatos = array_values(array_diff($vipsSolicitados, $vipsConDatos));
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
            $mozosSinDatos,
            $vipsSinDatos,
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
     * @param  list<int>  $mozosSinDatos
     * @param  list<int>  $vipsSinDatos
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
        array $mozosSinDatos,
        array $vipsSinDatos,
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
            'mozos_sin_datos' => $mozosSinDatos,
            'vips_sin_datos' => $vipsSinDatos,
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

        if (GastronomiaDescuentoReporteFiltros::usaFiltroCodigosDescuentoSecundario($filtros)) {
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

        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);
        $codigos = $filtros['codigos_descuento_resueltos'] ?? [];
        $clienteIds = $filtros['clientes_descuento_ids'] ?? [];
        $mozoIds = $filtros['mozos_descuento_ids'] ?? [];
        $vipIds = $filtros['vips_descuento_ids'] ?? [];

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO
            && $codigos === []) {
            return array_merge($avisos, ['Seleccione al menos un código de descuento o marque Listar todos.']);
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE
            && $clienteIds === []
            && ! GastronomiaDescuentoReporteClienteSupport::tieneRangoCodigo(
                (string) ($filtros['cliente_codigo_desde'] ?? ''),
                (string) ($filtros['cliente_codigo_hasta'] ?? ''),
            )) {
            return array_merge($avisos, ['Seleccione al menos un cliente interno, defina un rango de códigos, o marque Listar todos.']);
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO
            && GastronomiaDescuentoReporteFiltros::mozoRangoSinCoincidencias($filtros)) {
            return array_merge($avisos, ['El rango de mozos no coincide con ningún mozo registrado.']);
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP
            && GastronomiaDescuentoReporteFiltros::vipRangoSinCoincidencias($filtros)) {
            return array_merge($avisos, ['El rango de clientes VIP no coincide con ningún cliente VIP registrado.']);
        }

        if ($codigos !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO) {
            $existentes = GastronomiaDescuentoReporteCodigoSupport::resolverExistentes($codigos);
            $faltantes = array_values(array_diff($codigos, $existentes));
            if ($faltantes !== []) {
                $avisos[] = 'Códigos no registrados en descuentos gastronomía: '.implode(', ', $faltantes).'.';
            }
        }

        if ($clienteIds !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $existentesIds = Cliente::query()->whereIn('id', $clienteIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $faltantesIds = array_values(array_diff($clienteIds, $existentesIds));
            if ($faltantesIds !== []) {
                $avisos[] = 'Clientes internos no registrados: ID '.implode(', ', $faltantesIds).'.';
            }
        }

        if ($mozoIds !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $empresaId = (int) ($filtros['empresa_id'] ?? 0);
            $query = MozoGastronomia::query()->whereIn('id', $mozoIds);
            if ($empresaId > 0) {
                $query->where('empresa_id', $empresaId);
            }
            $existentesIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            $faltantesIds = array_values(array_diff($mozoIds, $existentesIds));
            if ($faltantesIds !== []) {
                $avisos[] = 'Mozos no registrados: ID '.implode(', ', $faltantesIds).'.';
            }
        }

        if ($vipIds !== [] && $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $empresaId = (int) ($filtros['empresa_id'] ?? 0);
            $query = ClienteVipGastronomia::query()->whereIn('id', $vipIds);
            if ($empresaId > 0) {
                $query->where('empresa_id', $empresaId);
            }
            $existentesIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            $faltantesIds = array_values(array_diff($vipIds, $existentesIds));
            if ($faltantesIds !== []) {
                $avisos[] = 'Clientes VIP no registrados: ID '.implode(', ', $faltantesIds).'.';
            }
        }

        return $avisos;
    }

    /**
     * Códigos del rango cliente interno que no existen en maestro (huecos esperables en rangos amplios).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public function codigosClienteRangoSinRegistro(array $filtros): array
    {
        $codigoDesde = (string) ($filtros['cliente_codigo_desde'] ?? '');
        $codigoHasta = (string) ($filtros['cliente_codigo_hasta'] ?? '');
        if (! GastronomiaDescuentoReporteClienteSupport::tieneRangoCodigo($codigoDesde, $codigoHasta)) {
            return [];
        }

        $tokenRango = trim($codigoDesde) !== '' && trim($codigoHasta) !== ''
            ? trim($codigoDesde).'/'.trim($codigoHasta)
            : (trim($codigoDesde) !== '' ? trim($codigoDesde) : trim($codigoHasta));
        $codigosRango = GastronomiaDescuentoReporteCodigoSupport::expandir($tokenRango);

        return GastronomiaDescuentoReporteClienteSupport::codigosSinClienteRegistrado($codigosRango);
    }

    /**
     * Códigos del rango mozo que no existen en maestro (huecos esperables en rangos amplios).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public function codigosMozoRangoSinRegistro(array $filtros): array
    {
        $codigoDesde = (string) ($filtros['mozo_codigo_desde'] ?? '');
        $codigoHasta = (string) ($filtros['mozo_codigo_hasta'] ?? '');
        if (! GastronomiaDescuentoReporteMozoSupport::tieneRangoCodigo($codigoDesde, $codigoHasta)) {
            return [];
        }

        $tokenRango = trim($codigoDesde) !== '' && trim($codigoHasta) !== ''
            ? trim($codigoDesde).'/'.trim($codigoHasta)
            : (trim($codigoDesde) !== '' ? trim($codigoDesde) : trim($codigoHasta));
        $codigosRango = GastronomiaDescuentoReporteCodigoSupport::expandir($tokenRango);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        return GastronomiaDescuentoReporteMozoSupport::codigosSinMozoRegistrado($codigosRango, $empresaId);
    }

    /**
     * Códigos del rango VIP que no existen en maestro (huecos esperables en rangos amplios).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public function codigosVipRangoSinRegistro(array $filtros): array
    {
        $codigoDesde = (string) ($filtros['vip_codigo_desde'] ?? '');
        $codigoHasta = (string) ($filtros['vip_codigo_hasta'] ?? '');
        if (! GastronomiaDescuentoReporteVipSupport::tieneRangoCodigo($codigoDesde, $codigoHasta)) {
            return [];
        }

        $tokenRango = trim($codigoDesde) !== '' && trim($codigoHasta) !== ''
            ? trim($codigoDesde).'/'.trim($codigoHasta)
            : (trim($codigoDesde) !== '' ? trim($codigoDesde) : trim($codigoHasta));
        $codigosRango = GastronomiaDescuentoReporteCodigoSupport::expandir($tokenRango);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        return GastronomiaDescuentoReporteVipSupport::codigosSinVipRegistrado($codigosRango, $empresaId);
    }

    /**
     * @param  object  $fila
     * @return array{clave:string,tipo_agrupacion:string,codigo:string,nombre:string}
     */
    private function metaAgrupacion(object $fila, string $agruparPor): array
    {
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $vipId = (int) ($fila->vip_id ?? 0);

            return [
                'clave' => 'v_'.$vipId,
                'tipo_agrupacion' => GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP,
                'codigo' => $vipId > 0 ? ($fila->vip_codigo ?: (string) $vipId) : '—',
                'nombre' => $vipId > 0 ? $fila->vip_nombre : 'Sin cliente VIP',
            ];
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $mozoId = (int) ($fila->mozo_id ?? 0);

            return [
                'clave' => 'm_'.$mozoId,
                'tipo_agrupacion' => GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO,
                'codigo' => $mozoId > 0 ? ($fila->mozo_codigo ?: (string) $mozoId) : '—',
                'nombre' => $mozoId > 0 ? $fila->mozo_nombre : 'Sin mozo',
            ];
        }

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
        if (! empty($filtros['listar_todos'])
            || GastronomiaDescuentoReporteFiltros::mozoAlcanceImplicitoTodos($filtros)
            || GastronomiaDescuentoReporteFiltros::vipAlcanceImplicitoTodos($filtros)) {
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

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            foreach ($filtros['mozos_descuento_ids'] ?? [] as $mozoId) {
                $clave = 'm_'.(int) $mozoId;
                if (isset($porClave[$clave])) {
                    $orden[] = $clave;
                }
            }

            return $orden;
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            foreach ($filtros['vips_descuento_ids'] ?? [] as $vipId) {
                $clave = 'v_'.(int) $vipId;
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
                    $tipoId = (int) ($fila['tipoarticulo_id'] ?? 0);
                    $articulos[$articuloId] = [
                        'articulo_id' => $articuloId,
                        'sku' => $fila['sku'] ?? '',
                        'descripcion' => $fila['descripcion'] ?? '',
                        'tipoarticulo_id' => $tipoId > 0 ? $tipoId : null,
                        'tipoarticulo_nombre' => trim((string) ($fila['tipoarticulo_nombre'] ?? '')),
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

        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas(array_values($articulos));

        return [
            'columnas' => $columnas,
            'filas' => $agrupado['filas'],
            'grupos' => $agrupado['grupos'],
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
