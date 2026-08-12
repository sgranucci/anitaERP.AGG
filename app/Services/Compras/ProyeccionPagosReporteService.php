<?php

namespace App\Services\Compras;

use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\ConceptoCashflowResolverSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\PropuestaPagoLineaPresentacionSupport;
use App\Support\Compras\ProyeccionPagosColumnasSupport;
use App\Support\Compras\ProyeccionPagosReporteFiltros;
use App\Support\Compras\ProyeccionPagosTramosSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proyección de pagos a proveedores (equivalente Anita l-proy.c) leyendo solo anitaERP.
 *
 * Deuda abierta = proveedor_cuentacorriente + aplicaciones hasta la fecha base.
 * La clasificación por tramos de vencimiento y los totales aprobado / pendiente /
 * a compensar / adelantos replican el informe original.
 */
class ProyeccionPagosReporteService
{
    /** @var list<string> Estados de comprobante que se consideran aprobados. */
    private const ESTADOS_APROBADOS = [
        ComprobanteProveedorEstados::APROBADO,
        ComprobanteProveedorEstados::CONTABILIZADO,
    ];

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private CotizacionQueryInterface $cotizacionQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, mixed>,
     *     columnas: list<array<string, mixed>>,
     *     tramos: array<string, mixed>,
     *     secciones: list<array<string, mixed>>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = $this->empresaIds($filtros);
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
     * @return array<string, mixed>
     */
    private function generarConsolidado(array $filtros): array
    {
        $tramos = ProyeccionPagosTramosSupport::construir($filtros);
        $catalogo = ProyeccionPagosColumnasSupport::catalogoConTramos($tramos);
        $columnas = ProyeccionPagosColumnasSupport::resolverVisibles(
            $catalogo,
            (string) ($filtros['columnas'] ?? ''),
            (string) ($filtros['salida'] ?? ProyeccionPagosReporteFiltros::SALIDA_DETALLE),
        );

        $movimientos = $this->mapearMovimientos($filtros, $tramos);
        $movimientos = $this->ordenar($movimientos, $filtros);
        $filas = $this->aplanarFilas($movimientos, $filtros, $columnas);

        return [
            'filas' => $filas,
            'totales' => $this->totales($movimientos, $columnas),
            'columnas' => $columnas,
            'catalogo' => $catalogo,
            'tramos' => $tramos,
            'secciones' => [[
                'empresa_id' => count($this->empresaIds($filtros)) === 1 ? $this->empresaIds($filtros)[0] : 0,
                'empresa_nombre' => '',
                'filas' => $filas,
                'totales' => $this->totales($movimientos, $columnas),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function generarPorEmpresa(array $filtros, array $empresaIds): array
    {
        $filasFusion = [];
        $secciones = [];
        $columnas = [];
        $tramos = [];
        $catalogo = [];
        $totalesGlobales = [];

        foreach ($empresaIds as $empresaId) {
            $parcial = $this->generarConsolidado(array_merge($filtros, [
                'empresa_ids' => [$empresaId],
                'consolidar_empresas' => true,
            ]));

            $columnas = $parcial['columnas'];
            $tramos = $parcial['tramos'];
            $catalogo = $parcial['catalogo'];
            $nombre = $this->nombreEmpresa($empresaId);

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
                'etiqueta' => $nombre,
                'valores' => [],
            ];
            foreach ($parcial['filas'] as $fila) {
                $filasFusion[] = $fila;
            }

            $totalesGlobales = $this->acumularTotales($totalesGlobales, $parcial['totales']);
        }

        return [
            'filas' => $filasFusion,
            'totales' => $totalesGlobales,
            'columnas' => $columnas,
            'catalogo' => $catalogo,
            'tramos' => $tramos,
            'secciones' => $secciones,
        ];
    }

    /**
     * Movimientos de deuda abierta ya valorizados y clasificados por tramo.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $tramos
     * @return list<array<string, mixed>>
     */
    public function mapearMovimientos(array $filtros, array $tramos): array
    {
        $fechaBase = (string) $tramos['fecha_base'];
        $monedaDestino = (int) ($filtros['moneda_id'] ?? config('cotizacion.ID_MONEDA_DEFAULT', 1));
        $modoMoneda = (string) ($filtros['modo_moneda'] ?? ProyeccionPagosReporteFiltros::MONEDA_TODAS);
        $cotizaciones = $this->cotizacionesEnFecha($fechaBase);
        $codigosCompensar = ProyeccionPagosReporteFiltros::interpretarCodigos(
            (string) ($filtros['condiciones_compensar'] ?? ''),
        )['codigos'];

        $deuda = $this->consultarDeuda($filtros, $fechaBase);
        $adelantos = ! empty($filtros['incluir_adelantos'])
            ? $this->consultarAdelantos($filtros, $fechaBase)
            : collect();
        $conceptos = $this->resolverConceptosCashflow($deuda, $adelantos);

        $movimientos = [];

        foreach ($deuda->all() as $row) {
            $monedaOrigen = (int) ($row->moneda_id ?? 0);

            if ($modoMoneda === ProyeccionPagosReporteFiltros::MONEDA_ORIGEN && $monedaOrigen !== $monedaDestino) {
                continue;
            }

            $saldoOrigen = round((float) ($row->total ?? 0) + (float) ($row->aplicado ?? 0), 4);
            if (abs($saldoOrigen) <= 0.009) {
                continue;
            }

            $coeficiente = $this->coeficiente(
                $monedaOrigen,
                $monedaDestino,
                (float) ($row->cotizacion ?? 1),
                $modoMoneda,
                $cotizaciones,
            );
            if ($coeficiente === null) {
                continue;
            }

            $importe = round($saldoOrigen * $coeficiente, 2);
            $aprobado = in_array((string) ($row->estado_comprobante ?? ''), self::ESTADOS_APROBADOS, true);
            $compensa = $codigosCompensar !== []
                && in_array(trim((string) ($row->condicion_pago_codigo ?? '')), $codigosCompensar, true);
            $claveTramo = ProyeccionPagosTramosSupport::claveTramo($tramos, $row->fechavencimiento ?? null);

            $movimientos[] = $this->mapearFila(
                $row,
                $filtros,
                $tramos,
                $importe,
                $saldoOrigen,
                $coeficiente,
                $aprobado,
                $compensa,
                $claveTramo,
                $conceptos[(int) $row->cuentacorriente_id] ?? null,
            );
        }

        if (! empty($filtros['incluir_adelantos'])) {
            foreach ($adelantos->all() as $row) {
                $monedaOrigen = (int) ($row->moneda_id ?? 0);

                if ($modoMoneda === ProyeccionPagosReporteFiltros::MONEDA_ORIGEN && $monedaOrigen !== $monedaDestino) {
                    continue;
                }

                $saldoOrigen = round((float) ($row->total ?? 0) + (float) ($row->aplicado ?? 0), 4);
                if (abs($saldoOrigen) <= 0.009) {
                    continue;
                }

                $coeficiente = $this->coeficiente(
                    $monedaOrigen,
                    $monedaDestino,
                    (float) ($row->cotizacion ?? 1),
                    $modoMoneda,
                    $cotizaciones,
                );
                if ($coeficiente === null) {
                    continue;
                }

                $movimientos[] = $this->mapearFilaAdelanto(
                    $row,
                    round($saldoOrigen * $coeficiente, 2),
                    $saldoOrigen,
                    $coeficiente,
                    $conceptos[(int) $row->cuentacorriente_id] ?? null,
                );
            }
        }

        return $movimientos;
    }

    /**
     * Concepto de cash flow de cada movimiento (Anita `concoper`).
     *
     * Cadena de resolución: concepto del pago (movimiento de caja) → cuenta imputada
     * en la línea del comprobante → cuenta de mayor importe del asiento → concepto
     * por defecto del proveedor.
     *
     * @param  Collection<int, object>  $deuda
     * @param  Collection<int, object>  $adelantos
     * @return array<int, array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string, origen: string}>
     */
    private function resolverConceptosCashflow(Collection $deuda, Collection $adelantos): array
    {
        $filas = $deuda->concat($adelantos);
        if ($filas->isEmpty()) {
            return [];
        }

        $asientoIds = [];
        $comprobanteIds = [];
        $cuentaIds = [];
        $conceptoIds = [];

        foreach ($filas as $row) {
            $asientoIds[] = (int) ($row->asiento_id ?? 0);
            $comprobanteIds[] = (int) ($row->comprobante_proveedor_id ?? 0);
            $cuentaIds[] = (int) ($row->concepto_cuenta_id ?? 0);
            $conceptoIds[] = (int) ($row->pago_conceptogasto_id ?? 0);
            $conceptoIds[] = (int) ($row->proveedor_conceptogasto_id ?? 0);
        }

        $porAsiento = ConceptoCashflowResolverSupport::porAsientos($asientoIds);
        $porComprobante = ConceptoCashflowResolverSupport::porComprobantesProveedor($comprobanteIds);
        $porCuenta = ConceptoCashflowResolverSupport::porCuentas($cuentaIds);
        $nombres = ConceptoCashflowResolverSupport::nombres($conceptoIds);

        $conceptos = [];

        foreach ($filas as $row) {
            $clave = (int) ($row->cuentacorriente_id ?? 0);
            if ($clave === 0) {
                continue;
            }

            $pagoConcepto = (int) ($row->pago_conceptogasto_id ?? 0);
            $cuentaId = (int) ($row->concepto_cuenta_id ?? 0);
            $asientoId = (int) ($row->asiento_id ?? 0);
            $comprobanteId = (int) ($row->comprobante_proveedor_id ?? 0);
            $proveedorConcepto = (int) ($row->proveedor_conceptogasto_id ?? 0);

            if ($pagoConcepto > 0 && isset($nombres[$pagoConcepto])) {
                $conceptos[$clave] = $this->conceptoSinCuenta(
                    $pagoConcepto,
                    $nombres[$pagoConcepto],
                    ConceptoCashflowResolverSupport::ORIGEN_PAGO,
                );

                continue;
            }

            if ($cuentaId > 0 && isset($porCuenta[$cuentaId])) {
                $conceptos[$clave] = $porCuenta[$cuentaId]
                    + ['origen' => ConceptoCashflowResolverSupport::ORIGEN_CUENTA];

                continue;
            }

            $delAsiento = $porAsiento[$asientoId] ?? $porComprobante[$comprobanteId] ?? null;
            if ($delAsiento !== null) {
                $conceptos[$clave] = $delAsiento
                    + ['origen' => ConceptoCashflowResolverSupport::ORIGEN_ASIENTO];

                continue;
            }

            if ($proveedorConcepto > 0 && isset($nombres[$proveedorConcepto])) {
                $conceptos[$clave] = $this->conceptoSinCuenta(
                    $proveedorConcepto,
                    $nombres[$proveedorConcepto],
                    ConceptoCashflowResolverSupport::ORIGEN_PROVEEDOR,
                );
            }
        }

        return $conceptos;
    }

    /**
     * @return array{conceptogasto_id: int, conceptogasto_nombre: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string, origen: string}
     */
    private function conceptoSinCuenta(int $conceptoId, string $nombre, string $origen): array
    {
        return [
            'conceptogasto_id' => $conceptoId,
            'conceptogasto_nombre' => $nombre,
            'cuentacontable_id' => 0,
            'cuenta_codigo' => '',
            'cuenta_nombre' => '',
            'origen' => $origen,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    private function consultarDeuda(array $filtros, string $fechaBase): Collection
    {
        $query = $this->queryBase($filtros, $fechaBase)
            ->whereNotNull('cc.comprobante_proveedor_id')
            ->leftJoin('comprobante_proveedor as comp', 'comp.id', '=', 'cc.comprobante_proveedor_id')
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'comp.tipotransaccion_compra_id')
            ->leftJoin('comprobante_proveedor_cuota as cuo', 'cuo.id', '=', 'cc.comprobante_proveedor_cuota_id')
            ->leftJoin('formapago as fp', 'fp.id', '=', 'cuo.formapago_id')
            ->leftJoin('ordencompra_comprobante_cuota as occ', 'occ.id', '=', 'cuo.ordencompra_comprobante_cuota_id')
            ->leftJoin('formapago as fpoc', 'fpoc.id', '=', 'occ.formapago_id')
            ->leftJoin('ordencompra_comprobante as ocp', 'ocp.id', '=', 'occ.ordencompra_comprobante_id')
            ->leftJoin('ordencompra as oc', function ($join) {
                $join->on('oc.id', '=', DB::raw(SqlDialectSupport::coalesce('ocp.ordencompra_id', 'comp.ordencompra_id')));
            })
            ->leftJoin('condicionpago as cp', function ($join) {
                $join->on('cp.id', '=', DB::raw(SqlDialectSupport::coalesce('comp.condicionpago_id', 'p.condicionpago_id')));
            })
            ->leftJoin('condicionentrega as ce', 'ce.id', '=', 'p.condicionentrega_id')
            ->leftJoinSub(
                DB::table('condicionpagocuota')
                    ->selectRaw('condicionpago_id, MAX(plazo) AS dias')
                    ->groupBy('condicionpago_id'),
                'cpd',
                'cpd.condicionpago_id',
                '=',
                'cp.id',
            )
            ->leftJoin('requisicion as req', 'req.id', '=', 'oc.requisicion_id')
            ->leftJoin('usuario as ureq', 'ureq.id', '=', 'req.creousuario_id')
            ->leftJoinSub(
                DB::table('requisicion_estado')
                    ->selectRaw('requisicion_id, MAX(fecha) AS fecha_aprobacion')
                    ->where('estado', 'APROBADA')
                    ->whereNull('deleted_at')
                    ->groupBy('requisicion_id'),
                'reap',
                'reap.requisicion_id',
                '=',
                'req.id',
            )
            ->leftJoinSub(
                DB::table('ordencompra_articulo')
                    ->selectRaw('ordencompra_id, MIN(id) AS linea_id')
                    ->groupBy('ordencompra_id'),
                'ocl',
                'ocl.ordencompra_id',
                '=',
                'oc.id',
            )
            ->leftJoin('ordencompra_articulo as oai', 'oai.id', '=', 'ocl.linea_id')
            ->leftJoin('articulo as art', 'art.id', '=', 'oai.articulo_id')
            ->leftJoinSub(
                DB::table('comprobante_proveedor_concepto')
                    ->selectRaw('comprobante_proveedor_id, MIN(id) AS concepto_linea_id')
                    ->groupBy('comprobante_proveedor_id'),
                'cpcm',
                'cpcm.comprobante_proveedor_id',
                '=',
                'comp.id',
            )
            ->leftJoin('comprobante_proveedor_concepto as cpc', 'cpc.id', '=', 'cpcm.concepto_linea_id')
            ->addSelect([
                'comp.id as comprobante_proveedor_id',
                'comp.letra as letra',
                'comp.sucursal as sucursal',
                'comp.numerocomprobante as numerocomprobante',
                'comp.fechacomprobante as fechacomprobante',
                'comp.fechaiva as fechaiva',
                'comp.estado as estado_comprobante',
                'comp.leyenda as leyenda',
                'comp.created_at as fecha_carga',
                'comp.ordencompra_id as comprobante_ordencompra_id',
                'tt.abreviatura as tipo_abreviatura',
                'tt.nombre as tipo_nombre',
                'cuo.numero_cuota as numero_cuota',
                'cuo.detalle as detalle_cuota',
                'fp.abreviatura as formapago_abreviatura',
                'fp.nombre as formapago_nombre',
                'fpoc.abreviatura as formapago_oc_abreviatura',
                'fpoc.nombre as formapago_oc_nombre',
                'occ.detalle as detalle_cuota_oc',
                'oc.id as ordencompra_id',
                'oc.numeroordencompra as numeroordencompra',
                'cp.id as condicionpago_id',
                'cp.nombre as condicion_pago',
                'cp.codigo as condicion_pago_codigo',
                'cpd.dias as condicion_pago_dias',
                'ce.dias as dias_entrega_cheque',
                'req.id as requisicion_id',
                'req.numerorequisicion as numerorequisicion',
                'req.estado as estado_requisicion',
                'ureq.nombre as usuario_requisicion',
                'reap.fecha_aprobacion as fecha_aprobacion_requisicion',
                DB::raw(SqlDialectSupport::coalesce('art.descripcion', 'oai.detalle').' as detalle_item'),
                'comp.asiento_id as asiento_id',
                'cpc.cuentacontabledebe_id as concepto_cuenta_id',
            ]);

        $this->aplicarFiltrosComprobante($query, $filtros);

        return collect($query->get());
    }

    /**
     * Pagos a cuenta (adelantos) sin aplicar: equivalen a los movimientos «OPA» de Anita.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    private function consultarAdelantos(array $filtros, string $fechaBase): Collection
    {
        $query = $this->queryBase($filtros, $fechaBase)
            ->whereNull('cc.comprobante_proveedor_id')
            ->whereNotNull('cc.pagoproveedor_id')
            ->leftJoin('pagoproveedor as pp', 'pp.id', '=', 'cc.pagoproveedor_id')
            ->leftJoin('caja_movimiento as cm', 'cm.id', '=', 'pp.caja_movimiento_id')
            ->addSelect([
                'cc.pagoproveedor_id as pagoproveedor_id',
                'pp.tipocomprobante as pago_tipocomprobante',
                'pp.letra as pago_letra',
                'pp.sucursal as pago_sucursal',
                'pp.numerotransaccion as pago_numero',
                'pp.detalle as pago_detalle',
                'pp.asiento_id as asiento_id',
                'cm.conceptogasto_id as pago_conceptogasto_id',
            ])
            ->whereNull('pp.deleted_at');

        return collect($query->get());
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBase(array $filtros, string $fechaBase): Builder
    {
        $aplicaciones = DB::table('proveedor_cuentacorriente_aplicacion')
            ->selectRaw('proveedor_cuentacorriente_id, SUM(total) AS aplicado')
            ->where('fecha', '<=', $fechaBase)
            ->groupBy('proveedor_cuentacorriente_id');

        $query = DB::table('proveedor_cuentacorriente as cc')
            ->join('proveedor as p', 'p.id', '=', 'cc.proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'cc.empresa_id')
            ->join('moneda as mon', 'mon.id', '=', 'cc.moneda_id')
            ->leftJoinSub($aplicaciones, 'apl', 'apl.proveedor_cuentacorriente_id', '=', 'cc.id')
            ->select([
                'cc.id as cuentacorriente_id',
                'cc.fecha as fecha',
                'cc.fechavencimiento as fechavencimiento',
                'cc.total as total',
                'cc.moneda_id as moneda_id',
                'cc.cotizacion as cotizacion',
                'cc.empresa_id as empresa_id',
                'cc.proveedor_id as proveedor_id',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'p.conceptogasto_id as proveedor_conceptogasto_id',
                'e.nombre as nombreempresa',
                'mon.abreviatura as moneda_abreviatura',
                DB::raw(SqlDialectSupport::coalesce('apl.aplicado', '0').' as aplicado'),
            ])
            ->whereNull('cc.deleted_at')
            ->where('cc.fecha', '<=', $fechaBase)
            ->whereRaw('abs(cc.total + '.SqlDialectSupport::coalesce('apl.aplicado', '0').') > 0.009');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cc.empresa_id');

        $empresaIds = $this->empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('cc.empresa_id', $empresaIds);
        }

        $codigos = ProyeccionPagosReporteFiltros::interpretarCodigos((string) ($filtros['proveedores_codigo'] ?? ''));
        if ($codigos['codigos'] !== []) {
            $query->whereIn('p.codigo', $codigos['codigos']);
        } else {
            if ($codigos['desde'] !== '') {
                $query->whereRaw(SqlDialectSupport::castEntero('p.codigo').' >= ?', [(int) $codigos['desde']]);
            }
            if ($codigos['hasta'] !== '') {
                $query->whereRaw(SqlDialectSupport::castEntero('p.codigo').' <= ?', [(int) $codigos['hasta']]);
            }
        }

        $nombre = trim((string) ($filtros['proveedor_nombre'] ?? ''));
        if ($nombre !== '') {
            $query->whereRaw(SqlDialectSupport::lower('p.nombre').' like ?', ['%'.mb_strtolower($nombre).'%']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosComprobante(Builder $query, array $filtros): void
    {
        $tipos = collect($filtros['tipotransaccion_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($tipos !== []) {
            $query->whereIn('comp.tipotransaccion_compra_id', $tipos);
        }

        switch ((string) ($filtros['estado_aprobacion'] ?? ProyeccionPagosReporteFiltros::APROBACION_TODOS)) {
            case ProyeccionPagosReporteFiltros::APROBACION_APROBADOS:
                $query->whereIn('comp.estado', self::ESTADOS_APROBADOS);
                break;
            case ProyeccionPagosReporteFiltros::APROBACION_PENDIENTES:
                $query->whereNotIn('comp.estado', self::ESTADOS_APROBADOS);
                break;
        }

        $fechaCarga = trim((string) ($filtros['fecha_carga_desde'] ?? ''));
        if ($fechaCarga !== '') {
            $hora = trim((string) ($filtros['hora_carga_desde'] ?? '')) ?: '00:00';
            $query->where('comp.updated_at', '>=', $fechaCarga.' '.$hora.':00');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $tramos
     * @param  array<string, mixed>|null  $concepto  Concepto de cash flow resuelto
     * @return array<string, mixed>
     */
    private function mapearFila(
        object $row,
        array $filtros,
        array $tramos,
        float $importe,
        float $saldoOrigen,
        float $coeficiente,
        bool $aprobado,
        bool $compensa,
        string $claveTramo,
        ?array $concepto = null,
    ): array {
        $fechaBase = Carbon::parse((string) $tramos['fecha_base'])->startOfDay();
        $fechaVto = ! empty($row->fechavencimiento)
            ? Carbon::parse((string) $row->fechavencimiento)->startOfDay()
            : null;
        $diasEntrega = (int) ($row->dias_entrega_cheque ?? 0);

        $medio = PropuestaPagoLineaPresentacionSupport::abreviaturaAnita(
            (string) ($row->formapago_abreviatura
                ?? $row->formapago_nombre
                ?? $row->formapago_oc_abreviatura
                ?? $row->formapago_oc_nombre
                ?? ''),
        );

        $valores = [
            'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
            'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
            'empresa' => (string) ($row->nombreempresa ?? ''),
            'tipo' => mb_substr((string) (($row->tipo_abreviatura ?? '') ?: ($row->tipo_nombre ?? '')), 0, 4),
            'comprobante' => $this->formatearComprobante($row),
            'cuota' => (int) ($row->numero_cuota ?? 0) > 0 ? (string) $row->numero_cuota : '',
            'estado_comprobante' => (string) ($row->estado_comprobante ?? ''),
            'fecha_comprobante' => $row->fechacomprobante ?? null,
            'fecha_iva' => $row->fechaiva ?? null,
            'fecha_carga' => $row->fecha_carga ?? null,
            'fecha_vencimiento' => $row->fechavencimiento ?? null,
            'dias_vencimiento' => $fechaVto ? (int) $fechaBase->diffInDays($fechaVto, false) : null,
            'fecha_diferida' => $fechaVto && $diasEntrega > 0
                ? $fechaVto->copy()->addDays($diasEntrega)->format('Y-m-d')
                : ($fechaVto?->format('Y-m-d')),
            'tramo_vencimiento' => ProyeccionPagosTramosSupport::etiquetaTramo($tramos, $claveTramo),
            'moneda' => (string) ($row->moneda_abreviatura ?? ''),
            'cotizacion' => round($coeficiente, 6),
            'importe_origen' => $saldoOrigen,
            'a_compensar' => $compensa ? $importe : 0.0,
            'adelantos' => 0.0,
            'pend_aprobacion' => (! $compensa && ! $aprobado) ? $importe : 0.0,
            'total_aprobado' => (! $compensa && $aprobado) ? $importe : 0.0,
            'total_adeudado' => $compensa ? 0.0 : $importe,
            'condicion_pago_dias' => (int) ($row->condicion_pago_dias ?? 0),
            'condicion_pago' => (string) ($row->condicion_pago ?? ''),
            'medio_pago' => $medio,
            'detalle_pago' => trim((string) (($row->detalle_cuota ?? '') ?: ($row->detalle_cuota_oc ?? ''))),
            'dias_entrega_cheque' => $diasEntrega,
            'aprobacion' => $aprobado ? 'A' : 'P',
            'nro_referencia' => (int) ($row->numeroordencompra ?? 0) > 0 ? (string) $row->numeroordencompra : '',
            'requisicion' => (int) ($row->numerorequisicion ?? 0) > 0 ? (string) $row->numerorequisicion : '',
            'usuario_requisicion' => (string) ($row->usuario_requisicion ?? ''),
            'aprobacion_requisicion' => $this->textoAprobacionRequisicion($row),
            'detalle_item' => (string) ($row->detalle_item ?? ''),
            'concepto' => $this->codigoConcepto($concepto),
            'detalle_concepto' => (string) ($concepto['conceptogasto_nombre'] ?? ''),
            'cuenta_concepto' => $this->textoCuentaConcepto($concepto),
            'leyenda' => (string) ($row->leyenda ?? ''),
        ];

        foreach ($this->clavesTramos($tramos) as $clave) {
            $valores[$clave] = 0.0;
        }
        if (! $compensa) {
            $valores[$claveTramo] = $importe;
        }

        return [
            'tipo_fila' => 'detalle',
            'valores' => $valores,
            'clave_tramo' => $claveTramo,
            'importe' => $importe,
            'aprobado' => $aprobado,
            'compensa' => $compensa,
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'comprobante_proveedor_id' => (int) ($row->comprobante_proveedor_id ?? 0),
            'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
            'requisicion_id' => (int) ($row->requisicion_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'moneda_id' => (int) ($row->moneda_id ?? 0),
            'condicionpago_id' => (int) ($row->condicionpago_id ?? 0),
            'conceptogasto_id' => (int) ($concepto['conceptogasto_id'] ?? 0),
            'concepto_cuentacontable_id' => (int) ($concepto['cuentacontable_id'] ?? 0),
            'concepto_origen' => (string) ($concepto['origen'] ?? ''),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'fechavencimiento' => $row->fechavencimiento ?? null,
        ];
    }

    /**
     * Pagos a cuenta y diferencias de cambio sin comprobante: restan del total adeudado.
     *
     * @param  array<string, mixed>|null  $concepto  Concepto de cash flow resuelto
     * @return array<string, mixed>
     */
    private function mapearFilaAdelanto(
        object $row,
        float $importe,
        float $saldoOrigen,
        float $coeficiente,
        ?array $concepto = null,
    ): array {
        $valores = [
            'proveedor_codigo' => (string) ($row->proveedor_codigo ?? ''),
            'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
            'empresa' => (string) ($row->nombreempresa ?? ''),
            'tipo' => 'ADE',
            'comprobante' => $this->formatearPago($row),
            'cuota' => '',
            'estado_comprobante' => '',
            'fecha_comprobante' => $row->fecha ?? null,
            'fecha_iva' => null,
            'fecha_carga' => null,
            'fecha_vencimiento' => $row->fechavencimiento ?? null,
            'dias_vencimiento' => null,
            'fecha_diferida' => null,
            'tramo_vencimiento' => 'Adelanto',
            'moneda' => (string) ($row->moneda_abreviatura ?? ''),
            'cotizacion' => round($coeficiente, 6),
            'importe_origen' => $saldoOrigen,
            'a_compensar' => 0.0,
            // El saldo de un pago a cuenta viene negativo en la cuenta corriente:
            // se conserva el signo para que reste del total adeudado.
            'adelantos' => $importe,
            'pend_aprobacion' => 0.0,
            'total_aprobado' => $importe,
            'total_adeudado' => $importe,
            'condicion_pago_dias' => 0,
            'condicion_pago' => '',
            'medio_pago' => '',
            'detalle_pago' => (string) ($row->pago_detalle ?? ''),
            'dias_entrega_cheque' => 0,
            'aprobacion' => '',
            'nro_referencia' => '',
            'requisicion' => '',
            'usuario_requisicion' => '',
            'aprobacion_requisicion' => '',
            'detalle_item' => '',
            'concepto' => $this->codigoConcepto($concepto),
            'detalle_concepto' => (string) ($concepto['conceptogasto_nombre'] ?? ''),
            'cuenta_concepto' => $this->textoCuentaConcepto($concepto),
            'leyenda' => '',
        ];

        return [
            'tipo_fila' => 'detalle',
            'valores' => $valores,
            'clave_tramo' => '',
            'importe' => $importe,
            'aprobado' => true,
            'compensa' => false,
            'es_adelanto' => true,
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'comprobante_proveedor_id' => 0,
            'ordencompra_id' => 0,
            'requisicion_id' => 0,
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'moneda_id' => (int) ($row->moneda_id ?? 0),
            'condicionpago_id' => 0,
            'conceptogasto_id' => (int) ($concepto['conceptogasto_id'] ?? 0),
            'concepto_cuentacontable_id' => (int) ($concepto['cuentacontable_id'] ?? 0),
            'concepto_origen' => (string) ($concepto['origen'] ?? ''),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'fechavencimiento' => $row->fechavencimiento ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $movimientos, array $filtros): array
    {
        $agrupacion = (string) ($filtros['agrupacion'] ?? ProyeccionPagosReporteFiltros::AGRUPACION_PROVEEDOR);
        $orden = (string) ($filtros['orden'] ?? ProyeccionPagosReporteFiltros::ORDEN_CODIGO);

        $totalPorGrupo = [];
        foreach ($movimientos as $movimiento) {
            $clave = $this->claveGrupo($movimiento, $agrupacion);
            $totalPorGrupo[$clave] = ($totalPorGrupo[$clave] ?? 0.0) + (float) $movimiento['importe'];
        }

        $coleccion = collect($movimientos)->map(function (array $movimiento) use ($agrupacion, $totalPorGrupo) {
            $clave = $this->claveGrupo($movimiento, $agrupacion);
            $movimiento['grupo_clave'] = $clave;
            $movimiento['grupo_etiqueta'] = $this->etiquetaGrupo($movimiento, $agrupacion);
            $movimiento['grupo_total'] = (float) ($totalPorGrupo[$clave] ?? 0);

            return $movimiento;
        });

        $ordenGrupo = match ($orden) {
            ProyeccionPagosReporteFiltros::ORDEN_TOTAL_DESC => fn (array $m) => -1 * $m['grupo_total'],
            ProyeccionPagosReporteFiltros::ORDEN_TOTAL_ASC => fn (array $m) => $m['grupo_total'],
            ProyeccionPagosReporteFiltros::ORDEN_NOMBRE => fn (array $m) => mb_strtolower((string) $m['valores']['proveedor_nombre']),
            default => fn (array $m) => $m['grupo_etiqueta'] === '' ? 'zzzz' : mb_strtolower((string) $m['grupo_etiqueta']),
        };

        if ($agrupacion === ProyeccionPagosReporteFiltros::AGRUPACION_PROVEEDOR
            && $orden === ProyeccionPagosReporteFiltros::ORDEN_CODIGO) {
            $ordenGrupo = fn (array $m) => str_pad((string) $m['valores']['proveedor_codigo'], 12, '0', STR_PAD_LEFT);
        }

        $ordenDetalle = match ($orden) {
            ProyeccionPagosReporteFiltros::ORDEN_VENCIMIENTO => fn (array $m) => (string) ($m['fechavencimiento'] ?? '9999-12-31'),
            ProyeccionPagosReporteFiltros::ORDEN_DIAS => fn (array $m) => (int) ($m['valores']['dias_vencimiento'] ?? 99999),
            ProyeccionPagosReporteFiltros::ORDEN_TOTAL_DESC => fn (array $m) => -1 * (float) $m['importe'],
            ProyeccionPagosReporteFiltros::ORDEN_TOTAL_ASC => fn (array $m) => (float) $m['importe'],
            default => fn (array $m) => (string) ($m['fechavencimiento'] ?? '9999-12-31'),
        };

        return $coleccion
            ->sortBy([
                fn (array $a, array $b) => $this->comparar($ordenGrupo($a), $ordenGrupo($b)),
                fn (array $a, array $b) => $this->comparar($ordenDetalle($a), $ordenDetalle($b)),
                fn (array $a, array $b) => $this->comparar(
                    (string) $a['valores']['comprobante'],
                    (string) $b['valores']['comprobante'],
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * Filas listas para pantalla, PDF y Excel: cabeceras de grupo, detalle, subtotales y total.
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $columnas
     * @return list<array<string, mixed>>
     */
    private function aplanarFilas(array $movimientos, array $filtros, array $columnas): array
    {
        if ($movimientos === []) {
            return [];
        }

        $agrupacion = (string) ($filtros['agrupacion'] ?? ProyeccionPagosReporteFiltros::AGRUPACION_PROVEEDOR);
        $soloTotales = (string) ($filtros['salida'] ?? ProyeccionPagosReporteFiltros::SALIDA_DETALLE)
            === ProyeccionPagosReporteFiltros::SALIDA_RESUMEN;
        $sinAgrupar = $agrupacion === ProyeccionPagosReporteFiltros::AGRUPACION_SIN;
        $clavesImporte = ProyeccionPagosColumnasSupport::clavesImporte($columnas);

        $filas = [];
        $grupoActual = null;
        $grupoSecuencia = 0;
        $subtotales = [];
        $cantidadGrupo = 0;
        $etiquetaGrupo = '';

        $cerrar = function () use (
            &$filas, &$grupoActual, &$subtotales, &$cantidadGrupo, &$etiquetaGrupo, &$grupoSecuencia, $sinAgrupar
        ) {
            if ($grupoActual === null || $sinAgrupar) {
                return;
            }

            $filas[] = [
                'tipo_fila' => 'subtotal_grupo',
                'grupo_id' => $grupoSecuencia,
                'etiqueta' => 'Total '.$etiquetaGrupo,
                'cantidad' => $cantidadGrupo,
                'valores' => $subtotales,
            ];
            $grupoActual = null;
            $subtotales = [];
            $cantidadGrupo = 0;
        };

        foreach ($movimientos as $movimiento) {
            $clave = (string) $movimiento['grupo_clave'];

            if ($grupoActual !== null && $grupoActual !== $clave) {
                $cerrar();
            }

            if ($grupoActual !== $clave) {
                $grupoActual = $clave;
                $etiquetaGrupo = (string) $movimiento['grupo_etiqueta'];
                $grupoSecuencia++;

                if (! $soloTotales && ! $sinAgrupar) {
                    $filas[] = [
                        'tipo_fila' => 'cabecera_grupo',
                        'grupo_id' => $grupoSecuencia,
                        'etiqueta' => $etiquetaGrupo,
                        'valores' => [],
                    ];
                }
            }

            $cantidadGrupo++;
            foreach ($clavesImporte as $claveImporte) {
                $subtotales[$claveImporte] = ($subtotales[$claveImporte] ?? 0.0)
                    + (float) ($movimiento['valores'][$claveImporte] ?? 0);
            }

            if (! $soloTotales) {
                $movimiento['grupo_id'] = $grupoSecuencia;
                $filas[] = $movimiento;
            }
        }

        $cerrar();

        $totales = $this->totales($movimientos, $columnas);
        $filas[] = [
            'tipo_fila' => 'total_general',
            'etiqueta' => 'Total general',
            'cantidad' => (int) $totales['cantidad'],
            'valores' => $totales['importes'],
        ];

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  list<array<string, mixed>>  $columnas
     * @return array{cantidad: int, proveedores: int, importes: array<string, float>, total_adeudado: float}
     */
    public function totales(array $movimientos, array $columnas): array
    {
        $importes = [];
        foreach (ProyeccionPagosColumnasSupport::clavesImporte($columnas) as $clave) {
            $importes[$clave] = 0.0;
        }

        foreach ($movimientos as $movimiento) {
            foreach ($importes as $clave => $acumulado) {
                $importes[$clave] = $acumulado + (float) ($movimiento['valores'][$clave] ?? 0);
            }
        }

        return [
            'cantidad' => count($movimientos),
            'proveedores' => collect($movimientos)->pluck('proveedor_id')->unique()->count(),
            'importes' => $importes,
            'total_adeudado' => (float) ($importes['total_adeudado'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));

        return new LengthAwarePaginator(
            array_slice($filas, ($page - 1) * $perPage, $perPage),
            count($filas),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery = null): string
    {
        $partes = [];

        $ids = $this->empresaIds($filtros);
        if ($ids !== [] && $empresaQuery !== null) {
            $nombres = collect($empresaQuery)->whereIn('id', $ids)->pluck('nombre')->filter()->values()->all();
            if ($nombres !== []) {
                $texto = 'Empresas: '.implode(', ', $nombres);
                if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
                    $texto .= ' (consolidado)';
                }
                $partes[] = $texto;
            }
        }

        $partes[] = ProyeccionPagosReporteFiltros::etiqueta(
            (string) ($filtros['tipo_informe'] ?? ''),
            ProyeccionPagosReporteFiltros::OPCIONES_INFORME,
        ).' al '.ProyeccionPagosReporteFiltros::formatearFechaPantalla($filtros['fecha_base'] ?? null);

        $partes[] = 'Expresado en: '.$this->nombreMoneda((int) ($filtros['moneda_id'] ?? 0)).' ('
            .ProyeccionPagosReporteFiltros::etiqueta(
                (string) ($filtros['modo_moneda'] ?? ''),
                ProyeccionPagosReporteFiltros::OPCIONES_MONEDA,
            ).')';

        $tramos = ProyeccionPagosReporteFiltros::tramos($filtros);
        $unidad = ($filtros['tipo_vencimiento'] ?? '') === ProyeccionPagosReporteFiltros::VENCIMIENTO_MES
            ? 'Meses'
            : 'Días';
        $partes[] = $unidad.': '.implode(', ', $tramos);

        if (! empty($filtros['abre_anterior'])) {
            $partes[] = 'Abre saldo a '.(int) ($filtros['dias_anterior'] ?? 0).' días';
        }

        $partes[] = ProyeccionPagosReporteFiltros::etiqueta(
            (string) ($filtros['agrupacion'] ?? ''),
            ProyeccionPagosReporteFiltros::OPCIONES_AGRUPACION,
        );
        $partes[] = 'Orden: '.ProyeccionPagosReporteFiltros::etiqueta(
            (string) ($filtros['orden'] ?? ''),
            ProyeccionPagosReporteFiltros::OPCIONES_ORDEN,
        );

        if (($filtros['estado_aprobacion'] ?? '') !== ProyeccionPagosReporteFiltros::APROBACION_TODOS) {
            $partes[] = ProyeccionPagosReporteFiltros::etiqueta(
                (string) $filtros['estado_aprobacion'],
                ProyeccionPagosReporteFiltros::OPCIONES_APROBACION,
            );
        }

        if (($filtros['proveedores_codigo'] ?? '') !== '') {
            $partes[] = 'Proveedores: '.$filtros['proveedores_codigo'];
        }

        if (($filtros['proveedor_nombre'] ?? '') !== '') {
            $partes[] = 'Nombre contiene: '.$filtros['proveedor_nombre'];
        }

        if (($filtros['fecha_carga_desde'] ?? '') !== '' && $filtros['fecha_carga_desde'] !== null) {
            $partes[] = 'Cargados desde: '
                .ProyeccionPagosReporteFiltros::formatearFechaPantalla($filtros['fecha_carga_desde'])
                .' '.(string) ($filtros['hora_carga_desde'] ?? '');
        }

        if (empty($filtros['incluir_adelantos'])) {
            $partes[] = 'Sin adelantos';
        }

        return implode(' · ', $partes);
    }

    /** @param array<string, mixed> $filtros @return list<int> */
    private function empresaIds(array $filtros): array
    {
        return array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            fn (int $id) => $id > 0,
        ));
    }

    /** @param array<string, mixed> $tramos @return list<string> */
    private function clavesTramos(array $tramos): array
    {
        $claves = [];
        if (! empty($tramos['abre_anterior'])) {
            $claves[] = ProyeccionPagosTramosSupport::CLAVE_SALDO_ANTERIOR;
        }
        foreach ($tramos['tramos'] ?? [] as $tramo) {
            $claves[] = (string) $tramo['clave'];
        }
        $claves[] = ProyeccionPagosTramosSupport::CLAVE_POSTERIOR;

        return $claves;
    }

    /** @param array<string, mixed> $movimiento */
    private function claveGrupo(array $movimiento, string $agrupacion): string
    {
        return match ($agrupacion) {
            ProyeccionPagosReporteFiltros::AGRUPACION_EMPRESA => 'emp:'.$movimiento['empresa_id'],
            ProyeccionPagosReporteFiltros::AGRUPACION_MONEDA => 'mon:'.$movimiento['moneda_id'],
            ProyeccionPagosReporteFiltros::AGRUPACION_MEDIO_PAGO => 'mp:'.mb_strtolower((string) $movimiento['valores']['medio_pago']),
            ProyeccionPagosReporteFiltros::AGRUPACION_CONDICION_PAGO => 'cp:'.$movimiento['condicionpago_id'],
            ProyeccionPagosReporteFiltros::AGRUPACION_CONCEPTO => 'cg:'.($movimiento['conceptogasto_id'] ?? 0),
            ProyeccionPagosReporteFiltros::AGRUPACION_TRAMO => 'tr:'.$movimiento['clave_tramo'],
            ProyeccionPagosReporteFiltros::AGRUPACION_SIN => 'all',
            default => 'prov:'.$movimiento['proveedor_id'],
        };
    }

    /** @param array<string, mixed> $movimiento */
    private function etiquetaGrupo(array $movimiento, string $agrupacion): string
    {
        $valores = $movimiento['valores'];

        return match ($agrupacion) {
            ProyeccionPagosReporteFiltros::AGRUPACION_EMPRESA => (string) $valores['empresa'],
            ProyeccionPagosReporteFiltros::AGRUPACION_MONEDA => (string) ($valores['moneda'] ?: 'Sin moneda'),
            ProyeccionPagosReporteFiltros::AGRUPACION_MEDIO_PAGO => (string) ($valores['medio_pago'] ?: 'Sin medio de pago'),
            ProyeccionPagosReporteFiltros::AGRUPACION_CONDICION_PAGO => (string) ($valores['condicion_pago'] ?: 'Sin condición'),
            ProyeccionPagosReporteFiltros::AGRUPACION_CONCEPTO => trim(
                ((string) $valores['concepto']).' '.((string) $valores['detalle_concepto']),
            ) ?: 'Sin concepto de cash flow',
            ProyeccionPagosReporteFiltros::AGRUPACION_TRAMO => (string) ($valores['tramo_vencimiento'] ?: 'Sin tramo'),
            ProyeccionPagosReporteFiltros::AGRUPACION_SIN => 'General',
            default => trim(((string) $valores['proveedor_codigo']).' '.((string) $valores['proveedor_nombre'])),
        };
    }

    private function comparar(mixed $a, mixed $b): int
    {
        if (is_string($a) || is_string($b)) {
            return strcmp((string) $a, (string) $b);
        }

        return $a <=> $b;
    }

    private function formatearComprobante(object $row): string
    {
        $letra = trim((string) ($row->letra ?? ''));
        $sucursal = (int) ($row->sucursal ?? 0);
        $numero = (int) ($row->numerocomprobante ?? 0);

        if ($letra === '' && $sucursal === 0 && $numero === 0) {
            return 'CC#'.((int) ($row->cuentacorriente_id ?? 0));
        }

        return sprintf('%s-%04d-%08d', $letra !== '' ? $letra : 'X', $sucursal, $numero);
    }

    private function formatearPago(object $row): string
    {
        $numero = (int) ($row->pago_numero ?? 0);
        if ($numero <= 0) {
            return 'Pago a cuenta';
        }

        return trim(sprintf(
            '%s %s-%04d-%08d',
            (string) ($row->pago_tipocomprobante ?: 'OP'),
            (string) ($row->pago_letra ?: 'A'),
            (int) ($row->pago_sucursal ?? 0),
            $numero,
        ));
    }

    /** @param array<string, mixed>|null $concepto */
    private function codigoConcepto(?array $concepto): string
    {
        $id = (int) ($concepto['conceptogasto_id'] ?? 0);

        return $id > 0 ? (string) $id : '';
    }

    /** @param array<string, mixed>|null $concepto */
    private function textoCuentaConcepto(?array $concepto): string
    {
        $codigo = trim((string) ($concepto['cuenta_codigo'] ?? ''));
        if ($codigo === '') {
            return '';
        }

        return trim($codigo.' '.(string) ($concepto['cuenta_nombre'] ?? ''));
    }

    private function textoAprobacionRequisicion(object $row): string
    {
        $fecha = $row->fecha_aprobacion_requisicion ?? null;
        if ($fecha) {
            return 'Aprobada '.Carbon::parse((string) $fecha)->format('d/m/y');
        }

        $estado = trim((string) ($row->estado_requisicion ?? ''));

        return $estado !== '' ? ucwords(mb_strtolower($estado)) : '';
    }

    /**
     * Coeficiente de conversión a la moneda de expresión del informe.
     *
     * @param  array<int, float>  $cotizaciones
     */
    private function coeficiente(
        int $monedaOrigen,
        int $monedaDestino,
        float $cotizacionMovimiento,
        string $modoMoneda,
        array $cotizaciones,
    ): ?float {
        if ($monedaOrigen === $monedaDestino || $monedaDestino <= 0) {
            return 1.0;
        }

        $monedaBase = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        $tasaOrigen = $monedaOrigen === $monedaBase
            ? 1.0
            : ($modoMoneda === ProyeccionPagosReporteFiltros::MONEDA_TODAS_HIST
                ? $cotizacionMovimiento
                : (float) ($cotizaciones[$monedaOrigen] ?? 0));

        $tasaDestino = $monedaDestino === $monedaBase
            ? 1.0
            : (float) ($cotizaciones[$monedaDestino] ?? 0);

        if ($tasaOrigen <= 0.000001 || $tasaDestino <= 0.000001) {
            return null;
        }

        return $tasaOrigen / $tasaDestino;
    }

    /**
     * Tasas vigentes por moneda en la fecha: las filas cargadas en cero no son cotización,
     * se resuelve con la última real anterior (CotizacionVigenteSupport).
     *
     * @return array<int, float>
     */
    private function cotizacionesEnFecha(string $fecha): array
    {
        $cotizacion = $this->cotizacionQuery->leeCotizacionDiaria($fecha);
        if (! $cotizacion) {
            return [];
        }

        $tasas = [];
        foreach ($cotizacion->cotizacion_monedas as $fila) {
            $monedaId = (int) $fila->moneda_id;
            $venta = CotizacionVigenteSupport::venta($fecha, $monedaId)['valor'];
            $tasas[$monedaId] = $venta > 0
                ? $venta
                : CotizacionVigenteSupport::compra($fecha, $monedaId)['valor'];
        }

        return $tasas;
    }

    private function nombreMoneda(int $monedaId): string
    {
        if ($monedaId <= 0) {
            return '';
        }

        return (string) (DB::table('moneda')->where('id', $monedaId)->value('nombre') ?? ('#'.$monedaId));
    }

    private function nombreEmpresa(int $empresaId): string
    {
        $nombre = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');

        return $nombre !== '' ? $nombre : ('#'.$empresaId);
    }

    /**
     * @param  array<string, mixed>  $acumulado
     * @param  array<string, mixed>  $totales
     * @return array<string, mixed>
     */
    private function acumularTotales(array $acumulado, array $totales): array
    {
        if ($acumulado === []) {
            return $totales;
        }

        $importes = $acumulado['importes'] ?? [];
        foreach ($totales['importes'] ?? [] as $clave => $valor) {
            $importes[$clave] = (float) ($importes[$clave] ?? 0) + (float) $valor;
        }

        return [
            'cantidad' => (int) ($acumulado['cantidad'] ?? 0) + (int) ($totales['cantidad'] ?? 0),
            'proveedores' => (int) ($acumulado['proveedores'] ?? 0) + (int) ($totales['proveedores'] ?? 0),
            'importes' => $importes,
            'total_adeudado' => (float) ($importes['total_adeudado'] ?? 0),
        ];
    }
}
