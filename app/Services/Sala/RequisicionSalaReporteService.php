<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSalaArticulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;
use App\Support\Sala\RequisicionSalaReporteCriteriosSupport;
use App\Support\Sala\RequisicionSalaReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequisicionSalaReporteService
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private StkmaeUltimaCompraAnitaService $stkmaeUltimaCompraAnitaService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array{total_cantidad: float, total_entregado: float, total_pendiente: float, total_requisiciones: int},
     *     secciones?: list<array<string, mixed>>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        $consolidar = array_key_exists('consolidar_empresas', $filtros)
            ? (bool) $filtros['consolidar_empresas']
            : true;

        if ($consolidar || count($empresaIds) <= 1) {
            return $this->generarConsolidado($filtros);
        }

        return $this->generarPorEmpresa($filtros, $empresaIds);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>, secciones: list<array<string, mixed>>}
     */
    private function generarConsolidado(array $filtros): array
    {
        $lineas = $this->consultarLineas($filtros);
        $preciosUltimaCompra = $this->resolverPreciosUltimaCompra($lineas);
        $filas = $this->aplanarFilas($lineas, $filtros, $preciosUltimaCompra);
        $totales = $this->totalesDesdeLineas($lineas);

        return [
            'filas' => $filas,
            'totales' => $totales,
            'secciones' => [[
                'empresa_id' => count($filtros['empresa_ids'] ?? []) === 1
                    ? (int) ($filtros['empresa_ids'][0] ?? 0)
                    : 0,
                'empresa_nombre' => '',
                'filas' => $filas,
                'totales' => $totales,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $empresaIds
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>, secciones: list<array<string, mixed>>}
     */
    private function generarPorEmpresa(array $filtros, array $empresaIds): array
    {
        $filasFusion = [];
        $secciones = [];
        $totales = [
            'total_cantidad' => 0.0,
            'total_entregado' => 0.0,
            'total_pendiente' => 0.0,
            'total_requisiciones' => 0,
        ];

        foreach ($empresaIds as $empresaId) {
            $parcialFiltros = array_merge($filtros, [
                'empresa_ids' => [$empresaId],
                'consolidar_empresas' => true,
            ]);
            $parcial = $this->generarConsolidado($parcialFiltros);
            $nombre = $this->nombreEmpresa($empresaId, $parcial['filas']);

            $secciones[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombre,
                'filas' => $parcial['filas'],
                'totales' => $parcial['totales'],
            ];

            $filasFusion[] = [
                'tipo_fila' => 'header_empresa',
                'empresa_id' => $empresaId,
                'nombreempresa' => $nombre,
            ];
            foreach ($parcial['filas'] as $fila) {
                $filasFusion[] = $fila;
            }

            $totales['total_cantidad'] += (float) ($parcial['totales']['total_cantidad'] ?? 0);
            $totales['total_entregado'] += (float) ($parcial['totales']['total_entregado'] ?? 0);
            $totales['total_pendiente'] += (float) ($parcial['totales']['total_pendiente'] ?? 0);
            $totales['total_requisiciones'] += (int) ($parcial['totales']['total_requisiciones'] ?? 0);
        }

        return [
            'filas' => $filasFusion,
            'totales' => $totales,
            'secciones' => $secciones,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function nombreEmpresa(int $empresaId, array $filas = []): string
    {
        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila['nombreempresa'] ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        $nombre = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');

        return $nombre !== '' ? $nombre : ('#'.$empresaId);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $total = count($filas);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filas, $offset, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    public function consultarLineas(array $filtros): Collection
    {
        $agrupacion = (string) ($filtros['agrupacion'] ?? RequisicionSalaReporteFiltros::AGRUPACION_REQUISICION);

        $query = DB::table('requisicion_sala_articulo as rsa')
            ->join('requisicion_sala as rs', 'rs.id', '=', 'rsa.requisicion_sala_id')
            ->join('articulo as a', 'a.id', '=', 'rsa.articulo_id')
            ->join('empresa as e', 'e.id', '=', 'rs.empresa_id')
            ->join('centrocosto as cc', 'cc.id', '=', 'rs.centrocosto_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'rs.usuario_id')
            ->leftJoin('tecnico_laboratorio as tl', 'tl.id', '=', 'rsa.tecnico_laboratorio_id')
            ->leftJoin(DB::raw('(
                SELECT ap1.articulo_id, ap1.codigo_articulo_proveedor
                FROM articulo_proveedor ap1
                INNER JOIN (
                    SELECT articulo_id, MIN(id) AS min_id
                    FROM articulo_proveedor
                    WHERE activo = 1
                    GROUP BY articulo_id
                ) apm ON apm.min_id = ap1.id
            ) as ap'), 'ap.articulo_id', '=', 'a.id')
            ->select([
                'rsa.id as linea_id',
                'rsa.requisicion_sala_id',
                'rsa.articulo_id',
                'rsa.cantidad',
                'rsa.cantidadentregada',
                'rsa.precio',
                'rsa.uid',
                'rsa.numeroparte',
                'rsa.fueradeservicio',
                'rsa.destino',
                'rsa.estado',
                'rsa.estadoparcial',
                'rsa.fecha_entrega',
                'rsa.numeroremito',
                'rsa.nombreresponsable',
                'rs.numerorequisicion',
                'rs.fecha',
                'rs.empresa_id',
                'rs.centrocosto_id',
                'rs.usuario_id',
                'rs.detalle as leyenda_cabecera',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                DB::raw('COALESCE(NULLIF(ap.codigo_articulo_proveedor, ""), NULLIF(a.skualternativo, ""), "") as articulo_proveedor'),
                'cc.codigo as centrocosto_codigo',
                'u.nombre as solicitante_nombre',
                'tl.nombre as tecnico_nombre',
                'e.nombre as nombreempresa',
            ]);

        if ($agrupacion === RequisicionSalaReporteFiltros::AGRUPACION_USUARIO) {
            $query->orderBy('rs.usuario_id')->orderBy('rs.numerorequisicion')->orderBy('rsa.id');
        } else {
            $query->orderBy('rs.numerorequisicion')->orderBy('rsa.id');
        }

        $query->where('rs.estado', '!=', 'SUSPENDIDO');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'rs.empresa_id');

        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        if ($empresaIds !== []) {
            $query->whereIn('rs.empresa_id', $empresaIds);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('rs.fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('rs.fecha', '<=', $filtros['fecha_hasta']);
        }

        RequisicionSalaReporteCriteriosSupport::aplicarFiltroNumerosRequisicion(
            $query,
            (string) ($filtros['requisicion_desde'] ?? ''),
            (string) ($filtros['requisicion_hasta'] ?? ''),
        );

        RequisicionSalaReporteCriteriosSupport::aplicarFiltroUsuarios(
            $query,
            (string) ($filtros['usuarios'] ?? ''),
        );

        RequisicionSalaReporteFiltros::aplicarEstadoLinea(
            $query,
            (string) ($filtros['estado_linea'] ?? RequisicionSalaReporteFiltros::ESTADO_TODOS),
        );

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @return array<string, float|null>
     */
    private function resolverPreciosUltimaCompra(Collection $lineas): array
    {
        $skus = $lineas
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter(fn (string $sku) => $sku !== '')
            ->unique()
            ->values()
            ->all();

        if ($skus === []) {
            return [];
        }

        return $this->stkmaeUltimaCompraAnitaService->obtenerPreciosUltimaCompraPorSkus($skus);
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, float|null>  $preciosUltimaCompra
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(Collection $lineas, array $filtros, array $preciosUltimaCompra = []): array
    {
        if ($lineas->isEmpty()) {
            return [];
        }

        $agrupacion = (string) ($filtros['agrupacion'] ?? RequisicionSalaReporteFiltros::AGRUPACION_REQUISICION);
        $soloTotales = ($filtros['modo_listado'] ?? RequisicionSalaReporteFiltros::MODO_MOVIMIENTOS)
            === RequisicionSalaReporteFiltros::MODO_TOTALES;

        $filas = [];
        $grupoActual = null;
        $subCantidad = 0.0;
        $subEntregado = 0.0;
        $subPendiente = 0.0;
        $metaGrupo = [];

        $cerrarGrupo = function () use (
            &$filas,
            &$grupoActual,
            &$subCantidad,
            &$subEntregado,
            &$subPendiente,
            &$metaGrupo,
            $agrupacion,
        ) {
            if ($grupoActual === null) {
                return;
            }

            if ($agrupacion === RequisicionSalaReporteFiltros::AGRUPACION_USUARIO) {
                $filas[] = [
                    'tipo_fila' => 'subtotal_usuario',
                    'usuario_id' => (int) ($metaGrupo['usuario_id'] ?? 0),
                    'usuario_nombre' => (string) ($metaGrupo['usuario_nombre'] ?? ''),
                    'total_cantidad' => $subCantidad,
                    'total_entregado' => $subEntregado,
                    'total_pendiente' => $subPendiente,
                ];
            } else {
                $filas[] = [
                    'tipo_fila' => 'subtotal_requisicion',
                    'numerorequisicion' => $grupoActual,
                    'total_cantidad' => $subCantidad,
                    'total_entregado' => $subEntregado,
                    'total_pendiente' => $subPendiente,
                ];
            }

            $grupoActual = null;
            $subCantidad = 0.0;
            $subEntregado = 0.0;
            $subPendiente = 0.0;
            $metaGrupo = [];
        };

        foreach ($lineas as $row) {
            $claveGrupo = $agrupacion === RequisicionSalaReporteFiltros::AGRUPACION_USUARIO
                ? (int) ($row->usuario_id ?? 0)
                : (int) ($row->numerorequisicion ?? 0);

            if ($grupoActual !== null && $grupoActual !== $claveGrupo) {
                $cerrarGrupo();
            }

            if ($grupoActual !== $claveGrupo) {
                $grupoActual = $claveGrupo;
                $metaGrupo = [
                    'usuario_id' => (int) ($row->usuario_id ?? 0),
                    'usuario_nombre' => trim((string) ($row->solicitante_nombre ?? '')) ?: 'Inexistente',
                ];

                if (! $soloTotales) {
                    if ($agrupacion === RequisicionSalaReporteFiltros::AGRUPACION_USUARIO) {
                        $filas[] = [
                            'tipo_fila' => 'cabecera_usuario',
                            'usuario_id' => $metaGrupo['usuario_id'],
                            'usuario_nombre' => $metaGrupo['usuario_nombre'],
                        ];
                    } else {
                        $filas[] = [
                            'tipo_fila' => 'cabecera_requisicion',
                            'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
                            'requisicion_sala_id' => (int) ($row->requisicion_sala_id ?? 0),
                        ];
                    }
                }
            }

            $cantidad = (float) ($row->cantidad ?? 0);
            $entregado = (float) ($row->cantidadentregada ?? 0);
            $pendiente = max(0, $cantidad - $entregado);

            $subCantidad += $cantidad;
            $subEntregado += $entregado;
            $subPendiente += $pendiente;

            if (! $soloTotales) {
                $filas[] = $this->mapearFilaDetalle($row, $cantidad, $entregado, $pendiente, $preciosUltimaCompra);
            }
        }

        $cerrarGrupo();

        $totales = $this->totalesDesdeLineas($lineas);
        $filas[] = [
            'tipo_fila' => 'total_general',
            'total_cantidad' => $totales['total_cantidad'],
            'total_entregado' => $totales['total_entregado'],
            'total_pendiente' => $totales['total_pendiente'],
        ];

        return $filas;
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @return array{total_cantidad: float, total_entregado: float, total_pendiente: float, total_requisiciones: int}
     */
    public function totalesDesdeLineas(Collection $lineas): array
    {
        $totalCantidad = 0.0;
        $totalEntregado = 0.0;

        foreach ($lineas as $row) {
            $cantidad = (float) ($row->cantidad ?? 0);
            $entregado = (float) ($row->cantidadentregada ?? 0);
            $totalCantidad += $cantidad;
            $totalEntregado += $entregado;
        }

        return [
            'total_cantidad' => $totalCantidad,
            'total_entregado' => $totalEntregado,
            'total_pendiente' => max(0, $totalCantidad - $totalEntregado),
            'total_requisiciones' => (int) $lineas->pluck('numerorequisicion')->unique()->count(),
        ];
    }

    /** @param  array<string, mixed>  $filtros */
    public function subtituloFiltros(array $filtros, $empresaQuery = null): string
    {
        $partes = [];

        $ids = $filtros['empresa_ids'] ?? [];
        if ($ids !== [] && $empresaQuery !== null) {
            $nombres = collect($empresaQuery)
                ->whereIn('id', $ids)
                ->pluck('nombre')
                ->filter()
                ->values()
                ->all();
            if ($nombres !== []) {
                $txt = 'Empresas: '.implode(', ', $nombres);
                if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
                    $txt .= ' (consolidado)';
                }
                $partes[] = $txt;
            }
        }

        $partes[] = 'Período: '.RequisicionSalaReporteFiltros::formatearPeriodoTexto($filtros);
        $partes[] = RequisicionSalaReporteFiltros::subtituloEstadoLinea(
            (string) ($filtros['estado_linea'] ?? RequisicionSalaReporteFiltros::ESTADO_TODOS),
        );
        $partes[] = 'Agrupación: '.RequisicionSalaReporteFiltros::etiquetaAgrupacion(
            (string) ($filtros['agrupacion'] ?? RequisicionSalaReporteFiltros::AGRUPACION_REQUISICION),
        );
        $partes[] = RequisicionSalaReporteFiltros::etiquetaModoListado(
            (string) ($filtros['modo_listado'] ?? RequisicionSalaReporteFiltros::MODO_MOVIMIENTOS),
        );

        if ($sub = RequisicionSalaReporteCriteriosSupport::subtituloRequisiciones($filtros)) {
            $partes[] = $sub;
        }

        if ($sub = RequisicionSalaReporteCriteriosSupport::subtituloUsuarios($filtros)) {
            $partes[] = $sub;
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, float|null>  $preciosUltimaCompra
     * @return array<string, mixed>
     */
    private function mapearFilaDetalle(
        object $row,
        float $cantidad,
        float $entregado,
        float $pendiente,
        array $preciosUltimaCompra = [],
    ): array {
        $estadoValor = trim((string) ($row->estado ?? ' ')) ?: ' ';
        $estadoNombre = RequisicionSalaArticulo::estadoLineaNombrePorValor($estadoValor);
        $destinoValor = trim((string) ($row->destino ?? ''));
        $destinoNombre = RequisicionSalaArticulo::destinoNombrePorValor($destinoValor);
        $parcialNombre = RequisicionSalaArticulo::estadoParcialNombrePorValor($row->estadoparcial ?? null);

        $sku = trim((string) ($row->sku ?? ''));
        $precioLinea = (float) ($row->precio ?? 0);
        $precioUltimaCompra = $sku !== '' ? ($preciosUltimaCompra[$sku] ?? null) : null;
        $precio = $precioUltimaCompra !== null ? (float) $precioUltimaCompra : $precioLinea;

        return [
            'tipo_fila' => 'detalle',
            'linea_id' => (int) ($row->linea_id ?? 0),
            'requisicion_sala_id' => (int) ($row->requisicion_sala_id ?? 0),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'centrocosto_id' => (int) ($row->centrocosto_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'usuario_id' => (int) ($row->usuario_id ?? 0),
            'sku' => $sku,
            'descripcion' => (string) ($row->articulo_descripcion ?? ''),
            'articulo_proveedor' => (string) ($row->articulo_proveedor ?? ''),
            'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
            'fecha' => $row->fecha ?? null,
            'cantidad' => $cantidad,
            'entregado' => $entregado,
            'pendiente' => $pendiente,
            'precio' => $precio,
            'centrocosto_codigo' => (string) ($row->centrocosto_codigo ?? ''),
            'leyenda' => trim((string) ($row->leyenda_cabecera ?? '')) ?: trim((string) ($row->solicitante_nombre ?? '')),
            'uid' => (string) ($row->uid ?? ''),
            'numeroparte' => (string) ($row->numeroparte ?? ''),
            'fueradeservicio' => ((string) ($row->fueradeservicio ?? '')) === 'S' ? 'S' : 'N',
            'destino' => $destinoNombre !== '' ? ucfirst(strtolower($destinoNombre)) : '',
            'estado' => $this->etiquetaEstadoLegacy($estadoNombre),
            'entrega_parcial' => $parcialNombre,
            'fecha_entrega' => $this->formatearFechaEntrega($row->fecha_entrega ?? null),
            'numeroremito' => (string) ($row->numeroremito ?? ''),
            'responsable' => trim((string) ($row->nombreresponsable ?? '')),
            'tecnico' => trim((string) ($row->tecnico_nombre ?? '')),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
        ];
    }

    private function etiquetaEstadoLegacy(string $estadoNombre): string
    {
        return match ($estadoNombre) {
            'ENTREGADO' => 'Entregado',
            'PENDIENTE' => 'Pendiente',
            'ENTREGADO PARCIAL' => 'Entreg. pa',
            'PARA RETIRAR' => 'Para retirar',
            'PENDIENTE REP' => 'Pendiente rep',
            'CERRADO' => 'Cerrado',
            default => $estadoNombre,
        };
    }

    private function formatearFechaEntrega($fecha): string
    {
        if ($fecha === null || trim((string) $fecha) === '') {
            return '-- -- --';
        }

        try {
            return date('d/m/Y', strtotime((string) $fecha));
        } catch (\Throwable) {
            return '-- -- --';
        }
    }
}
