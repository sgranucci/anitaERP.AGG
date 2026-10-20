<?php

namespace App\Services\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\SqlDialectSupport;
use App\Support\Stock\ControlInventarioItemsClasificacionSupport as Clasif;
use App\Support\Stock\ControlInventarioItemsListadoFiltros;
use App\Support\Stock\ExistenciasDepositoFiltroDepositosSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ControlInventarioItemsReporteService
{
    private const TOLERANCIA = 0.0001;

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: Collection<int, array<string, mixed>>|LengthAwarePaginator,
     *     totales: array<string, mixed>,
     *     avisos: list<string>,
     *     abreviaturas_otros: list<string>
     * }
     */
    public function consultar(array $filtros, bool $paginar = true, int $porPagina = 25): array
    {
        [$fechaDesde, $fechaHasta] = $this->ventanaFechas($filtros);
        $depositos = $this->depositosAutorizados($filtros);
        $depositoIds = $depositos->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($depositoIds === []) {
            return $this->vacio($paginar, $porPagina);
        }

        $movimientosPeriodo = $this->agregarMovimientosPeriodo($filtros, $depositoIds, $fechaDesde, $fechaHasta);
        $abreviaturasOtros = Clasif::abreviaturasEnOtros(
            $movimientosPeriodo->pluck('abreviatura')->filter()->map(fn ($a) => (string) $a)->unique()->values()->all()
        );

        $porArticuloPeriodo = $this->pivotBucketsPorArticulo($movimientosPeriodo);
        $articuloIds = array_keys($porArticuloPeriodo);

        if (! ($filtros['solo_con_movimiento'] ?? true)) {
            $conSaldo = $this->articuloIdsConSaldoHasta($filtros, $depositoIds, $fechaHasta);
            $articuloIds = array_values(array_unique(array_merge($articuloIds, $conSaldo)));
        }

        if ($articuloIds === []) {
            return [
                'filas' => $paginar ? new LengthAwarePaginator([], 0, $porPagina) : collect(),
                'totales' => $this->totalesVacios(),
                'avisos' => $this->avisosClasificacion($abreviaturasOtros, 0),
                'abreviaturas_otros' => $abreviaturasOtros,
            ];
        }

        sort($articuloIds);

        $iniciales = $this->saldosHasta($articuloIds, $depositoIds, $this->diaAnterior($fechaDesde));
        $finales = $this->saldosHasta($articuloIds, $depositoIds, $fechaHasta);

        $filasCompletas = [];
        $noCierran = 0;
        foreach ($articuloIds as $articuloId) {
            $buckets = $porArticuloPeriodo[$articuloId] ?? $this->bucketsCero();
            $inicial = (float) ($iniciales[$articuloId] ?? 0);
            $final = (float) ($finales[$articuloId] ?? 0);
            $calculado = $inicial
                + $buckets[Clasif::BUCKET_RECEPCIONES]
                + $buckets[Clasif::BUCKET_DEVOLUCIONES]
                + $buckets[Clasif::BUCKET_AJUSTES]
                + $buckets[Clasif::BUCKET_CONSUMOS]
                + $buckets[Clasif::BUCKET_OTROS];
            $diferencia = $final - $calculado;
            $cierra = abs($diferencia) <= self::TOLERANCIA;
            if (! $cierra) {
                $noCierran++;
            }
            $filasCompletas[$articuloId] = [
                'articulo_id' => $articuloId,
                'existencia_inicial' => $inicial,
                'recepciones' => $buckets[Clasif::BUCKET_RECEPCIONES],
                'devoluciones' => $buckets[Clasif::BUCKET_DEVOLUCIONES],
                'ajustes' => $buckets[Clasif::BUCKET_AJUSTES],
                'consumos' => $buckets[Clasif::BUCKET_CONSUMOS],
                'otros' => $buckets[Clasif::BUCKET_OTROS],
                'existencia_final' => $final,
                'existencia_calculada' => $calculado,
                'diferencia' => $cierra ? 0.0 : $diferencia,
                'cierra' => $cierra,
            ];
        }

        $totales = $this->sumarTotales($filasCompletas);
        $totales['filas_no_cierran'] = $noCierran;

        if ($paginar) {
            $page = max(1, (int) request()->input('page', 1));
            $sliceIds = array_slice($articuloIds, ($page - 1) * $porPagina, $porPagina);
            $meta = $this->metaArticulos($sliceIds);
            $filasPagina = collect($sliceIds)->map(fn (int $id) => $this->mapFila($filasCompletas[$id], $meta[$id] ?? null));
            $paginator = new LengthAwarePaginator(
                $filasPagina,
                count($articuloIds),
                $porPagina,
                $page,
                [
                    'path' => request()->url(),
                    'query' => ControlInventarioItemsListadoFiltros::paraQueryString($filtros),
                ]
            );

            return [
                'filas' => $paginator,
                'totales' => $totales,
                'avisos' => $this->avisosClasificacion($abreviaturasOtros, $noCierran),
                'abreviaturas_otros' => $abreviaturasOtros,
            ];
        }

        $meta = $this->metaArticulos($articuloIds);
        $filas = collect($articuloIds)->map(fn (int $id) => $this->mapFila($filasCompletas[$id], $meta[$id] ?? null));

        return [
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $this->avisosClasificacion($abreviaturasOtros, $noCierran),
            'abreviaturas_otros' => $abreviaturasOtros,
        ];
    }

    public function subtituloFiltros(array $filtros): string
    {
        [$fechaDesde, $fechaHasta] = $this->ventanaFechas($filtros);
        $partes = [];

        if (! empty($filtros['empresa_id'])) {
            $empresa = Empresa::query()->find((int) $filtros['empresa_id']);
            if ($empresa) {
                $partes[] = 'Empresa: '.$empresa->nombre;
            }
        }

        $partes[] = 'Período: '.$fechaDesde.' — '.$fechaHasta;
        $partes[] = 'Existencia inicial al '.$this->diaAnterior($fechaDesde);

        $partes[] = $this->etiquetaRango('Artículo', $filtros['desdearticulo_id'] ?? null, $filtros['hastaarticulo_id'] ?? null, fn ($id) => Articulo::query()->find($id)?->sku);
        $partes[] = $this->etiquetaRango('Categoría', $filtros['desdecategoria_id'] ?? null, $filtros['hastacategoria_id'] ?? null, fn ($id) => Categoria::query()->find($id)?->nombre);
        $partes[] = $this->etiquetaRango('Subcategoría', $filtros['desdesubcategoria_id'] ?? null, $filtros['hastasubcategoria_id'] ?? null, fn ($id) => Subcategoria::query()->find($id)?->nombre);
        $partes[] = $this->etiquetaRango('Uso', $filtros['desdeusoarticulo_id'] ?? null, $filtros['hastausoarticulo_id'] ?? null, fn ($id) => Usoarticulo::query()->find($id)?->nombre);
        $partes[] = $this->etiquetaRango('Tipo', $filtros['desdetipoarticulo_id'] ?? null, $filtros['hastatipoarticulo_id'] ?? null, fn ($id) => Tipoarticulo::query()->find($id)?->nombre);

        $depMeta = ExistenciasDepositoFiltroDepositosSupport::metaTexto((string) ($filtros['depositos_filtro'] ?? ''));
        if ($depMeta !== '') {
            $partes[] = $depMeta;
        }

        return implode(' · ', array_values(array_filter($partes, fn ($p) => trim((string) $p) !== '')));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Depmae>
     */
    private function depositosAutorizados(array $filtros): Collection
    {
        $query = Depmae::query()
            ->select('id', 'codigo', 'nombre', 'empresa_id')
            ->paraUsuarioAutorizado()
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'));

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        return ExistenciasDepositoFiltroDepositosSupport::filtrarColeccion(
            $query->get(),
            (string) ($filtros['depositos_filtro'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $depositoIds
     */
    private function agregarMovimientosPeriodo(array $filtros, array $depositoIds, string $fechaDesde, string $fechaHasta): Collection
    {
        $query = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->leftJoin('tipotransaccion_stock as t', 't.id', '=', 'am.tipotransaccion_stock_id')
            ->whereIn('am.deposito_id', $depositoIds)
            ->where('am.fecha', '>=', $fechaDesde)
            ->where('am.fecha', '<=', $fechaHasta);

        $this->aplicarFiltrosArticulo($query, $filtros);

        return $query
            ->groupBy('am.articulo_id', 't.abreviatura')
            ->selectRaw('am.articulo_id, COALESCE(t.abreviatura, \'\') as abreviatura, SUM(am.cantidad) as cantidad')
            ->get();
    }

    /**
     * @return array<int, array<string, float>>
     */
    private function pivotBucketsPorArticulo(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $articuloId = (int) $row->articulo_id;
            if (! isset($out[$articuloId])) {
                $out[$articuloId] = $this->bucketsCero();
            }
            $bucket = Clasif::bucketDeAbreviatura((string) ($row->abreviatura ?? ''));
            $out[$articuloId][$bucket] += (float) ($row->cantidad ?? 0);
        }

        return $out;
    }

    /** @return array<string, float> */
    private function bucketsCero(): array
    {
        return [
            Clasif::BUCKET_RECEPCIONES => 0.0,
            Clasif::BUCKET_DEVOLUCIONES => 0.0,
            Clasif::BUCKET_AJUSTES => 0.0,
            Clasif::BUCKET_CONSUMOS => 0.0,
            Clasif::BUCKET_OTROS => 0.0,
        ];
    }

    /**
     * @param  list<int>  $articuloIds
     * @param  list<int>  $depositoIds
     * @return array<int, float>
     */
    private function saldosHasta(array $articuloIds, array $depositoIds, string $fechaHastaInclusive): array
    {
        if ($articuloIds === [] || $depositoIds === []) {
            return [];
        }

        $rows = DB::table('articulo_movimiento')
            ->whereIn('articulo_id', $articuloIds)
            ->whereIn('deposito_id', $depositoIds)
            ->where('fecha', '<=', $fechaHastaInclusive)
            ->groupBy('articulo_id')
            ->selectRaw('articulo_id, SUM(cantidad) as saldo')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->articulo_id] = (float) ($row->saldo ?? 0);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $depositoIds
     * @return list<int>
     */
    private function articuloIdsConSaldoHasta(array $filtros, array $depositoIds, string $fechaHasta): array
    {
        $query = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->whereIn('am.deposito_id', $depositoIds)
            ->where('am.fecha', '<=', $fechaHasta);

        $this->aplicarFiltrosArticulo($query, $filtros);

        return $query
            ->groupBy('am.articulo_id')
            ->havingRaw('ABS(SUM(am.cantidad)) > 0.0000001')
            ->pluck('am.articulo_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosArticulo($query, array $filtros): void
    {
        $this->aplicarRango($query, 'a.id', $filtros['desdearticulo_id'] ?? null, $filtros['hastaarticulo_id'] ?? null);
        $this->aplicarRango($query, 'a.categoria_id', $filtros['desdecategoria_id'] ?? null, $filtros['hastacategoria_id'] ?? null);
        $this->aplicarRango($query, 'a.subcategoria_id', $filtros['desdesubcategoria_id'] ?? null, $filtros['hastasubcategoria_id'] ?? null);
        $this->aplicarRango($query, 'a.usoarticulo_id', $filtros['desdeusoarticulo_id'] ?? null, $filtros['hastausoarticulo_id'] ?? null);
        $this->aplicarRango($query, 'a.tipoarticulo_id', $filtros['desdetipoarticulo_id'] ?? null, $filtros['hastatipoarticulo_id'] ?? null);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function aplicarRango($query, string $campo, $desde, $hasta): void
    {
        $desde = $desde === null || $desde === '' ? null : (int) $desde;
        $hasta = $hasta === null || $hasta === '' ? null : (int) $hasta;

        if ($desde !== null && $desde !== ControlInventarioItemsListadoFiltros::ID_PRIMERO) {
            $query->where($campo, '>=', $desde);
        }
        if ($hasta !== null && $hasta !== ControlInventarioItemsListadoFiltros::ID_ULTIMO) {
            $query->where($campo, '<=', $hasta);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, object>
     */
    private function metaArticulos(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = Articulo::query()
            ->select([
                'articulo.id',
                'articulo.sku',
                'articulo.descripcion',
                'articulo.categoria_id',
                'articulo.subcategoria_id',
                'articulo.usoarticulo_id',
                'articulo.tipoarticulo_id',
            ])
            ->with([
                'categorias:id,nombre',
                'subcategorias:id,nombre',
                'usoarticulos:id,nombre',
                'tipoarticulos:id,nombre',
            ])
            ->whereIn('articulo.id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($rows as $id => $art) {
            $out[(int) $id] = $art;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function mapFila(array $base, $articulo): array
    {
        return array_merge($base, [
            'sku' => $articulo->sku ?? '',
            'descripcion' => $articulo->descripcion ?? '',
            'categoria' => $articulo?->categorias?->nombre ?? '',
            'subcategoria' => $articulo?->subcategorias?->nombre ?? '',
            'uso' => $articulo?->usoarticulos?->nombre ?? '',
            'tipo' => $articulo?->tipoarticulos?->nombre ?? '',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function sumarTotales(array $filas): array
    {
        $t = $this->totalesVacios();
        $t['total_articulos'] = count($filas);
        foreach ($filas as $f) {
            $t['existencia_inicial'] += (float) $f['existencia_inicial'];
            $t['recepciones'] += (float) $f['recepciones'];
            $t['devoluciones'] += (float) $f['devoluciones'];
            $t['ajustes'] += (float) $f['ajustes'];
            $t['consumos'] += (float) $f['consumos'];
            $t['otros'] += (float) $f['otros'];
            $t['existencia_final'] += (float) $f['existencia_final'];
            $t['diferencia'] += (float) $f['diferencia'];
        }

        return $t;
    }

    /** @return array<string, mixed> */
    private function totalesVacios(): array
    {
        return [
            'total_articulos' => 0,
            'existencia_inicial' => 0.0,
            'recepciones' => 0.0,
            'devoluciones' => 0.0,
            'ajustes' => 0.0,
            'consumos' => 0.0,
            'otros' => 0.0,
            'existencia_final' => 0.0,
            'diferencia' => 0.0,
            'filas_no_cierran' => 0,
        ];
    }

    /**
     * @param  list<string>  $abreviaturasOtros
     * @return list<string>
     */
    private function avisosClasificacion(array $abreviaturasOtros, int $noCierran): array
    {
        $avisos = [];
        if ($abreviaturasOtros !== []) {
            $avisos[] = 'Movimientos fuera de recepciones/devoluciones/ajustes/consumos (columna Otros): '
                .implode(', ', $abreviaturasOtros)
                .'. Incluye transferencias (TRA/TRCONT/…), préstamos y similares.';
        }
        if ($noCierran > 0) {
            $avisos[] = "Hay {$noCierran} artículo(s) donde inicial + movimientos ≠ existencia final (revisar datos).";
        }

        return $avisos;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function ventanaFechas(array $filtros): array
    {
        $hasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        $desde = (string) ($filtros['fecha_desde'] ?? date('Y-m-01', strtotime($hasta)));

        return [$desde, $hasta];
    }

    private function diaAnterior(string $fechaYmd): string
    {
        return date('Y-m-d', strtotime($fechaYmd.' -1 day'));
    }

    /**
     * @param  callable(int): ?string  $resolver
     */
    private function etiquetaRango(string $etiqueta, $desde, $hasta, callable $resolver): string
    {
        $desde = $desde === null || $desde === '' ? null : (int) $desde;
        $hasta = $hasta === null || $hasta === '' ? null : (int) $hasta;
        $esDefault = ($desde === null || $desde === ControlInventarioItemsListadoFiltros::ID_PRIMERO)
            && ($hasta === null || $hasta === ControlInventarioItemsListadoFiltros::ID_ULTIMO);
        if ($esDefault) {
            return '';
        }
        $txtDesde = ($desde === null || $desde === ControlInventarioItemsListadoFiltros::ID_PRIMERO)
            ? 'Primero'
            : ((string) ($resolver($desde) ?? $desde));
        $txtHasta = ($hasta === null || $hasta === ControlInventarioItemsListadoFiltros::ID_ULTIMO)
            ? 'Último'
            : ((string) ($resolver($hasta) ?? $hasta));

        return "{$etiqueta}: {$txtDesde} — {$txtHasta}";
    }

    /**
     * @return array{
     *     filas: LengthAwarePaginator|Collection,
     *     totales: array<string, mixed>,
     *     avisos: list<string>,
     *     abreviaturas_otros: list<string>
     * }
     */
    private function vacio(bool $paginar, int $porPagina): array
    {
        return [
            'filas' => $paginar ? new LengthAwarePaginator([], 0, $porPagina) : collect(),
            'totales' => $this->totalesVacios(),
            'avisos' => ['No hay depósitos autorizados para los filtros elegidos.'],
            'abreviaturas_otros' => [],
        ];
    }
}
