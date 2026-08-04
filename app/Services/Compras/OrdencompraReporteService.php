<?php

namespace App\Services\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\OrdencompraReporteFiltros;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrdencompraReporteService
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float|int>,
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
        $filas = $this->aplanarFilas($lineas, $filtros);
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
            'total_facturado' => 0.0,
            'total_pendiente_fact' => 0.0,
            'total_importe_pendiente' => 0.0,
            'total_importe_oc' => 0.0,
            'total_ordenes' => 0,
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

            foreach (array_keys($totales) as $clave) {
                if ($clave === 'total_ordenes') {
                    $totales[$clave] += (int) ($parcial['totales'][$clave] ?? 0);
                } else {
                    $totales[$clave] += (float) ($parcial['totales'][$clave] ?? 0);
                }
            }
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
        $agrupacion = (string) ($filtros['agrupacion'] ?? OrdencompraReporteFiltros::AGRUPACION_PEDIDO);

        $sqlEntregado = 'COALESCE(ent.cantidad_entregada, 0)';
        $sqlPendiente = 'GREATEST(oa.cantidad - '.$sqlEntregado.', 0)';

        $query = DB::table('ordencompra_articulo as oa')
            ->join('ordencompra as oc', 'oc.id', '=', 'oa.ordencompra_id')
            ->join('empresa as e', 'e.id', '=', 'oc.empresa_id')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'oc.centrocosto_id')
            ->leftJoin('centrocosto as ccd', 'ccd.id', '=', 'oa.centrocostodestino_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'oa.articulo_id')
            ->leftJoin('categoria as cat', 'cat.id', '=', 'a.categoria_id')
            ->leftJoin('unidadmedida as umd', 'umd.id', '=', 'a.unidadmedida_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'oa.moneda_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'oc.creousuario_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->leftJoin('condicionpago as cp', 'cp.id', '=', 'oc.condicionpago_id')
            ->leftJoin('capex as cap', 'cap.id', '=', 'oa.capex_id')
            ->leftJoin('partidagasto as pg', 'pg.id', '=', 'oa.partidagasto_id')
            ->leftJoin('cuentacontable as cta', 'cta.id', '=', 'a.cuentacontablecompra_id')
            ->leftJoin('requisicion as r', 'r.id', '=', 'oc.requisicion_id')
            ->leftJoin(DB::raw('(
                SELECT requisicion_id, MAX(fecha) AS fecha_aprobacion
                FROM requisicion_estado
                WHERE estado = \'APROBADA\'
                GROUP BY requisicion_id
            ) AS reap'), 'reap.requisicion_id', '=', 'oc.requisicion_id')
            ->leftJoin(DB::raw('(
                SELECT
                    rpa.ordencompra_articulo_id,
                    SUM(CASE rp.tipo
                        WHEN \''.Recepcion_Proveedor::TIPO_RECEPCION.'\' THEN rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)
                        WHEN \''.Recepcion_Proveedor::TIPO_DEVOLUCION.'\' THEN -(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0))
                        ELSE 0 END) AS cantidad_entregada
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE rp.estado = \''.Recepcion_Proveedor::ESTADO_CONFIRMADA.'\'
                  AND rp.tipo IN (\''.Recepcion_Proveedor::TIPO_RECEPCION.'\',\''.Recepcion_Proveedor::TIPO_DEVOLUCION.'\')
                  AND rpa.ordencompra_articulo_id IS NOT NULL
                GROUP BY rpa.ordencompra_articulo_id
            ) AS ent'), 'ent.ordencompra_articulo_id', '=', 'oa.id')
            ->leftJoin(DB::raw('(
                SELECT
                    rpa.ordencompra_articulo_id,
                    MIN(rp.id) AS recepcion_id,
                    MIN(rp.numerorecepcion) AS numero_recepcion,
                    MIN(rp.fecha) AS fecha_recepcion
                FROM recepcion_proveedor_articulo rpa
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE rp.estado = \''.Recepcion_Proveedor::ESTADO_CONFIRMADA.'\'
                  AND rp.tipo = \''.Recepcion_Proveedor::TIPO_RECEPCION.'\'
                  AND rpa.ordencompra_articulo_id IS NOT NULL
                GROUP BY rpa.ordencompra_articulo_id
            ) AS rec1'), 'rec1.ordencompra_articulo_id', '=', 'oa.id')
            ->leftJoin(DB::raw('(
                SELECT
                    cp.ordencompra_id,
                    MIN(cp.id) AS comprobante_id,
                    MIN(cp.numerocomprobante) AS numero_factura,
                    MIN(cp.letra) AS letra_factura,
                    MIN(cp.sucursal) AS sucursal_factura,
                    MIN(cp.fechacomprobante) AS fecha_factura,
                    MIN(cp.total) AS total_factura
                FROM comprobante_proveedor cp
                WHERE cp.ordencompra_id IS NOT NULL
                GROUP BY cp.ordencompra_id
            ) AS fac1'), 'fac1.ordencompra_id', '=', 'oc.id')
            ->select([
                'oa.id as linea_id',
                'oa.ordencompra_id',
                'oa.articulo_id',
                'oa.cantidad',
                'oa.cantidadalternativa',
                'oa.precio',
                'oa.detalle as leyenda_linea',
                'oa.fechaentrega as fecha_entrega_linea',
                'oa.estado_linea_oc',
                'oa.centrocostodestino_id',
                'oa.partidagasto_id',
                'oa.capex_id',
                'oc.numeroordencompra',
                'oc.fecha',
                'oc.fechaentrega',
                'oc.estadoordencompra',
                'oc.tratamiento',
                'oc.detalle as leyenda_cabecera',
                'oc.comentario',
                'oc.empresa_id',
                'oc.centrocosto_id',
                'oc.creousuario_id',
                'oc.proveedor_id',
                'oc.requisicion_id',
                'r.numerorequisicion',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'cat.codigo as agrupacion_codigo',
                'umd.abreviatura as umd',
                'm.abreviatura as moneda',
                'cc.codigo as centrocosto_codigo',
                'cc.nombre as centrocosto_nombre',
                'ccd.codigo as centrocosto_destino_codigo',
                'u.nombre as usuario_nombre',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'cp.nombre as condicionpago_nombre',
                'cap.codigo as capex_codigo',
                'cap.codigoproyecto as capex_codigoproyecto',
                'cap.nombre as capex_nombre',
                'pg.codigo as partidagasto_codigo',
                'pg.detalle as partidagasto_detalle',
                'cta.codigo as cuenta_codigo',
                'cta.nombre as cuenta_nombre',
                'reap.fecha_aprobacion',
                'rec1.recepcion_id',
                'rec1.numero_recepcion',
                'rec1.fecha_recepcion',
                'fac1.comprobante_id',
                'fac1.numero_factura',
                'fac1.letra_factura',
                'fac1.sucursal_factura',
                'fac1.fecha_factura',
                'fac1.total_factura',
                DB::raw($sqlEntregado.' as cantidad_entregada'),
                'e.nombre as nombreempresa',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'oc.empresa_id');

        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        if ($empresaIds !== []) {
            $query->whereIn('oc.empresa_id', $empresaIds);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('oc.fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('oc.fecha', '<=', $filtros['fecha_hasta']);
        }

        RequisicionReporteCriteriosSupport::aplicarFiltroNumerosRequisicion(
            $query,
            (string) ($filtros['ordencompra_desde'] ?? ''),
            (string) ($filtros['ordencompra_hasta'] ?? ''),
            'oc.numeroordencompra',
        );

        RequisicionReporteCriteriosSupport::aplicarFiltroUsuarios(
            $query,
            (string) ($filtros['usuarios'] ?? ''),
            'oc.creousuario_id',
        );

        RequisicionReporteCriteriosSupport::aplicarFiltroCentrocostosCodigo(
            $query,
            (string) ($filtros['centrocostos_codigo'] ?? ''),
            'cc.codigo',
            'ccd.codigo',
        );

        OrdencompraReporteCriteriosSupport::aplicarFiltroProveedoresCodigo(
            $query,
            (string) ($filtros['proveedores'] ?? ''),
        );

        OrdencompraReporteFiltros::aplicarEstadoOc(
            $query,
            (string) ($filtros['estado_oc'] ?? OrdencompraReporteFiltros::ESTADO_ACTIVOS),
        );

        OrdencompraReporteFiltros::aplicarAnticipada(
            $query,
            (string) ($filtros['anticipada'] ?? OrdencompraReporteFiltros::ANTICIPADA_TODAS),
        );

        $this->aplicarFiltroPendiente(
            $query,
            (string) ($filtros['pendiente'] ?? OrdencompraReporteFiltros::PENDIENTE_PENDIENTES),
            $sqlPendiente,
        );

        $this->aplicarOrden($query, $agrupacion);

        return $query->get();
    }

    private function aplicarFiltroPendiente(Builder $query, string $pendiente, string $sqlPendiente): void
    {
        $hoy = Carbon::now()->format('Y-m-d');

        switch ($pendiente) {
            case OrdencompraReporteFiltros::PENDIENTE_PENDIENTES:
                $query->whereRaw($sqlPendiente.' > 0.009');
                break;
            case OrdencompraReporteFiltros::PENDIENTE_PENDIENTES_EXCEDIDOS:
                $query->where(function (Builder $sub) use ($sqlPendiente, $hoy) {
                    $sub->whereRaw($sqlPendiente.' > 0.009')
                        ->orWhere(function (Builder $ex) use ($sqlPendiente, $hoy) {
                            $ex->whereRaw($sqlPendiente.' > 0.009')
                                ->whereRaw('COALESCE(oa.fechaentrega, oc.fechaentrega) < ?', [$hoy]);
                        });
                });
                break;
            case OrdencompraReporteFiltros::PENDIENTE_RECEPCIONADAS:
                $query->whereRaw($sqlPendiente.' <= 0.009');
                break;
        }
    }

    private function aplicarOrden(Builder $query, string $agrupacion): void
    {
        switch ($agrupacion) {
            case OrdencompraReporteFiltros::AGRUPACION_ARTICULO:
                $query->orderBy('a.sku')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR:
                $query->orderBy('p.codigo')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR_PEDIDO:
                $query->orderBy('p.codigo')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_REQUISICION:
                $query->orderBy('r.numerorequisicion')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_PARTIDA:
                $query->orderBy('pg.codigo')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_CAPEX:
                $query->orderBy('cap.codigo')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            case OrdencompraReporteFiltros::AGRUPACION_AGRUPACION:
                $query->orderBy('cat.codigo')->orderBy('a.sku')->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
            default:
                $query->orderBy('oc.numeroordencompra')->orderBy('oa.id');
                break;
        }
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(Collection $lineas, array $filtros): array
    {
        if ($lineas->isEmpty()) {
            return [];
        }

        $agrupacion = (string) ($filtros['agrupacion'] ?? OrdencompraReporteFiltros::AGRUPACION_PEDIDO);
        $soloTotales = ($filtros['modo_listado'] ?? OrdencompraReporteFiltros::MODO_MOVIMIENTOS)
            === OrdencompraReporteFiltros::MODO_TOTALES;

        $filas = [];
        $grupoActual = null;
        $subCantidad = 0.0;
        $subEntregado = 0.0;
        $subPendiente = 0.0;
        $subFacturado = 0.0;
        $subPendFact = 0.0;
        $subImportePend = 0.0;
        $subImporteOc = 0.0;
        $metaGrupo = [];
        $grupoSecuencia = 0;

        $cerrarGrupo = function () use (
            &$filas,
            &$grupoActual,
            &$subCantidad,
            &$subEntregado,
            &$subPendiente,
            &$subFacturado,
            &$subPendFact,
            &$subImportePend,
            &$subImporteOc,
            &$metaGrupo,
            $agrupacion,
        ) {
            if ($grupoActual === null) {
                return;
            }

            $filas[] = $this->filaSubtotal(
                $agrupacion,
                $metaGrupo,
                $subCantidad,
                $subEntregado,
                $subPendiente,
                $subFacturado,
                $subPendFact,
                $subImportePend,
                $subImporteOc,
            );

            $grupoActual = null;
            $subCantidad = 0.0;
            $subEntregado = 0.0;
            $subPendiente = 0.0;
            $subFacturado = 0.0;
            $subPendFact = 0.0;
            $subImportePend = 0.0;
            $subImporteOc = 0.0;
            $metaGrupo = [];
        };

        foreach ($lineas as $row) {
            [$claveGrupo, $meta] = $this->resolverGrupo($row, $agrupacion);

            if ($grupoActual !== null && $grupoActual !== $claveGrupo) {
                $cerrarGrupo();
            }

            if ($grupoActual !== $claveGrupo) {
                $grupoActual = $claveGrupo;
                $metaGrupo = $meta;
                $grupoSecuencia++;

                if (! $soloTotales) {
                    $filas[] = $this->filaCabecera($agrupacion, $meta, $grupoSecuencia);
                }
            }

            $cantidad = (float) ($row->cantidad ?? 0);
            $entregado = min($cantidad, max(0, (float) ($row->cantidad_entregada ?? 0)));
            $pendiente = max(0, $cantidad - $entregado);
            $facturado = $entregado;
            $pendFact = max(0, $cantidad - $facturado);
            $precio = (float) ($row->precio ?? 0);
            $importePend = $pendiente * $precio;
            $importeOc = $cantidad * $precio;

            $subCantidad += $cantidad;
            $subEntregado += $entregado;
            $subPendiente += $pendiente;
            $subFacturado += $facturado;
            $subPendFact += $pendFact;
            $subImportePend += $importePend;
            $subImporteOc += $importeOc;

            if (! $soloTotales) {
                $filas[] = $this->mapearFilaDetalle(
                    $row,
                    $cantidad,
                    $entregado,
                    $pendiente,
                    $facturado,
                    $pendFact,
                    $precio,
                    $importePend,
                    $importeOc,
                    $grupoSecuencia,
                );
            }
        }

        $cerrarGrupo();

        $totales = $this->totalesDesdeLineas($lineas);
        $filas[] = array_merge(['tipo_fila' => 'total_general'], $totales);

        return $filas;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolverGrupo(object $row, string $agrupacion): array
    {
        return match ($agrupacion) {
            OrdencompraReporteFiltros::AGRUPACION_ARTICULO => [
                'art:'.(int) ($row->articulo_id ?? 0),
                [
                    'articulo_id' => (int) ($row->articulo_id ?? 0),
                    'sku' => (string) ($row->sku ?? ''),
                    'articulo_descripcion' => (string) ($row->articulo_descripcion ?? ''),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR => [
                'prov:'.(int) ($row->proveedor_id ?? 0),
                [
                    'proveedor_id' => (int) ($row->proveedor_id ?? 0),
                    'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
                    'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR_PEDIDO => [
                'provped:'.(int) ($row->proveedor_id ?? 0).':'.(int) ($row->ordencompra_id ?? 0),
                [
                    'proveedor_id' => (int) ($row->proveedor_id ?? 0),
                    'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
                    'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
                    'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
                    'numeroordencompra' => (int) ($row->numeroordencompra ?? 0),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_REQUISICION => [
                'req:'.(int) ($row->numerorequisicion ?? 0),
                [
                    'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
                    'requisicion_id' => (int) ($row->requisicion_id ?? 0),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_PARTIDA => [
                'pg:'.(int) ($row->partidagasto_id ?? 0),
                [
                    'partidagasto_id' => (int) ($row->partidagasto_id ?? 0),
                    'partidagasto_codigo' => (string) ($row->partidagasto_codigo ?? ''),
                    'partidagasto_detalle' => (string) ($row->partidagasto_detalle ?? ''),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_CAPEX => [
                'cap:'.(int) ($row->capex_id ?? 0),
                [
                    'capex_id' => (int) ($row->capex_id ?? 0),
                    'capex_codigo' => (string) ($row->capex_codigo ?? ''),
                    'capex_nombre' => (string) ($row->capex_nombre ?? ''),
                ],
            ],
            OrdencompraReporteFiltros::AGRUPACION_AGRUPACION => [
                'agr:'.(string) ($row->agrupacion_codigo ?? ''),
                [
                    'agrupacion_codigo' => (string) ($row->agrupacion_codigo ?? ''),
                ],
            ],
            default => [
                'oc:'.(int) ($row->ordencompra_id ?? 0),
                [
                    'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
                    'numeroordencompra' => (int) ($row->numeroordencompra ?? 0),
                    'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
                ],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function filaCabecera(string $agrupacion, array $meta, int $grupoSecuencia): array
    {
        return match ($agrupacion) {
            OrdencompraReporteFiltros::AGRUPACION_ARTICULO => [
                'tipo_fila' => 'cabecera_articulo',
                'grupo_id' => $grupoSecuencia,
                'articulo_id' => $meta['articulo_id'] ?? 0,
                'sku' => $meta['sku'] ?? '',
                'articulo_descripcion' => $meta['articulo_descripcion'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR => [
                'tipo_fila' => 'cabecera_proveedor',
                'grupo_id' => $grupoSecuencia,
                'proveedor_id' => $meta['proveedor_id'] ?? 0,
                'proveedor_codigo' => $meta['proveedor_codigo'] ?? '',
                'proveedor_nombre' => $meta['proveedor_nombre'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR_PEDIDO => [
                'tipo_fila' => 'cabecera_proveedor_pedido',
                'grupo_id' => $grupoSecuencia,
                'proveedor_codigo' => $meta['proveedor_codigo'] ?? '',
                'proveedor_nombre' => $meta['proveedor_nombre'] ?? '',
                'ordencompra_id' => $meta['ordencompra_id'] ?? 0,
                'numeroordencompra' => $meta['numeroordencompra'] ?? 0,
            ],
            OrdencompraReporteFiltros::AGRUPACION_REQUISICION => [
                'tipo_fila' => 'cabecera_requisicion',
                'grupo_id' => $grupoSecuencia,
                'numerorequisicion' => $meta['numerorequisicion'] ?? 0,
                'requisicion_id' => $meta['requisicion_id'] ?? 0,
            ],
            OrdencompraReporteFiltros::AGRUPACION_PARTIDA => [
                'tipo_fila' => 'cabecera_partida',
                'grupo_id' => $grupoSecuencia,
                'partidagasto_codigo' => $meta['partidagasto_codigo'] ?? '',
                'partidagasto_detalle' => $meta['partidagasto_detalle'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_CAPEX => [
                'tipo_fila' => 'cabecera_capex',
                'grupo_id' => $grupoSecuencia,
                'capex_id' => $meta['capex_id'] ?? 0,
                'capex_codigo' => $meta['capex_codigo'] ?? '',
                'capex_nombre' => $meta['capex_nombre'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_AGRUPACION => [
                'tipo_fila' => 'cabecera_agrupacion',
                'grupo_id' => $grupoSecuencia,
                'agrupacion_codigo' => $meta['agrupacion_codigo'] ?? '',
            ],
            default => [
                'tipo_fila' => 'cabecera_pedido',
                'grupo_id' => $grupoSecuencia,
                'ordencompra_id' => $meta['ordencompra_id'] ?? 0,
                'numeroordencompra' => $meta['numeroordencompra'] ?? 0,
                'proveedor_nombre' => $meta['proveedor_nombre'] ?? '',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function filaSubtotal(
        string $agrupacion,
        array $meta,
        float $subCantidad,
        float $subEntregado,
        float $subPendiente,
        float $subFacturado,
        float $subPendFact,
        float $subImportePend,
        float $subImporteOc,
    ): array {
        $base = [
            'total_cantidad' => $subCantidad,
            'total_entregado' => $subEntregado,
            'total_pendiente' => $subPendiente,
            'total_facturado' => $subFacturado,
            'total_pendiente_fact' => $subPendFact,
            'total_importe_pendiente' => $subImportePend,
            'total_importe_oc' => $subImporteOc,
        ];

        $etiqueta = match ($agrupacion) {
            OrdencompraReporteFiltros::AGRUPACION_ARTICULO => [
                'tipo_fila' => 'subtotal_articulo',
                'sku' => $meta['sku'] ?? '',
                'articulo_descripcion' => $meta['articulo_descripcion'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR => [
                'tipo_fila' => 'subtotal_proveedor',
                'proveedor_codigo' => $meta['proveedor_codigo'] ?? '',
                'proveedor_nombre' => $meta['proveedor_nombre'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_PROVEEDOR_PEDIDO => [
                'tipo_fila' => 'subtotal_proveedor_pedido',
                'proveedor_codigo' => $meta['proveedor_codigo'] ?? '',
                'numeroordencompra' => $meta['numeroordencompra'] ?? 0,
            ],
            OrdencompraReporteFiltros::AGRUPACION_REQUISICION => [
                'tipo_fila' => 'subtotal_requisicion',
                'numerorequisicion' => $meta['numerorequisicion'] ?? 0,
            ],
            OrdencompraReporteFiltros::AGRUPACION_PARTIDA => [
                'tipo_fila' => 'subtotal_partida',
                'partidagasto_codigo' => $meta['partidagasto_codigo'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_CAPEX => [
                'tipo_fila' => 'subtotal_capex',
                'capex_codigo' => $meta['capex_codigo'] ?? '',
            ],
            OrdencompraReporteFiltros::AGRUPACION_AGRUPACION => [
                'tipo_fila' => 'subtotal_agrupacion',
                'agrupacion_codigo' => $meta['agrupacion_codigo'] ?? '',
            ],
            default => [
                'tipo_fila' => 'subtotal_pedido',
                'numeroordencompra' => $meta['numeroordencompra'] ?? 0,
            ],
        };

        return array_merge($base, $etiqueta);
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @return array<string, float|int>
     */
    public function totalesDesdeLineas(Collection $lineas): array
    {
        $totalCantidad = 0.0;
        $totalEntregado = 0.0;
        $totalFacturado = 0.0;
        $totalImportePend = 0.0;
        $totalImporteOc = 0.0;

        foreach ($lineas as $row) {
            $cantidad = (float) ($row->cantidad ?? 0);
            $entregado = min($cantidad, max(0, (float) ($row->cantidad_entregada ?? 0)));
            $pendiente = max(0, $cantidad - $entregado);
            $precio = (float) ($row->precio ?? 0);
            $totalCantidad += $cantidad;
            $totalEntregado += $entregado;
            $totalFacturado += $entregado;
            $totalImportePend += $pendiente * $precio;
            $totalImporteOc += $cantidad * $precio;
        }

        return [
            'total_cantidad' => $totalCantidad,
            'total_entregado' => $totalEntregado,
            'total_pendiente' => max(0, $totalCantidad - $totalEntregado),
            'total_facturado' => $totalFacturado,
            'total_pendiente_fact' => max(0, $totalCantidad - $totalFacturado),
            'total_importe_pendiente' => $totalImportePend,
            'total_importe_oc' => $totalImporteOc,
            'total_ordenes' => (int) $lineas->pluck('numeroordencompra')->unique()->count(),
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

        $partes[] = 'Período: '.OrdencompraReporteFiltros::formatearPeriodoTexto($filtros);
        $partes[] = OrdencompraReporteFiltros::subtituloEstado(
            (string) ($filtros['estado_oc'] ?? OrdencompraReporteFiltros::ESTADO_ACTIVOS),
        );
        $partes[] = OrdencompraReporteFiltros::subtituloPendiente(
            (string) ($filtros['pendiente'] ?? OrdencompraReporteFiltros::PENDIENTE_PENDIENTES),
        );
        $partes[] = 'Agrupación: '.OrdencompraReporteFiltros::etiquetaAgrupacion(
            (string) ($filtros['agrupacion'] ?? OrdencompraReporteFiltros::AGRUPACION_PEDIDO),
        );
        $partes[] = OrdencompraReporteFiltros::etiquetaModoListado(
            (string) ($filtros['modo_listado'] ?? OrdencompraReporteFiltros::MODO_MOVIMIENTOS),
        );

        foreach ([
            OrdencompraReporteCriteriosSupport::subtituloOrdenescompra($filtros),
            OrdencompraReporteCriteriosSupport::subtituloProveedores($filtros),
            RequisicionReporteCriteriosSupport::subtituloUsuarios($filtros),
            RequisicionReporteCriteriosSupport::subtituloCentrocostos($filtros),
        ] as $sub) {
            if ($sub) {
                $partes[] = $sub;
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearFilaDetalle(
        object $row,
        float $cantidad,
        float $entregado,
        float $pendiente,
        float $facturado,
        float $pendFact,
        float $precio,
        float $importePend,
        float $importeOc,
        int $grupoSecuencia,
    ): array {
        $leyenda = trim((string) ($row->leyenda_linea ?? ''));
        if ($leyenda === '') {
            $leyenda = trim((string) ($row->leyenda_cabecera ?? ''));
        }

        $fechaAut = $row->fecha_aprobacion ?? null;
        $fechaOc = $row->fecha ?? null;
        $difDias = null;
        if ($fechaAut && $fechaOc) {
            try {
                $difDias = abs(Carbon::parse($fechaOc)->diffInDays(Carbon::parse($fechaAut)));
            } catch (\Throwable) {
                $difDias = null;
            }
        }

        $importeRecep = $entregado * $precio;
        $saldoPendRecep = max(0, $cantidad - $entregado) * $precio;
        $importeFact = $facturado * $precio;
        $saldoPendFact = max(0, $cantidad - $facturado) * $precio;

        $nroFactura = '';
        if (! empty($row->numero_factura)) {
            $letra = trim((string) ($row->letra_factura ?? ''));
            $suc = (int) ($row->sucursal_factura ?? 0);
            $nro = (int) ($row->numero_factura ?? 0);
            $nroFactura = trim($letra.($suc > 0 ? sprintf('%05d', $suc) : '').'-'.sprintf('%08d', $nro));
        }

        $motivoCierre = '';
        if ((string) ($row->estadoordencompra ?? '') === OrdencompraEstados::SUSPENDIDA) {
            $motivoCierre = trim((string) ($row->comentario ?? ''));
        } elseif (! empty($row->estado_linea_oc)) {
            $motivoCierre = (string) $row->estado_linea_oc;
        }

        return [
            'tipo_fila' => 'detalle',
            'grupo_id' => $grupoSecuencia,
            'linea_id' => (int) ($row->linea_id ?? 0),
            'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
            'requisicion_id' => (int) ($row->requisicion_id ?? 0),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'centrocosto_id' => (int) ($row->centrocosto_id ?? 0),
            'centrocostodestino_id' => (int) ($row->centrocostodestino_id ?? 0),
            'capex_id' => (int) ($row->capex_id ?? 0),
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'recepcion_id' => (int) ($row->recepcion_id ?? 0),
            'comprobante_id' => (int) ($row->comprobante_id ?? 0),
            'sku' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->articulo_descripcion ?? ''),
            'tipo_comprobante' => 'ORD',
            'numeroordencompra' => (int) ($row->numeroordencompra ?? 0),
            'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
            'fecha_aprobacion_req' => $fechaAut,
            'dif_dias' => $difDias,
            'fecha' => $fechaOc,
            'fecha_entrega' => $row->fecha_entrega_linea ?? $row->fechaentrega ?? null,
            'cantidad' => $cantidad,
            'entregado' => $entregado,
            'pendiente' => $pendiente,
            'facturado' => $facturado,
            'pendiente_fact' => $pendFact,
            'importe' => $precio,
            'total_pendiente' => $importePend,
            'total_oc' => $importeOc,
            'numero_recepcion' => (int) ($row->numero_recepcion ?? 0),
            'importe_recepcion' => $importeRecep,
            'fecha_recepcion' => $row->fecha_recepcion ?? null,
            'saldo_pendiente_recepcion' => $saldoPendRecep,
            'numero_factura' => $nroFactura,
            'importe_factura' => $importeFact,
            'fecha_factura' => $row->fecha_factura ?? null,
            'saldo_pendiente_factura' => $saldoPendFact,
            'cuenta_codigo' => (string) ($row->cuenta_codigo ?? ''),
            'cuenta_nombre' => (string) ($row->cuenta_nombre ?? ''),
            'centrocosto_codigo' => (string) ($row->centrocosto_codigo ?? ''),
            'centrocosto_nombre' => (string) ($row->centrocosto_nombre ?? ''),
            'centrocosto_destino_codigo' => (string) ($row->centrocosto_destino_codigo ?? ''),
            'capex_codigoproyecto' => (string) ($row->capex_codigoproyecto ?? ''),
            'capex_nombre' => (string) ($row->capex_nombre ?? ''),
            'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
            'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
            'leyenda' => $leyenda,
            'motivo_cierre' => $motivoCierre,
            'usuario_nombre' => (string) ($row->usuario_nombre ?? ''),
            'condicionpago_nombre' => (string) ($row->condicionpago_nombre ?? ''),
            'estado' => (string) ($row->estadoordencompra ?? ''),
            'moneda' => (string) ($row->moneda ?? ''),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
        ];
    }
}
