<?php

namespace App\Services\Stock;

use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\ArticuloSaldosDepositoSupport;
use App\Support\Stock\ExistenciasDepositoFiltroDepositosSupport;
use App\Support\Stock\ExistenciasDepositoListadoFiltros;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExistenciasDepositoReporteService
{
  public function __construct(
    private EmpresaRepositoryInterface $empresaRepository,
  ) {}

  /**
   * @param  array<string, mixed>  $filtros
   * @return array{
   *     depositos: \Illuminate\Support\Collection<int, \App\Models\Stock\Depmae>,
   *     filas: \Illuminate\Support\Collection<int, array<string, mixed>>|\Illuminate\Pagination\LengthAwarePaginator,
   *     totales: array<string, mixed>
   * }
   */
  public function consultar(array $filtros, bool $paginar = true, int $porPagina = 25): array
  {
    [$fechaDesde, $fechaHasta] = $this->resolverVentanaFechas($filtros);
    $depositosTodos = $this->depositosAutorizados($filtros);
    $depositoIdsTodos = $depositosTodos->pluck('id')->map(fn ($id) => (int) $id)->all();

    if ($depositoIdsTodos === []) {
      return $this->resultadoVacio($paginar, $porPagina, $depositosTodos);
    }

    $depositoIdsActivos = $this->depositoIdsConExistencia($depositoIdsTodos, $fechaHasta);
    $depositos = $depositosTodos->whereIn('id', $depositoIdsActivos)->values();
    $depositos->load('empresas:id,nombre');

    if ($depositoIdsActivos === []) {
      return $this->resultadoVacio($paginar, $porPagina, collect());
    }

    $articuloIds = $this->articuloIdsFiltrados($filtros, $depositoIdsActivos, $fechaHasta);

    if ($articuloIds === []) {
      return [
        'depositos' => $depositos,
        'filas' => $paginar ? new LengthAwarePaginator([], 0, $porPagina) : collect(),
        'totales' => [
          'total_articulos' => 0,
          'totales_deposito' => array_fill_keys($depositoIdsActivos, 0.0),
          'total_general' => 0.0,
        ],
      ];
    }

    $query = Articulo::query()
      ->select([
        'articulo.id',
        'articulo.sku',
        'articulo.descripcion',
        'articulo.categoria_id',
        'articulo.usoarticulo_id',
        'articulo.tipoarticulo_id',
      ])
      ->with([
        'categorias:id,nombre',
        'usoarticulos:id,nombre',
        'tipoarticulos:id,nombre',
      ])
      ->whereIn('articulo.id', $articuloIds)
      ->orderBy('articulo.sku');

    if ($paginar) {
      $paginator = $query->paginate($porPagina);
      $idsPagina = $paginator->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
      $saldosMap = $this->saldosPorArticulo($idsPagina, $depositoIdsActivos, $fechaHasta);
      $filas = $paginator->getCollection()->map(fn (Articulo $articulo) => $this->mapFila($articulo, $depositos, $saldosMap));
      $paginator->setCollection($filas);

      return [
        'depositos' => $depositos,
        'filas' => $paginator,
        'totales' => $this->totales($filtros, $depositoIdsActivos, $fechaHasta),
      ];
    }

    $articulos = $query->get();
    $articuloIdsPagina = $articulos->pluck('id')->map(fn ($id) => (int) $id)->all();
    $saldosMap = $this->saldosPorArticulo($articuloIdsPagina, $depositoIdsActivos, $fechaHasta);
    $filas = $articulos->map(fn (Articulo $articulo) => $this->mapFila($articulo, $depositos, $saldosMap));

    return [
      'depositos' => $depositos,
      'filas' => $filas,
      'totales' => $this->totales($filtros, $depositoIdsActivos, $fechaHasta),
    ];
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @param  list<int>  $depositoIds
   * @return array{total_articulos: int, totales_deposito: array<int, float>, total_general: float}
   */
  public function totales(array $filtros, array $depositoIds, ?string $fechaHasta = null): array
  {
    if ($depositoIds === []) {
      return [
        'total_articulos' => 0,
        'totales_deposito' => [],
        'total_general' => 0.0,
      ];
    }

    $fechaHasta = $fechaHasta ?? (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));

    $articuloIds = $this->articuloIdsFiltrados($filtros, $depositoIds, $fechaHasta);

    if ($articuloIds === []) {
      return [
        'total_articulos' => 0,
        'totales_deposito' => array_fill_keys($depositoIds, 0.0),
        'total_general' => 0.0,
      ];
    }

    $rows = DB::table($this->usaSaldoVigenteTabla($fechaHasta) ? 'articulo_saldo_deposito' : 'articulo_movimiento')
      ->when(
        ! $this->usaSaldoVigenteTabla($fechaHasta),
        fn ($q) => $q->whereNull('deleted_at')->where('fecha', '<=', $fechaHasta)
      )
      ->whereIn('articulo_id', $articuloIds)
      ->whereIn('deposito_id', $depositoIds)
      ->groupBy('deposito_id')
      ->selectRaw('deposito_id, SUM(cantidad) as saldo')
      ->get();

    $totalesDeposito = array_fill_keys($depositoIds, 0.0);
    $totalGeneral = 0.0;

    foreach ($rows as $row) {
      $depId = (int) $row->deposito_id;
      $saldo = (float) ($row->saldo ?? 0);
      $totalesDeposito[$depId] = $saldo;
      $totalGeneral += $saldo;
    }

    return [
      'total_articulos' => count($articuloIds),
      'totales_deposito' => $totalesDeposito,
      'total_general' => $totalGeneral,
    ];
  }

  public function subtituloFiltros(array $filtros): string
  {
    $partes = [];

    if (! empty($filtros['empresa_id'])) {
      $empresa = Empresa::query()->find((int) $filtros['empresa_id']);
      if ($empresa) {
        $partes[] = 'Empresa: '.$empresa->nombre;
      }
    }

    [$fechaDesde, $fechaHasta] = $this->resolverVentanaFechas($filtros);
    $partes[] = 'Período movimientos: '.$fechaDesde.' — '.$fechaHasta;
    $partes[] = 'Existencias a '.$fechaHasta.($this->usaSaldoVigenteTabla($fechaHasta) ? ' (tabla saldos vigente)' : ' (suma movimientos)');

    $partes[] = self::etiquetaRango(
      'Artículo',
      $filtros['desdearticulo_id'] ?? null,
      $filtros['hastaarticulo_id'] ?? null,
      fn ($id) => Articulo::query()->find($id)?->sku
    );
    $partes[] = self::etiquetaRango(
      'Categoría',
      $filtros['desdecategoria_id'] ?? null,
      $filtros['hastacategoria_id'] ?? null,
      fn ($id) => Categoria::query()->find($id)?->nombre
    );
    $partes[] = self::etiquetaRango(
      'Uso',
      $filtros['desdeusoarticulo_id'] ?? null,
      $filtros['hastausoarticulo_id'] ?? null,
      fn ($id) => Usoarticulo::query()->find($id)?->nombre
    );
    $partes[] = self::etiquetaRango(
      'Tipo',
      $filtros['desdetipoarticulo_id'] ?? null,
      $filtros['hastatipoarticulo_id'] ?? null,
      fn ($id) => Tipoarticulo::query()->find($id)?->nombre
    );

    $depositosFiltro = ExistenciasDepositoFiltroDepositosSupport::metaTexto((string) ($filtros['depositos_filtro'] ?? ''));
    if ($depositosFiltro !== '') {
      $partes[] = $depositosFiltro;
    }

    if (! ($filtros['solo_con_saldo'] ?? true)) {
      $partes[] = 'Incluye artículos sin saldo en el período';
    }

    return implode(' · ', array_values(array_filter($partes, fn ($p) => trim($p) !== '')));
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @return Collection<int, Depmae>
   */
  public function depositosColumnas(array $filtros): Collection
  {
    [$fechaDesde, $fechaHasta] = $this->resolverVentanaFechas($filtros);
    $depositos = $this->depositosAutorizados($filtros);
    $idsActivos = $this->depositoIdsConExistencia(
      $depositos->pluck('id')->map(fn ($id) => (int) $id)->all(),
      $fechaHasta
    );

    return $depositos->whereIn('id', $idsActivos)->values();
  }

  /**
   * Depósitos visibles según empresa, autorización de usuario y filtro de códigos del formulario.
   *
   * @param  array<string, mixed>  $filtros
   * @return Collection<int, Depmae>
   */
  public function depositosAutorizadosParaFiltros(array $filtros): Collection
  {
    return $this->depositosAutorizados($filtros);
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
      ->orderByRaw('CAST(codigo AS UNSIGNED) ASC');

    $empresaId = (int) ($filtros['empresa_id'] ?? 0);
    if ($empresaId > 0) {
      $query->paraEmpresa($empresaId);
    } else {
      $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
    }

    $depositos = $query->get();

    return ExistenciasDepositoFiltroDepositosSupport::filtrarColeccion(
      $depositos,
      (string) ($filtros['depositos_filtro'] ?? ''),
    );
  }

  /**
   * @param  list<int>  $depositoIds
   * @return list<int>
   */
  private function depositoIdsConExistencia(array $depositoIds, string $fechaHasta): array
  {
    if ($depositoIds === []) {
      return [];
    }

    if ($this->usaSaldoVigenteTabla($fechaHasta)) {
      return DB::table('articulo_saldo_deposito')
        ->whereIn('deposito_id', $depositoIds)
        ->groupBy('deposito_id')
        ->havingRaw('ABS(SUM(cantidad)) > 0.0000001')
        ->pluck('deposito_id')
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all();
    }

    return DB::table('articulo_movimiento')
      ->whereNull('deleted_at')
      ->whereIn('deposito_id', $depositoIds)
      ->where('fecha', '<=', $fechaHasta)
      ->groupBy('deposito_id')
      ->havingRaw('ABS(SUM(cantidad)) > 0.0000001')
      ->pluck('deposito_id')
      ->map(fn ($id) => (int) $id)
      ->values()
      ->all();
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @param  list<int>  $depositoIds
   * @return list<int>
   */
  private function articuloIdsFiltrados(
    array $filtros,
    array $depositoIds,
    string $fechaHasta,
  ): array {
    if ($depositoIds === []) {
      return [];
    }

    if ($this->usaSaldoVigenteTabla($fechaHasta)) {
      return $this->articuloIdsDesdeSaldoVigente($filtros, $depositoIds);
    }

    return $this->articuloIdsDesdeMovimientos($filtros, $depositoIds, $fechaHasta);
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @param  list<int>  $depositoIds
   * @return list<int>
   */
  private function articuloIdsDesdeSaldoVigente(array $filtros, array $depositoIds): array
  {
    $query = DB::table('articulo_saldo_deposito as sd')
      ->join('articulo as a', 'a.id', '=', 'sd.articulo_id')
      ->whereIn('sd.deposito_id', $depositoIds);

    if ($filtros['solo_con_saldo'] ?? true) {
      $query->whereRaw('ABS(sd.cantidad) > 0.0000001');
    }

    $this->aplicarFiltrosArticuloQuery($query, $filtros);

    return $query->distinct()->pluck('sd.articulo_id')->map(fn ($id) => (int) $id)->values()->all();
  }

  /**
   * @param  array<string, mixed>  $filtros
   * @param  list<int>  $depositoIds
   * @return list<int>
   */
  private function articuloIdsDesdeMovimientos(array $filtros, array $depositoIds, string $fechaHasta): array
  {
    $query = DB::table('articulo_movimiento as am')
      ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
      ->whereNull('am.deleted_at')
      ->whereIn('am.deposito_id', $depositoIds)
      ->where('am.fecha', '<=', $fechaHasta);

    $this->aplicarFiltrosArticuloQuery($query, $filtros);

    $query->groupBy('am.articulo_id', 'am.deposito_id');

    if ($filtros['solo_con_saldo'] ?? true) {
      $query->havingRaw('ABS(SUM(am.cantidad)) > 0.0000001');
    }

    return $query->pluck('am.articulo_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
  }

  /**
   * @param  \Illuminate\Database\Query\Builder  $query
   * @param  array<string, mixed>  $filtros
   */
  private function aplicarFiltrosArticuloQuery(\Illuminate\Database\Query\Builder $query, array $filtros): void
  {
    // La empresa del reporte se acota por depósito (depmae.empresa_id en depositosAutorizados).

    self::aplicarRangoIdQuery($query, 'a.id', $filtros['desdearticulo_id'] ?? null, $filtros['hastaarticulo_id'] ?? null);
    self::aplicarRangoIdQuery($query, 'a.categoria_id', $filtros['desdecategoria_id'] ?? null, $filtros['hastacategoria_id'] ?? null);
    self::aplicarRangoIdQuery($query, 'a.usoarticulo_id', $filtros['desdeusoarticulo_id'] ?? null, $filtros['hastausoarticulo_id'] ?? null);
    self::aplicarRangoIdQuery($query, 'a.tipoarticulo_id', $filtros['desdetipoarticulo_id'] ?? null, $filtros['hastatipoarticulo_id'] ?? null);
  }

  /**
   * @param  list<int>  $articuloIds
   * @param  list<int>  $depositoIds
   * @return array<int, array<int, float>>
   */
  private function saldosPorArticulo(
    array $articuloIds,
    array $depositoIds,
    string $fechaHasta,
  ): array {
    if ($articuloIds === [] || $depositoIds === []) {
      return [];
    }

    if ($this->usaSaldoVigenteTabla($fechaHasta)) {
      $rows = DB::table('articulo_saldo_deposito')
        ->whereIn('articulo_id', $articuloIds)
        ->whereIn('deposito_id', $depositoIds)
        ->get(['articulo_id', 'deposito_id', 'cantidad']);

      $map = [];
      foreach ($rows as $row) {
        $articuloId = (int) $row->articulo_id;
        $depId = (int) $row->deposito_id;
        $map[$articuloId][$depId] = (float) ($row->cantidad ?? 0);
      }

      return $map;
    }

    $rows = DB::table('articulo_movimiento')
      ->whereNull('deleted_at')
      ->whereIn('articulo_id', $articuloIds)
      ->whereIn('deposito_id', $depositoIds)
      ->where('fecha', '<=', $fechaHasta)
      ->groupBy('articulo_id', 'deposito_id')
      ->selectRaw('articulo_id, deposito_id, SUM(cantidad) as saldo')
      ->get();

    $map = [];
    foreach ($rows as $row) {
      $articuloId = (int) $row->articulo_id;
      $depId = (int) $row->deposito_id;
      $map[$articuloId][$depId] = (float) ($row->saldo ?? 0);
    }

    return $map;
  }

  private function usaSaldoVigenteTabla(string $fechaHasta): bool
  {
    return $fechaHasta >= date('Y-m-d');
  }

  /**
   * @param  Collection<int, Depmae>  $depositos
   * @param  array<int, array<int, float>>  $saldosMap
   * @return array<string, mixed>
   */
  private function mapFila(Articulo $articulo, Collection $depositos, array $saldosMap): array
  {
    $articuloId = (int) $articulo->id;
    $saldos = [];
    $total = 0.0;

    foreach ($depositos as $dep) {
      $depId = (int) $dep->id;
      $saldo = (float) ($saldosMap[$articuloId][$depId] ?? 0.0);
      $saldos[$depId] = $saldo;
      $total += $saldo;
    }

    return [
      'articulo_id' => $articuloId,
      'sku' => (string) ($articulo->sku ?? ''),
      'descripcion' => (string) ($articulo->descripcion ?? ''),
      'categoria' => (string) ($articulo->categorias?->nombre ?? ''),
      'uso' => (string) ($articulo->usoarticulos?->nombre ?? ''),
      'tipo' => (string) ($articulo->tipoarticulos?->nombre ?? ''),
      'nombreempresa' => (string) ($depositos->first()?->empresas?->nombre ?? ''),
      'saldos' => $saldos,
      'total' => $total,
      'total_fmt' => ArticuloSaldosDepositoSupport::formatSaldo($total),
    ];
  }

  /**
   * @return array{0: string, 1: string}
   */
  private function resolverVentanaFechas(array $filtros): array
  {
    $fechaHasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
    $fechaDesde = (string) ($filtros['fecha_desde'] ?? $fechaHasta);

    return [$fechaDesde, $fechaHasta];
  }

  /**
   * @return array{depositos: Collection, filas: LengthAwarePaginator|Collection, totales: array}
   */
  private function resultadoVacio(bool $paginar, int $porPagina, Collection $depositos): array
  {
    $filas = $paginar
      ? new LengthAwarePaginator([], 0, $porPagina)
      : collect();

    return [
      'depositos' => $depositos,
      'filas' => $filas,
      'totales' => [
        'total_articulos' => 0,
        'totales_deposito' => [],
        'total_general' => 0.0,
      ],
    ];
  }

  private static function aplicarRangoId(Builder $query, string $columna, ?int $desde, ?int $hasta): void
  {
    if ($desde === null && $hasta === null) {
      return;
    }

    $desdeId = $desde ?? ExistenciasDepositoListadoFiltros::ID_PRIMERO;
    $hastaId = $hasta ?? ExistenciasDepositoListadoFiltros::ID_ULTIMO;

    if ($desdeId === ExistenciasDepositoListadoFiltros::ID_PRIMERO && $hastaId === ExistenciasDepositoListadoFiltros::ID_ULTIMO) {
      return;
    }

    $query->whereBetween($columna, [$desdeId, $hastaId]);
  }

  private static function aplicarRangoIdQuery(\Illuminate\Database\Query\Builder $query, string $columna, ?int $desde, ?int $hasta): void
  {
    if ($desde === null && $hasta === null) {
      return;
    }

    $desdeId = $desde ?? ExistenciasDepositoListadoFiltros::ID_PRIMERO;
    $hastaId = $hasta ?? ExistenciasDepositoListadoFiltros::ID_ULTIMO;

    if ($desdeId === ExistenciasDepositoListadoFiltros::ID_PRIMERO && $hastaId === ExistenciasDepositoListadoFiltros::ID_ULTIMO) {
      return;
    }

    $query->whereBetween($columna, [$desdeId, $hastaId]);
  }

  private static function etiquetaRango(string $label, ?int $desde, ?int $hasta, callable $resolver): string
  {
    if ($desde === null && $hasta === null) {
      return '';
    }

    $desdeId = $desde ?? ExistenciasDepositoListadoFiltros::ID_PRIMERO;
    $hastaId = $hasta ?? ExistenciasDepositoListadoFiltros::ID_ULTIMO;

    if ($desdeId === ExistenciasDepositoListadoFiltros::ID_PRIMERO && $hastaId === ExistenciasDepositoListadoFiltros::ID_ULTIMO) {
      return '';
    }

    $desdeTxt = $desdeId === ExistenciasDepositoListadoFiltros::ID_PRIMERO
      ? 'Primero'
      : (string) ($resolver($desdeId) ?? $desdeId);
    $hastaTxt = $hastaId === ExistenciasDepositoListadoFiltros::ID_ULTIMO
      ? 'Último'
      : (string) ($resolver($hastaId) ?? $hastaId);

    return $label.': '.$desdeTxt.' — '.$hastaTxt;
  }
}
