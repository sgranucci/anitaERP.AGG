<?php

namespace App\Services\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Compras\RequisicionReporteFiltros;
use App\Support\Compras\RequisicionVisibilidadSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisicionReporteService
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array{
     *         total_cantidad: float,
     *         total_entregado: float,
     *         total_pendiente: float,
     *         total_importe: float,
     *         total_requisiciones: int
     *     }
     * }
     */
    public function generar(array $filtros): array
    {
        $lineas = $this->consultarLineas($filtros);
        $filas = $this->aplanarFilas($lineas, $filtros);
        $totales = $this->totalesDesdeLineas($lineas);

        return [
            'filas' => $filas,
            'totales' => $totales,
        ];
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
        $agrupacion = (string) ($filtros['agrupacion'] ?? RequisicionReporteFiltros::AGRUPACION_USUARIO);

        $query = DB::table('requisicion_articulo as ra')
            ->join('requisicion as r', 'r.id', '=', 'ra.requisicion_id')
            ->join('empresa as e', 'e.id', '=', 'r.empresa_id')
            ->join('centrocosto as cc', 'cc.id', '=', 'r.centrocosto_id')
            ->join('centrocosto as ccd', 'ccd.id', '=', 'ra.centrocostodestino_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'ra.articulo_id')
            ->leftJoin('categoria as cat', 'cat.id', '=', 'a.categoria_id')
            ->leftJoin('unidadmedida as umd', 'umd.id', '=', 'a.unidadmedida_id')
            ->leftJoin('unidadmedida as umd_alt', 'umd_alt.id', '=', 'a.unidadmedidaalternativa_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'ra.moneda_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'r.creousuario_id')
            ->leftJoin('proveedor as p_req', 'p_req.id', '=', 'r.proveedor_id')
            ->leftJoin('capex as cap', 'cap.id', '=', 'ra.capex_id')
            ->leftJoin(DB::raw('(
                SELECT requisicion_id, MAX(fecha) AS fecha_aprobacion
                FROM requisicion_estado
                WHERE estado = \'APROBADA\'
                GROUP BY requisicion_id
            ) AS reap'), 'reap.requisicion_id', '=', 'r.id')
            ->leftJoin(DB::raw('(
                SELECT
                    oa.requisicion_articulo_id,
                    MIN(oc.numeroordencompra) AS numeroordencompra,
                    MIN(oc.id) AS ordencompra_id,
                    MIN(oc.proveedor_id) AS proveedor_oc_id
                FROM ordencompra_articulo oa
                INNER JOIN ordencompra oc ON oc.id = oa.ordencompra_id
                WHERE oa.requisicion_articulo_id IS NOT NULL
                GROUP BY oa.requisicion_articulo_id
            ) AS oc_lin'), 'oc_lin.requisicion_articulo_id', '=', 'ra.id')
            ->leftJoin('proveedor as p_oc', 'p_oc.id', '=', 'oc_lin.proveedor_oc_id')
            ->leftJoin(DB::raw('(
                SELECT
                    oa.requisicion_articulo_id,
                    SUM(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) AS cantidad_entregada
                FROM ordencompra_articulo oa
                INNER JOIN recepcion_proveedor_articulo rpa ON rpa.ordencompra_articulo_id = oa.id
                INNER JOIN recepcion_proveedor rp ON rp.id = rpa.recepcion_proveedor_id
                WHERE oa.requisicion_articulo_id IS NOT NULL
                  AND rp.estado = \''.Recepcion_Proveedor::ESTADO_CONFIRMADA.'\'
                  AND rp.tipo = \''.Recepcion_Proveedor::TIPO_RECEPCION.'\'
                GROUP BY oa.requisicion_articulo_id
            ) AS ent'), 'ent.requisicion_articulo_id', '=', 'ra.id')
            ->select([
                'ra.id as linea_id',
                'ra.requisicion_id',
                'ra.articulo_id',
                'ra.cantidad',
                'ra.cantidadalternativa',
                'ra.precio',
                'ra.preciooriginal',
                'ra.motivoahorro',
                'ra.detalle as leyenda_linea',
                'ra.fechaentrega as fecha_entrega_linea',
                'r.numerorequisicion',
                'r.fecha',
                'r.fechaentrega',
                'r.estado',
                'r.tratamiento',
                'r.motivotratamiento',
                'r.contrataciondirecta',
                'r.detalle as leyenda_cabecera',
                'r.empresa_id',
                'r.centrocosto_id',
                'r.creousuario_id',
                'a.sku',
                'a.descripcion as articulo_descripcion',
                'cat.codigo as agrupacion_codigo',
                'umd.abreviatura as umd',
                'umd_alt.abreviatura as umd_alternativa',
                'm.abreviatura as moneda',
                'cc.codigo as centrocosto_codigo',
                'ccd.codigo as centrocosto_destino_codigo',
                'ccd.id as centrocostodestino_id',
                'u.nombre as usuario_nombre',
                'p_req.codigo as proveedor_codigo',
                'p_req.nombre as proveedor_nombre',
                'p_oc.nombre as proveedor_oc_nombre',
                'cap.codigo as capex_codigo',
                'cap.codigoproyecto as capex_codigoproyecto',
                'reap.fecha_aprobacion',
                'oc_lin.numeroordencompra',
                'oc_lin.ordencompra_id',
                DB::raw('COALESCE(ent.cantidad_entregada, 0) as cantidad_entregada'),
                'e.nombre as nombreempresa',
            ]);

        $this->aplicarVisibilidad($query);
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'r.empresa_id');

        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
        if ($empresaIds !== []) {
            $query->whereIn('r.empresa_id', $empresaIds);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->where('r.fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->where('r.fecha', '<=', $filtros['fecha_hasta']);
        }

        RequisicionReporteCriteriosSupport::aplicarFiltroNumerosRequisicion(
            $query,
            (string) ($filtros['requisicion_desde'] ?? ''),
            (string) ($filtros['requisicion_hasta'] ?? ''),
        );

        RequisicionReporteCriteriosSupport::aplicarFiltroUsuarios(
            $query,
            (string) ($filtros['usuarios'] ?? ''),
        );

        RequisicionReporteCriteriosSupport::aplicarFiltroCentrocostosCodigo(
            $query,
            (string) ($filtros['centrocostos_codigo'] ?? ''),
        );

        RequisicionReporteFiltros::aplicarEstadoRequisicion(
            $query,
            (string) ($filtros['estado_requisicion'] ?? RequisicionReporteFiltros::ESTADO_TODOS),
        );

        RequisicionReporteFiltros::aplicarUrgente(
            $query,
            (string) ($filtros['urgente'] ?? RequisicionReporteFiltros::URGENTE_TODAS),
        );

        RequisicionReporteFiltros::aplicarContratacion(
            $query,
            (string) ($filtros['contratacion'] ?? RequisicionReporteFiltros::CONTRATACION_TODAS),
        );

        $query->where('r.estado', '!=', 'V');

        $this->aplicarOrden($query, $agrupacion);

        return $query->get();
    }

    private function aplicarOrden(Builder $query, string $agrupacion): void
    {
        switch ($agrupacion) {
            case RequisicionReporteFiltros::AGRUPACION_ARTICULO:
                $query->orderBy('a.sku')->orderBy('r.numerorequisicion')->orderBy('ra.id');
                break;
            case RequisicionReporteFiltros::AGRUPACION_CENTROCOSTO:
                $query->orderBy('ccd.codigo')->orderBy('r.numerorequisicion')->orderBy('ra.id');
                break;
            case RequisicionReporteFiltros::AGRUPACION_REQUISICION:
                $query->orderBy('r.numerorequisicion')->orderBy('ra.id');
                break;
            default:
                $query->orderBy('r.creousuario_id')->orderBy('u.nombre')->orderBy('r.numerorequisicion')->orderBy('ra.id');
                break;
        }
    }

    private function aplicarVisibilidad(Builder $query): void
    {
        if (RequisicionVisibilidadSupport::puedeVerTodasSinRestriccion()) {
            return;
        }

        $empresas = RequisicionVisibilidadSupport::empresaIdsAsignadas();
        if ($empresas !== []) {
            $query->whereIn('r.empresa_id', $empresas);
        }

        if (RequisicionVisibilidadSupport::esUsuarioCompras()) {
            if (config('requisicion.filtro_oficina_compras_activo', false)) {
                $oficinaCompraId = Auth::user()->oficinacompra_id ?? null;
                if ($oficinaCompraId) {
                    $query->where('r.oficinacompra_id', $oficinaCompraId);
                }
            }

            return;
        }

        if (RequisicionVisibilidadSupport::esUsuarioRestoSectores()) {
            $centrocostoId = RequisicionVisibilidadSupport::centrocostoOrigenUsuario();
            if ($centrocostoId !== null) {
                $query->where('r.centrocosto_id', $centrocostoId);

                return;
            }
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where('r.creousuario_id', $usuarioId);
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

        $agrupacion = (string) ($filtros['agrupacion'] ?? RequisicionReporteFiltros::AGRUPACION_USUARIO);
        $soloTotales = ($filtros['modo_listado'] ?? RequisicionReporteFiltros::MODO_MOVIMIENTOS)
            === RequisicionReporteFiltros::MODO_TOTALES;

        $filas = [];
        $grupoActual = null;
        $subCantidad = 0.0;
        $subEntregado = 0.0;
        $subPendiente = 0.0;
        $subImporte = 0.0;
        $metaGrupo = [];
        $grupoSecuencia = 0;

        $cerrarGrupo = function () use (
            &$filas,
            &$grupoActual,
            &$subCantidad,
            &$subEntregado,
            &$subPendiente,
            &$subImporte,
            &$metaGrupo,
            $agrupacion,
        ) {
            if ($grupoActual === null) {
                return;
            }

            $filas[] = $this->filaSubtotal($agrupacion, $metaGrupo, $grupoActual, $subCantidad, $subEntregado, $subPendiente, $subImporte);

            $grupoActual = null;
            $subCantidad = 0.0;
            $subEntregado = 0.0;
            $subPendiente = 0.0;
            $subImporte = 0.0;
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
            $entregado = min($cantidad, (float) ($row->cantidad_entregada ?? 0));
            $pendiente = max(0, $cantidad - $entregado);
            $importe = (float) ($row->precio ?? 0);
            $total = $importe * $cantidad;

            $subCantidad += $cantidad;
            $subEntregado += $entregado;
            $subPendiente += $pendiente;
            $subImporte += $total;

            if (! $soloTotales) {
                $filas[] = $this->mapearFilaDetalle($row, $cantidad, $entregado, $pendiente, $importe, $total, $grupoSecuencia);
            }
        }

        $cerrarGrupo();

        $totales = $this->totalesDesdeLineas($lineas);
        $filas[] = [
            'tipo_fila' => 'total_general',
            'total_cantidad' => $totales['total_cantidad'],
            'total_entregado' => $totales['total_entregado'],
            'total_pendiente' => $totales['total_pendiente'],
            'total_importe' => $totales['total_importe'],
        ];

        return $filas;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolverGrupo(object $row, string $agrupacion): array
    {
        return match ($agrupacion) {
            RequisicionReporteFiltros::AGRUPACION_ARTICULO => [
                'art:'.(int) ($row->articulo_id ?? 0),
                [
                    'articulo_id' => (int) ($row->articulo_id ?? 0),
                    'sku' => (string) ($row->sku ?? ''),
                    'articulo_descripcion' => (string) ($row->articulo_descripcion ?? ''),
                ],
            ],
            RequisicionReporteFiltros::AGRUPACION_CENTROCOSTO => [
                'cc:'.(int) ($row->centrocostodestino_id ?? 0),
                [
                    'centrocostodestino_id' => (int) ($row->centrocostodestino_id ?? 0),
                    'centrocosto_destino_codigo' => (string) ($row->centrocosto_destino_codigo ?? ''),
                ],
            ],
            RequisicionReporteFiltros::AGRUPACION_REQUISICION => [
                'req:'.(int) ($row->numerorequisicion ?? 0),
                [
                    'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
                    'requisicion_id' => (int) ($row->requisicion_id ?? 0),
                ],
            ],
            default => [
                'usr:'.(int) ($row->creousuario_id ?? 0),
                [
                    'usuario_id' => (int) ($row->creousuario_id ?? 0),
                    'usuario_nombre' => trim((string) ($row->usuario_nombre ?? '')) ?: 'Inexistente',
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
            RequisicionReporteFiltros::AGRUPACION_ARTICULO => [
                'tipo_fila' => 'cabecera_articulo',
                'grupo_id' => $grupoSecuencia,
                'articulo_id' => $meta['articulo_id'] ?? 0,
                'sku' => $meta['sku'] ?? '',
                'articulo_descripcion' => $meta['articulo_descripcion'] ?? '',
            ],
            RequisicionReporteFiltros::AGRUPACION_CENTROCOSTO => [
                'tipo_fila' => 'cabecera_centrocosto',
                'grupo_id' => $grupoSecuencia,
                'centrocostodestino_id' => $meta['centrocostodestino_id'] ?? 0,
                'centrocosto_destino_codigo' => $meta['centrocosto_destino_codigo'] ?? '',
            ],
            RequisicionReporteFiltros::AGRUPACION_REQUISICION => [
                'tipo_fila' => 'cabecera_requisicion',
                'grupo_id' => $grupoSecuencia,
                'numerorequisicion' => $meta['numerorequisicion'] ?? 0,
                'requisicion_id' => $meta['requisicion_id'] ?? 0,
            ],
            default => [
                'tipo_fila' => 'cabecera_usuario',
                'grupo_id' => $grupoSecuencia,
                'usuario_id' => $meta['usuario_id'] ?? 0,
                'usuario_nombre' => $meta['usuario_nombre'] ?? '',
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
        string $claveGrupo,
        float $subCantidad,
        float $subEntregado,
        float $subPendiente,
        float $subImporte,
    ): array {
        $base = [
            'total_cantidad' => $subCantidad,
            'total_entregado' => $subEntregado,
            'total_pendiente' => $subPendiente,
            'total_importe' => $subImporte,
        ];

        return match ($agrupacion) {
            RequisicionReporteFiltros::AGRUPACION_ARTICULO => array_merge($base, [
                'tipo_fila' => 'subtotal_articulo',
                'articulo_id' => $meta['articulo_id'] ?? 0,
                'sku' => $meta['sku'] ?? '',
                'articulo_descripcion' => $meta['articulo_descripcion'] ?? '',
            ]),
            RequisicionReporteFiltros::AGRUPACION_CENTROCOSTO => array_merge($base, [
                'tipo_fila' => 'subtotal_centrocosto',
                'centrocostodestino_id' => $meta['centrocostodestino_id'] ?? 0,
                'centrocosto_destino_codigo' => $meta['centrocosto_destino_codigo'] ?? '',
            ]),
            RequisicionReporteFiltros::AGRUPACION_REQUISICION => array_merge($base, [
                'tipo_fila' => 'subtotal_requisicion',
                'numerorequisicion' => $meta['numerorequisicion'] ?? 0,
            ]),
            default => array_merge($base, [
                'tipo_fila' => 'subtotal_usuario',
                'usuario_id' => $meta['usuario_id'] ?? 0,
                'usuario_nombre' => $meta['usuario_nombre'] ?? '',
            ]),
        };
    }

    /**
     * @param  Collection<int, object>  $lineas
     * @return array{
     *     total_cantidad: float,
     *     total_entregado: float,
     *     total_pendiente: float,
     *     total_importe: float,
     *     total_requisiciones: int
     * }
     */
    public function totalesDesdeLineas(Collection $lineas): array
    {
        $totalCantidad = 0.0;
        $totalEntregado = 0.0;
        $totalImporte = 0.0;

        foreach ($lineas as $row) {
            $cantidad = (float) ($row->cantidad ?? 0);
            $entregado = min($cantidad, (float) ($row->cantidad_entregada ?? 0));
            $totalCantidad += $cantidad;
            $totalEntregado += $entregado;
            $totalImporte += (float) ($row->precio ?? 0) * $cantidad;
        }

        return [
            'total_cantidad' => $totalCantidad,
            'total_entregado' => $totalEntregado,
            'total_pendiente' => max(0, $totalCantidad - $totalEntregado),
            'total_importe' => $totalImporte,
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
                $partes[] = 'Empresas: '.implode(', ', $nombres);
            }
        }

        $partes[] = 'Período: '.RequisicionReporteFiltros::formatearPeriodoTexto($filtros);
        $partes[] = RequisicionReporteFiltros::subtituloEstado(
            (string) ($filtros['estado_requisicion'] ?? RequisicionReporteFiltros::ESTADO_TODOS),
        );
        $partes[] = 'Agrupación: '.RequisicionReporteFiltros::etiquetaAgrupacion(
            (string) ($filtros['agrupacion'] ?? RequisicionReporteFiltros::AGRUPACION_USUARIO),
        );
        $partes[] = RequisicionReporteFiltros::etiquetaModoListado(
            (string) ($filtros['modo_listado'] ?? RequisicionReporteFiltros::MODO_MOVIMIENTOS),
        );

        foreach ([
            RequisicionReporteCriteriosSupport::subtituloRequisiciones($filtros),
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
        float $importe,
        float $total,
        int $grupoSecuencia,
    ): array {
        $precioOriginal = (float) ($row->preciooriginal ?? 0);
        $montoAhorroUnit = $precioOriginal > $importe ? ($precioOriginal - $importe) : 0.0;
        $porcAhorro = $precioOriginal > 0.000001
            ? (($precioOriginal - $importe) / $precioOriginal) * 100
            : null;

        $estadoNombre = $this->nombreEstado((string) ($row->estado ?? ''));
        $leyenda = trim((string) ($row->leyenda_linea ?? ''));
        if ($leyenda === '') {
            $leyenda = trim((string) ($row->leyenda_cabecera ?? ''));
        }

        $capexProyecto = trim((string) ($row->capex_codigo ?? ''));
        $capexNumero = trim((string) ($row->capex_codigoproyecto ?? ''));
        $proyectoCapex = $capexProyecto !== '' || $capexNumero !== ''
            ? trim($capexProyecto.'/'.$capexNumero, '/')
            : '0/0';

        return [
            'tipo_fila' => 'detalle',
            'grupo_id' => $grupoSecuencia,
            'linea_id' => (int) ($row->linea_id ?? 0),
            'requisicion_id' => (int) ($row->requisicion_id ?? 0),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'centrocosto_id' => (int) ($row->centrocosto_id ?? 0),
            'centrocostodestino_id' => (int) ($row->centrocostodestino_id ?? 0),
            'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'usuario_id' => (int) ($row->creousuario_id ?? 0),
            'sku' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->articulo_descripcion ?? ''),
            'agrupacion' => (string) ($row->agrupacion_codigo ?? ''),
            'numerorequisicion' => (int) ($row->numerorequisicion ?? 0),
            'numeroordencompra' => (int) ($row->numeroordencompra ?? 0),
            'fecha' => $row->fecha ?? null,
            'fecha_entrega' => $row->fecha_entrega_linea ?? $row->fechaentrega ?? null,
            'umd' => (string) ($row->umd ?? ''),
            'cantidad' => $cantidad,
            'entregado' => $entregado,
            'pendiente' => $pendiente,
            'importe' => $importe,
            'total' => $total,
            'moneda' => (string) ($row->moneda ?? ''),
            'unidades' => (float) ($row->cantidadalternativa ?? 0),
            'umd_alternativa' => (string) ($row->umd_alternativa ?? ''),
            'estado' => $estadoNombre,
            'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
            'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
            'centrocosto_codigo' => (string) ($row->centrocosto_codigo ?? ''),
            'centrocosto_destino_codigo' => (string) ($row->centrocosto_destino_codigo ?? ''),
            'proyecto_capex' => $proyectoCapex,
            'leyenda' => $leyenda,
            'fecha_aprobacion' => $row->fecha_aprobacion ?? null,
            'proveedor_oc_nombre' => (string) ($row->proveedor_oc_nombre ?? ''),
            'usuario_nombre' => (string) ($row->usuario_nombre ?? ''),
            'urgente' => ((string) ($row->tratamiento ?? '')) === 'U' ? 'S' : 'N',
            'motivo_urgencia' => (string) ($row->motivotratamiento ?? ''),
            'precio_original' => $precioOriginal,
            'porc_ahorro' => $porcAhorro,
            'monto_ahorro' => $montoAhorroUnit > 0 ? $montoAhorroUnit * $cantidad : 0.0,
            'motivo_ahorro' => (string) ($row->motivoahorro ?? ''),
            'usuario_ahorro' => trim((string) ($row->motivoahorro ?? '')) !== ''
                ? (string) ($row->usuario_nombre ?? '')
                : '',
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
        ];
    }

    private function nombreEstado(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        return ucwords(strtolower($valor));
    }
}
