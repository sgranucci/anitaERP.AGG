<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Support\Stock\ArticuloEstadoCanalSupport;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\FormulaArticuloFactorCosto;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Stock\PrecioListaVigenteSupport;
use App\Support\Ventas\GastronomiaRecetasReporteAsignacion;
use App\Support\Ventas\GastronomiaRecetasReporteFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de fórmulas: costo por última compra, expresado por porción para compararlo
 * con el precio de venta del artículo, y alertas (sin precio, costo en cero, insumo sin costo, nombre duplicado).
 */
final class GastronomiaRecetasReporteService
{
    private const GRAVEDAD = ['Alta' => 3, 'Media' => 2, 'Baja' => 1];

    /** @var array<int, array<string, mixed>> */
    private array $formulas = [];

    /** @var array<int, list<array<string, mixed>>> */
    private array $hijos = [];

    /** @var array<int, float|null> */
    private array $costoInsumo = [];

    /** @var array<int, float> */
    private array $costoFormula = [];

    /** @var array<int, true> */
    private array $enCurso = [];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $vacio = $this->resultadoVacio();
        if (! Schema::hasTable('formula_articulo') || ! Schema::hasTable('formula_articulo_hijo') || ! Schema::hasTable('articulo')) {
            return $vacio;
        }

        $this->cargarFormulas();
        if ($this->formulas === []) {
            return $vacio;
        }

        $this->cargarHijos();
        $articulos = $this->cargarArticulos();
        $this->cargarCostosInsumo($articulos['insumo_ids']);
        $this->costoFormula = [];
        $this->enCurso = [];
        foreach (array_keys($this->formulas) as $formulaId) {
            $this->costoDe((int) $formulaId);
        }

        $preciosVenta = $this->cargarPreciosVenta($articulos['venta_ids']);
        $nombresRubro = $this->nombresRubro();
        $unidades = $this->unidades();

        $recetas = [];
        $lineas = [];
        foreach ($this->formulas as $formulaId => $formula) {
            $asignados = $this->articulosDeFormula((int) $formulaId, $formula, $articulos['por_id'], $articulos['por_formula'], $preciosVenta);
            $decision = GastronomiaRecetasReporteAsignacion::resolver($asignados, [
                'activos' => ! empty($filtros['activos']),
                'inactivos' => ! empty($filtros['inactivos']),
                'sin_asignar' => ! empty($filtros['sin_asignar']),
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'categoria_id' => (int) ($filtros['categoria_id'] ?? 0),
                'subcategoria_id' => (int) ($filtros['subcategoria_id'] ?? 0),
            ]);
            if (! $decision['incluye']) {
                continue;
            }

            $armada = $this->armarReceta((int) $formulaId, $formula, $decision, $nombresRubro, $unidades, $articulos['por_id']);
            $recetas[] = $armada['receta'];
            foreach ($armada['lineas'] as $linea) {
                $lineas[] = $linea;
            }
        }

        $this->marcarNombresDuplicados($recetas);
        $this->cerrarAlertas($recetas, $lineas);

        usort($recetas, static function (array $a, array $b): int {
            $porCategoria = strcmp((string) $a['categoria'], (string) $b['categoria']);
            if ($porCategoria !== 0) {
                return $porCategoria;
            }
            $porSub = strcmp((string) $a['subcategoria'], (string) $b['subcategoria']);
            if ($porSub !== 0) {
                return $porSub;
            }
            $porCodigo = ((int) $a['codigo_num']) <=> ((int) $b['codigo_num']);

            return $porCodigo !== 0 ? $porCodigo : strcmp((string) $a['nombre'], (string) $b['nombre']);
        });

        $ordenReceta = [];
        foreach ($recetas as $indice => $receta) {
            $ordenReceta[(int) $receta['formula_id']] = $indice;
        }
        usort($lineas, static function (array $a, array $b) use ($ordenReceta): int {
            return ($ordenReceta[(int) $a['formula_id']] ?? 0) <=> ($ordenReceta[(int) $b['formula_id']] ?? 0);
        });

        $lineasPorFormula = [];
        foreach ($lineas as $linea) {
            $lineasPorFormula[(int) $linea['formula_id']][] = $linea;
        }
        $detalle = [];
        foreach ($recetas as $receta) {
            $grupo = $lineasPorFormula[(int) $receta['formula_id']] ?? [];
            $sumCant = 0.0;
            $sumCosto = 0.0;
            $primera = true;
            foreach ($grupo as $linea) {
                $detalle[] = [
                    'receta' => $primera ? $receta['nombre'] : '',
                    'codigo' => $primera ? $receta['codigo'] : '',
                    'tipo' => $linea['tipo'],
                    'codigo_linea' => $linea['codigo_linea'],
                    'descripcion' => $linea['descripcion'],
                    'um' => $linea['um'],
                    'cantidad' => $linea['cantidad'],
                    'factor' => $linea['factor'],
                    'cant_desp' => $linea['cant_desp'],
                    'costo_unitario' => $linea['costo_unitario'],
                    'costo_linea' => $linea['costo_linea'],
                    'entra_costo' => $linea['entra_costo'],
                    'precio' => $primera ? $receta['precio'] : null,
                    'porciones' => $primera ? $receta['porciones'] : null,
                    'costo_porcion' => $primera ? $receta['costo'] : null,
                    'es_total' => false,
                ];
                if ($linea['entra_costo'] === 'Sí') {
                    $sumCant += (float) $linea['cant_desp'];
                    $sumCosto += (float) $linea['costo_linea'];
                }
                $primera = false;
            }
            $detalle[] = [
                'receta' => 'Total '.$receta['nombre'],
                'codigo' => $receta['codigo'],
                'tipo' => '',
                'codigo_linea' => '',
                'descripcion' => '',
                'um' => '',
                'cantidad' => null,
                'factor' => null,
                'cant_desp' => $sumCant,
                'costo_unitario' => null,
                'costo_linea' => $sumCosto,
                'entra_costo' => '',
                'precio' => $receta['precio'],
                'porciones' => $receta['porciones'],
                'costo_porcion' => $receta['costo'],
                'es_total' => true,
            ];
        }

        $resumen = [];
        $porCategoria = [];
        $alertas = [];
        $totales = ['recetas' => count($recetas), 'alta' => 0, 'media' => 0, 'baja' => 0];
        foreach ($recetas as $receta) {
            $cat = (string) $receta['categoria'];
            $sub = (string) $receta['subcategoria'];
            $clave = $cat.'|'.$sub;
            if (! isset($resumen[$clave])) {
                $resumen[$clave] = ['categoria' => $cat, 'subcategoria' => $sub, 'recetas' => 0, 'alta' => 0, 'media' => 0];
            }
            $resumen[$clave]['recetas']++;
            if (! isset($porCategoria[$cat])) {
                $porCategoria[$cat] = 0;
            }
            $porCategoria[$cat]++;
            $gravedad = (string) ($receta['alerta'] ?? '');
            if ($gravedad === 'Alta') {
                $resumen[$clave]['alta']++;
                $totales['alta']++;
            } elseif ($gravedad === 'Media') {
                $resumen[$clave]['media']++;
                $totales['media']++;
            } elseif ($gravedad === 'Baja') {
                $totales['baja']++;
            }
            foreach ($receta['alertas'] as $alerta) {
                $alertas[] = [
                    'codigo' => $receta['codigo'],
                    'receta' => $receta['nombre'],
                    'categoria' => $cat,
                    'gravedad' => $alerta['gravedad'],
                    'tipo' => $alerta['tipo'],
                    'detalle' => $alerta['detalle'],
                ];
            }
        }

        usort($alertas, static function (array $a, array $b): int {
            $ga = self::GRAVEDAD[$a['gravedad']] ?? 0;
            $gb = self::GRAVEDAD[$b['gravedad']] ?? 0;
            if ($ga !== $gb) {
                return $gb <=> $ga;
            }

            return strcmp((string) $a['receta'], (string) $b['receta']);
        });

        ksort($porCategoria);
        $resumenFilas = array_values($resumen);
        usort($resumenFilas, static function (array $a, array $b): int {
            $porCategoria = strcmp($a['categoria'], $b['categoria']);

            return $porCategoria !== 0 ? $porCategoria : strcmp($a['subcategoria'], $b['subcategoria']);
        });

        return [
            'recetas' => $recetas,
            'lineas' => $lineas,
            'detalle' => $detalle,
            'resumen' => $resumenFilas,
            'por_categoria' => $porCategoria,
            'alertas' => $alertas,
            'totales' => $totales,
            'inclusion' => GastronomiaRecetasReporteFiltros::textoInclusion($filtros),
            'listaprecio_id' => (int) config('precio.listaprecio_default_id', 1),
        ];
    }

    /**
     * @return array{categorias:list<object>,subcategorias:list<object>}
     */
    public function opcionesFiltro(): array
    {
        $categorias = Schema::hasTable('categoria')
            ? DB::table('categoria')->orderBy('nombre')->get(['id', 'nombre'])->all()
            : [];
        $subcategorias = Schema::hasTable('subcategoria')
            ? DB::table('subcategoria')->orderBy('nombre')->get(['id', 'nombre'])->all()
            : [];

        return [
            'categorias' => $categorias,
            'subcategorias' => $subcategorias,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function paginar(array $items, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = count($items);

        return new PaginatorImpl(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            [
                'path' => PaginatorImpl::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoVacio(): array
    {
        return [
            'recetas' => [],
            'lineas' => [],
            'detalle' => [],
            'resumen' => [],
            'por_categoria' => [],
            'alertas' => [],
            'totales' => ['recetas' => 0, 'alta' => 0, 'media' => 0, 'baja' => 0],
            'inclusion' => '',
            'listaprecio_id' => (int) config('precio.listaprecio_default_id', 1),
        ];
    }

    private function cargarFormulas(): void
    {
        $columnas = ['id', 'articulo_id', 'codigo', 'detalle', 'cantidadunidad', 'estado'];
        if (Schema::hasColumn('formula_articulo', 'anita_stkcm_formula')) {
            $columnas[] = 'anita_stkcm_formula';
        }

        $this->formulas = [];
        foreach (DB::table('formula_articulo')->select($columnas)->orderBy('id')->get() as $fila) {
            $this->formulas[(int) $fila->id] = [
                'id' => (int) $fila->id,
                'articulo_id' => (int) ($fila->articulo_id ?? 0),
                'codigo' => trim((string) ($fila->codigo ?? '')),
                'anita' => (int) ($fila->anita_stkcm_formula ?? 0),
                'detalle' => trim((string) ($fila->detalle ?? '')),
                'cantidadunidad' => (float) ($fila->cantidadunidad ?? 1),
                'estado' => trim((string) ($fila->estado ?? '')),
            ];
        }
    }

    private function cargarHijos(): void
    {
        $columnas = ['id', 'formula_articulo_id', 'articulo_id', 'cantidad', 'factorcosto', 'formula_hija_id', 'esopcional'];
        $tieneOrden = Schema::hasColumn('formula_articulo_hijo', 'ordenopcional');
        if ($tieneOrden) {
            $columnas[] = 'ordenopcional';
        }

        $this->hijos = [];
        foreach (DB::table('formula_articulo_hijo')->select($columnas)->orderBy('id')->get() as $fila) {
            $this->hijos[(int) $fila->formula_articulo_id][] = [
                'id' => (int) $fila->id,
                'articulo_id' => (int) ($fila->articulo_id ?? 0),
                'cantidad' => (float) ($fila->cantidad ?? 0),
                'factorcosto' => $fila->factorcosto,
                'formula_hija_id' => (int) ($fila->formula_hija_id ?? 0),
                'esopcional' => (int) ($fila->esopcional ?? 0) === 1,
                'ordenopcional' => $tieneOrden ? (int) ($fila->ordenopcional ?? 0) : 0,
            ];
        }
    }

    /**
     * @return array{por_id:array<int, object>, por_formula:array<int, list<int>>, insumo_ids:list<int>, venta_ids:list<int>}
     */
    private function cargarArticulos(): array
    {
        $ids = [];
        $headerIds = [];
        foreach ($this->formulas as $formula) {
            if ((int) $formula['articulo_id'] > 0) {
                $ids[] = (int) $formula['articulo_id'];
                $headerIds[] = (int) $formula['articulo_id'];
            }
        }
        foreach ($this->hijos as $lineas) {
            foreach ($lineas as $linea) {
                if ((int) $linea['articulo_id'] > 0) {
                    $ids[] = (int) $linea['articulo_id'];
                }
            }
        }

        $formulaIds = array_keys($this->formulas);
        $porFormula = [];
        foreach (array_chunk($formulaIds, 500) as $lote) {
            foreach (DB::table('articulo')->whereIn('formula', $lote)->pluck('formula', 'id') as $articuloId => $formulaId) {
                $articuloId = (int) $articuloId;
                $formulaId = (int) $formulaId;
                if ($articuloId <= 0 || $formulaId <= 0) {
                    continue;
                }
                $ids[] = $articuloId;
                $porFormula[$formulaId][] = $articuloId;
            }
        }

        $columnas = ['id', 'sku', 'descripcion', 'estado', 'empresa_id', 'formula', 'categoria_id', 'subcategoria_id', 'unidadmedida_id'];
        $tieneCanales = ArticuloEstadoCanalSupport::columnasEstadoDisponibles();
        if ($tieneCanales) {
            $columnas[] = 'estado_fabrica';
            $columnas[] = 'estado_local';
        }

        $porId = [];
        foreach (array_chunk(array_values(array_unique($ids)), 500) as $lote) {
            foreach (DB::table('articulo')->whereIn('id', $lote)->get($columnas) as $articulo) {
                $porId[(int) $articulo->id] = $articulo;
            }
        }

        $insumoIds = [];
        foreach ($this->hijos as $lineas) {
            foreach ($lineas as $linea) {
                if ((int) $linea['articulo_id'] > 0 && (int) $linea['formula_hija_id'] <= 0) {
                    $insumoIds[] = (int) $linea['articulo_id'];
                }
            }
        }

        $ventaIds = $headerIds;
        foreach ($porFormula as $lista) {
            foreach ($lista as $articuloId) {
                $ventaIds[] = $articuloId;
            }
        }

        return [
            'por_id' => $porId,
            'por_formula' => $porFormula,
            'insumo_ids' => array_values(array_unique($insumoIds)),
            'venta_ids' => array_values(array_unique(array_filter($ventaIds))),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function cargarCostosInsumo(array $ids): void
    {
        $this->costoInsumo = [];
        foreach (array_chunk($ids, 300) as $lote) {
            $resueltos = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($lote, null, true);
            foreach ($lote as $id) {
                $precio = $resueltos[$id]['precio'] ?? null;
                $this->costoInsumo[$id] = $precio === null ? null : (float) $precio;
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, float>
     */
    private function cargarPreciosVenta(array $ids): array
    {
        $listaId = (int) config('precio.listaprecio_default_id', 1);
        $precios = [];
        if ($listaId < 1 || $ids === []) {
            return $precios;
        }

        foreach (array_chunk($ids, 500) as $lote) {
            foreach (PrecioListaVigenteSupport::vigentesPorArticulos($lote, $listaId) as $articuloId => $dato) {
                $precios[(int) $articuloId] = (float) ($dato['precio'] ?? 0);
            }
        }

        return $precios;
    }

    /**
     * @return array{categorias:array<int, string>, subcategorias:array<int, string>}
     */
    private function nombresRubro(): array
    {
        $categorias = [];
        $subcategorias = [];
        if (Schema::hasTable('categoria')) {
            foreach (DB::table('categoria')->get(['id', 'nombre']) as $fila) {
                $categorias[(int) $fila->id] = trim((string) $fila->nombre);
            }
        }
        if (Schema::hasTable('subcategoria')) {
            foreach (DB::table('subcategoria')->get(['id', 'nombre']) as $fila) {
                $subcategorias[(int) $fila->id] = trim((string) $fila->nombre);
            }
        }

        return ['categorias' => $categorias, 'subcategorias' => $subcategorias];
    }

    /**
     * @return array<int, string>
     */
    private function unidades(): array
    {
        if (! Schema::hasTable('unidadmedida')) {
            return [];
        }

        $mapa = [];
        foreach (DB::table('unidadmedida')->get(['id', 'abreviatura', 'nombre']) as $fila) {
            $abrev = trim((string) ($fila->abreviatura ?? ''));
            $mapa[(int) $fila->id] = $abrev !== '' ? $abrev : trim((string) ($fila->nombre ?? ''));
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $formula
     * @param  array<int, object>  $porId
     * @param  array<int, list<int>>  $porFormula
     * @param  array<int, float>  $preciosVenta
     * @return list<array{id:int,estado:string,empresa_id:int,precio:float,descripcion:string,sku:string,categoria_id:int,subcategoria_id:int}>
     */
    private function articulosDeFormula(int $formulaId, array $formula, array $porId, array $porFormula, array $preciosVenta): array
    {
        $ids = $porFormula[$formulaId] ?? [];
        if ((int) $formula['articulo_id'] > 0) {
            $ids[] = (int) $formula['articulo_id'];
        }

        $ferli = ArticuloEstadoCanalSupport::uiFerliActiva();
        $salida = [];
        foreach (array_unique($ids) as $articuloId) {
            $articulo = $porId[(int) $articuloId] ?? null;
            if ($articulo === null) {
                continue;
            }
            $estado = $ferli
                ? ArticuloEstadoCanalSupport::derivarEstadoLegacy(
                    (string) ($articulo->estado_fabrica ?? ''),
                    (string) ($articulo->estado_local ?? ''),
                )
                : ArticuloEstadoCanalSupport::normalizarEstado((string) ($articulo->estado ?? ''));
            $salida[] = [
                'id' => (int) $articulo->id,
                'estado' => $estado,
                'empresa_id' => (int) ($articulo->empresa_id ?? 0),
                'precio' => (float) ($preciosVenta[(int) $articulo->id] ?? 0),
                'descripcion' => trim((string) ($articulo->descripcion ?? '')),
                'sku' => trim((string) ($articulo->sku ?? '')),
                'categoria_id' => (int) ($articulo->categoria_id ?? 0),
                'subcategoria_id' => (int) ($articulo->subcategoria_id ?? 0),
            ];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $formula
     * @param  array{incluye:bool,estado:string,articulo:array<string,mixed>|null}  $decision
     * @param  array{categorias:array<int, string>, subcategorias:array<int, string>}  $nombresRubro
     * @param  array<int, string>  $unidades
     * @param  array<int, object>  $porId
     * @return array{receta:array<string, mixed>, lineas:list<array<string, mixed>>}
     */
    private function armarReceta(int $formulaId, array $formula, array $decision, array $nombresRubro, array $unidades, array $porId): array
    {
        $articulo = $decision['articulo'];
        $nombre = trim((string) ($articulo['descripcion'] ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) $formula['detalle']);
        }
        if ($nombre === '') {
            $nombre = '(sin nombre)';
        }

        $categoriaId = (int) ($articulo['categoria_id'] ?? 0);
        $subcategoriaId = (int) ($articulo['subcategoria_id'] ?? 0);
        $categoria = $nombresRubro['categorias'][$categoriaId] ?? ($decision['estado'] === GastronomiaRecetasReporteAsignacion::ESTADO_SIN_ASIGNAR ? 'Sin categoría' : 'Sin categoría');
        $subcategoria = $nombresRubro['subcategorias'][$subcategoriaId] ?? 'Sin subcategoría';
        $porciones = (float) $formula['cantidadunidad'];
        if ($porciones <= 0) {
            $porciones = 1.0;
        }
        $costoLote = (float) ($this->costoFormula[$formulaId] ?? 0);
        $costo = $costoLote / $porciones;
        $precio = $articulo === null ? 0.0 : (float) ($articulo['precio'] ?? 0);
        $codigoNum = (int) $formula['anita'];
        if ($codigoNum <= 0 && $formula['codigo'] !== '' && ctype_digit((string) $formula['codigo'])) {
            $codigoNum = (int) $formula['codigo'];
        }
        $codigo = $codigoNum > 0 ? (string) $codigoNum : (string) $formulaId;

        $entran = $this->idsQueEntranCosto($formulaId);
        $lineas = [];
        $sinCosto = 0;
        foreach ($this->hijos[$formulaId] ?? [] as $hijo) {
            $factor = FormulaArticuloFactorCosto::efectivo($hijo['factorcosto']);
            $cantDesp = (float) $hijo['cantidad'] * $factor;
            $entra = isset($entran[(int) $hijo['id']]);
            $hijoId = (int) $hijo['formula_hija_id'];
            $articuloId = (int) $hijo['articulo_id'];
            if ($hijoId > 0) {
                $tipo = 'Subfórmula';
                $descripcion = $this->nombreFormula($hijoId, $porId);
                $codigoLinea = $this->codigoFormula($hijoId);
                $um = '';
                $articuloHijo = $porId[(int) ($this->formulas[$hijoId]['articulo_id'] ?? 0)] ?? null;
                if ($articuloHijo !== null) {
                    $um = $unidades[(int) ($articuloHijo->unidadmedida_id ?? 0)] ?? '';
                }
                $porcionesHija = (float) ($this->formulas[$hijoId]['cantidadunidad'] ?? 1);
                if ($porcionesHija <= 0.0001) {
                    $porcionesHija = 1.0;
                }
                $unitario = ((float) ($this->costoFormula[$hijoId] ?? 0)) / $porcionesHija;
                $costoLinea = $unitario * $cantDesp;
                $tieneCosto = $unitario > 0;
            } else {
                $tipo = 'Insumo';
                $art = $porId[$articuloId] ?? null;
                $descripcion = trim((string) ($art->descripcion ?? ''));
                if ($descripcion === '') {
                    $descripcion = $articuloId > 0 ? 'Artículo #'.$articuloId : 'Línea sin artículo';
                }
                $codigoLinea = trim((string) ($art->sku ?? ''));
                $um = $art !== null ? ($unidades[(int) ($art->unidadmedida_id ?? 0)] ?? '') : '';
                $precioInsumo = $this->costoInsumo[$articuloId] ?? null;
                $tieneCosto = $precioInsumo !== null && $precioInsumo > 0;
                $unitario = $tieneCosto ? (float) $precioInsumo : 0.0;
                $costoLinea = $unitario * $cantDesp;
            }

            if ($entra && ! $tieneCosto) {
                $sinCosto++;
            }

            $lineas[] = [
                'formula_id' => $formulaId,
                'receta' => $nombre,
                'codigo_receta' => $codigo,
                'tipo' => $tipo,
                'codigo_linea' => $codigoLinea,
                'descripcion' => $descripcion,
                'um' => $um,
                'cantidad' => (float) $hijo['cantidad'],
                'factor' => $factor,
                'cant_desp' => $cantDesp,
                'costo_unitario' => $unitario,
                'costo_linea' => $costoLinea,
                'entra_costo' => $entra ? 'Sí' : 'No',
                'opcional' => $hijo['esopcional'] ? 'Sí' : 'No',
                'categoria' => $categoria,
                'subcategoria' => $subcategoria,
                'estado_articulo' => $decision['estado'],
                'precio' => $precio,
                'porciones' => $porciones,
                'costo_receta' => $costo,
                'costo_lote' => $costoLote,
            ];
        }

        $alertas = [];
        if ($nombre === '(sin nombre)') {
            $alertas[] = $this->alerta(
                'Alta',
                'Fórmula sin nombre',
                $articulo === null
                    ? 'La fórmula no tiene artículo de venta ni detalle.'
                    : 'El artículo de venta no tiene descripción y la fórmula no tiene detalle.',
            );
        }
        if ($lineas === []) {
            $alertas[] = $this->alerta('Alta', 'Fórmula incompleta', 'La fórmula no tiene líneas.');
        } elseif ($sinCosto > 0) {
            $alertas[] = $this->alerta(
                'Alta',
                'Insumo sin costo',
                $sinCosto === 1
                    ? 'Una línea que entra al costo no tiene última compra.'
                    : $sinCosto.' líneas que entran al costo no tienen última compra.',
            );
        }
        if ($costo <= 0 && $lineas !== [] && $sinCosto === 0) {
            $alertas[] = $this->alerta('Alta', 'Costo en cero', 'El costo de la fórmula es 0.');
        }
        if ($articulo !== null && $precio <= 0) {
            $alertas[] = $this->alerta('Baja', 'Sin precio de venta', 'El artículo de venta asignado no tiene precio vigente en la lista.');
        }

        $receta = [
            'formula_id' => $formulaId,
            'codigo' => $codigo,
            'codigo_num' => $codigoNum > 0 ? $codigoNum : $formulaId,
            'nombre' => $nombre,
            'sku' => trim((string) ($articulo['sku'] ?? '')),
            'articulo_id' => (int) ($articulo['id'] ?? 0),
            'estado_articulo' => $decision['estado'],
            'categoria' => $categoria,
            'subcategoria' => $subcategoria,
            'porciones' => $porciones,
            'costo' => $costo,
            'costo_lote' => $costoLote,
            'precio' => $precio,
            'ratio' => ($costo > 0 && $precio > 0) ? $precio / $costo : null,
            'lineas' => count($lineas),
            'alerta' => null,
            'cant_alertas' => 0,
            'alertas' => $alertas,
        ];

        return ['receta' => $receta, 'lineas' => $lineas];
    }

    /**
     * @param  array<int, object>  $porId
     */
    private function nombreFormula(int $formulaId, array $porId): string
    {
        $formula = $this->formulas[$formulaId] ?? null;
        if ($formula === null) {
            return 'Subfórmula #'.$formulaId;
        }
        $articulo = $porId[(int) $formula['articulo_id']] ?? null;
        $nombre = trim((string) ($articulo->descripcion ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) $formula['detalle']);
        }
        if ($nombre === '') {
            $nombre = 'Fórmula '.$this->codigoFormula($formulaId);
        }

        return $nombre;
    }

    private function codigoFormula(int $formulaId): string
    {
        $formula = $this->formulas[$formulaId] ?? null;
        if ($formula === null) {
            return (string) $formulaId;
        }
        $codigoNum = (int) $formula['anita'];
        if ($codigoNum <= 0 && $formula['codigo'] !== '' && ctype_digit((string) $formula['codigo'])) {
            $codigoNum = (int) $formula['codigo'];
        }

        return $codigoNum > 0 ? (string) $codigoNum : (string) $formulaId;
    }

    /**
     * @param  list<array<string, mixed>>  $recetas
     */
    private function marcarNombresDuplicados(array &$recetas): void
    {
        $porNombre = [];
        foreach ($recetas as $indice => $receta) {
            $clave = mb_strtoupper(trim((string) $receta['nombre']));
            if ($clave === '' || $clave === '(SIN NOMBRE)') {
                continue;
            }
            $porNombre[$clave][] = $indice;
        }

        foreach ($porNombre as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            foreach ($indices as $indice) {
                $recetas[$indice]['alertas'][] = $this->alerta(
                    'Media',
                    'Nombre duplicado',
                    'Hay '.count($indices).' fórmulas con el mismo nombre en este filtro.',
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $recetas
     * @param  list<array<string, mixed>>  $lineas
     */
    private function cerrarAlertas(array &$recetas, array &$lineas): void
    {
        $porFormula = [];
        foreach ($recetas as $indice => $receta) {
            $mejor = null;
            $rank = 0;
            foreach ($receta['alertas'] as $alerta) {
                $actual = self::GRAVEDAD[$alerta['gravedad']] ?? 0;
                if ($actual > $rank) {
                    $rank = $actual;
                    $mejor = $alerta['gravedad'];
                }
            }
            $recetas[$indice]['alerta'] = $mejor;
            $recetas[$indice]['cant_alertas'] = count($receta['alertas']);
            $porFormula[(int) $receta['formula_id']] = $mejor;
        }

        foreach ($lineas as $indice => $linea) {
            $lineas[$indice]['alerta'] = $porFormula[(int) $linea['formula_id']] ?? null;
        }
    }

    /**
     * @return array<int, true>
     */
    private function idsQueEntranCosto(int $formulaId): array
    {
        $fijas = [];
        $opcionales = [];
        foreach ($this->hijos[$formulaId] ?? [] as $hijo) {
            if ($hijo['esopcional'] && FormulaArticuloGastronomia::opcionalesHabilitados()) {
                $opcionales[(int) $hijo['ordenopcional']][] = $hijo;
            } elseif (! $hijo['esopcional']) {
                $fijas[] = $hijo;
            }
        }

        $ids = [];
        foreach ($fijas as $hijo) {
            $ids[(int) $hijo['id']] = true;
        }
        ksort($opcionales);
        foreach ($opcionales as $grupo) {
            usort($grupo, static fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
            if ($grupo !== []) {
                $ids[(int) $grupo[0]['id']] = true;
            }
        }

        return $ids;
    }

    private function costoDe(int $formulaId): float
    {
        if (isset($this->costoFormula[$formulaId])) {
            return $this->costoFormula[$formulaId];
        }
        if (isset($this->enCurso[$formulaId]) || ! isset($this->formulas[$formulaId])) {
            return 0.0;
        }

        $this->enCurso[$formulaId] = true;
        $total = 0.0;
        $entran = $this->idsQueEntranCosto($formulaId);
        foreach ($this->hijos[$formulaId] ?? [] as $hijo) {
            if (! isset($entran[(int) $hijo['id']])) {
                continue;
            }
            $cant = (float) $hijo['cantidad'] * FormulaArticuloFactorCosto::efectivo($hijo['factorcosto']);
            $hijaId = (int) $hijo['formula_hija_id'];
            if ($hijaId > 0) {
                $porciones = (float) ($this->formulas[$hijaId]['cantidadunidad'] ?? 1);
                if ($porciones <= 0.0001) {
                    $porciones = 1.0;
                }
                $total += ($this->costoDe($hijaId) / $porciones) * $cant;
                continue;
            }
            $precio = $this->costoInsumo[(int) $hijo['articulo_id']] ?? null;
            if ($precio !== null) {
                $total += $cant * $precio;
            }
        }
        unset($this->enCurso[$formulaId]);
        $this->costoFormula[$formulaId] = $total;

        return $total;
    }

    /**
     * @return array{gravedad:string,tipo:string,detalle:string}
     */
    private function alerta(string $gravedad, string $tipo, string $detalle): array
    {
        return ['gravedad' => $gravedad, 'tipo' => $tipo, 'detalle' => $detalle];
    }

    private function numero(float $valor): string
    {
        $texto = rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');

        return $texto === '' ? '0' : $texto;
    }
}
