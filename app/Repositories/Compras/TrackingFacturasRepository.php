<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\Tracking\TrackingAntiguedadDeuda;
use App\Support\Compras\Tracking\TrackingComprobanteFamilia;
use App\Support\Compras\Tracking\TrackingFacturasListadoFiltros;
use App\Support\Compras\Tracking\TrackingPagoEstado;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta del tracking de facturas.
 *
 * Une `comprobante_proveedor` con el índice materializado
 * `comprobante_tracking_indice`, que aporta las tres columnas que el ERP no
 * tiene por sí mismo: disponibilidad del PDF, fecha real de carga y estado de
 * pago. El join es a la izquierda a propósito: un comprobante todavía no
 * indexado tiene que aparecer en la grilla, marcado como pendiente de resolver,
 * y no desaparecer del listado.
 */
class TrackingFacturasRepository
{
    private const REGISTROS_POR_PAGINA = 20;

    public function __construct(
        private readonly Comprobante_Proveedor $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Comprobante_Proveedor>|\Illuminate\Database\Eloquent\Collection<int, Comprobante_Proveedor>
     */
    public function leeTrackingFacturas(array $filtros, bool $paginando = true)
    {
        $query = $this->consultaBase();

        TrackingFacturasListadoFiltros::aplicar($query, $filtros);

        $this->ordenar($query, $filtros);

        return $paginando ? $query->paginate(self::REGISTROS_POR_PAGINA) : $query->get();
    }

    /**
     * Totales del encabezado: se calculan sobre el mismo filtro que la grilla
     * para que las tarjetas y el listado no puedan contradecirse.
     *
     * La cobertura de PDF se cuenta en tres estados y no en dos: un
     * comprobante todavía no indexado no es un faltante, es un pendiente de
     * averiguar, y mezclarlos haría ver un agujero donde no lo hay.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{registros: int, total: float, saldo: float, con_pdf: int, sin_pdf: int, sin_resolver: int, sin_contabilizar: int, con_deuda: int}
     */
    public function resumen(array $filtros): array
    {
        $query = $this->consultaBase();
        TrackingFacturasListadoFiltros::aplicar($query, $filtros);

        $indexado = 'comprobante_tracking_indice.sincronizado_at is not null';
        $conDeuda = 'comprobante_tracking_indice.pago_estado in (?, ?)';
        $fechaAging = '('.TrackingAntiguedadDeuda::sqlFechaBase('comprobante_proveedor').')';
        $diasAging = 'datediff(curdate(), '.$fechaAging.')';
        // FIN/CIN son internos: no se escanean, no son un faltante operativo.
        $abrevInternas = "tipotransaccion_compra.abreviatura not in ('FIN', 'CIN')";

        $fila = $query
            ->reorder()
            ->selectRaw('count(*) as registros')
            ->selectRaw('coalesce(sum(comprobante_proveedor.total), 0) as total')
            ->selectRaw('coalesce(sum(comprobante_tracking_indice.pago_saldo), 0) as saldo')
            ->selectRaw('sum(case when comprobante_tracking_indice.pdf_disponible = 1 then 1 else 0 end) as con_pdf')
            ->selectRaw(
                'sum(case when '.$indexado.' and comprobante_tracking_indice.pdf_disponible = 0'
                .' then 1 else 0 end) as sin_pdf'
            )
            ->selectRaw(
                'sum(case when '.$indexado.' and comprobante_tracking_indice.pdf_disponible = 0'
                .' and '.$abrevInternas.' then 1 else 0 end) as sin_pdf_externos'
            )
            ->selectRaw('sum(case when '.$indexado.' then 0 else 1 end) as sin_resolver')
            ->selectRaw(
                'sum(case when comprobante_proveedor.estado <> ? or comprobante_proveedor.asiento_id is null'
                .' then 1 else 0 end) as sin_contabilizar',
                [ComprobanteProveedorEstados::CONTABILIZADO]
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' then 1 else 0 end) as con_deuda',
                TrackingPagoEstado::conDeuda()
            )
            // Tramos de aging: cantidad y monto de saldo (dashboard premium).
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' < 0 then 1 else 0 end) as deuda_corriente',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' < 0'
                .' then coalesce(comprobante_tracking_indice.pago_saldo, 0) else 0 end) as saldo_corriente',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 0 and 30 then 1 else 0 end) as deuda_0_30',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 0 and 30'
                .' then coalesce(comprobante_tracking_indice.pago_saldo, 0) else 0 end) as saldo_0_30',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 31 and 60 then 1 else 0 end) as deuda_31_60',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 31 and 60'
                .' then coalesce(comprobante_tracking_indice.pago_saldo, 0) else 0 end) as saldo_31_60',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 61 and 90 then 1 else 0 end) as deuda_61_90',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' between 61 and 90'
                .' then coalesce(comprobante_tracking_indice.pago_saldo, 0) else 0 end) as saldo_61_90',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' > 90 then 1 else 0 end) as deuda_90_mas',
                TrackingPagoEstado::conDeuda()
            )
            ->selectRaw(
                'sum(case when '.$conDeuda.' and '.$diasAging.' > 90'
                .' then coalesce(comprobante_tracking_indice.pago_saldo, 0) else 0 end) as saldo_90_mas',
                TrackingPagoEstado::conDeuda()
            )
            ->first();

        return [
            'registros' => (int) ($fila->registros ?? 0),
            'total' => (float) ($fila->total ?? 0),
            'saldo' => (float) ($fila->saldo ?? 0),
            'con_pdf' => (int) ($fila->con_pdf ?? 0),
            'sin_pdf' => (int) ($fila->sin_pdf ?? 0),
            'sin_pdf_externos' => (int) ($fila->sin_pdf_externos ?? 0),
            'sin_resolver' => (int) ($fila->sin_resolver ?? 0),
            'sin_contabilizar' => (int) ($fila->sin_contabilizar ?? 0),
            'con_deuda' => (int) ($fila->con_deuda ?? 0),
            'deuda_corriente' => (int) ($fila->deuda_corriente ?? 0),
            'deuda_0_30' => (int) ($fila->deuda_0_30 ?? 0),
            'deuda_31_60' => (int) ($fila->deuda_31_60 ?? 0),
            'deuda_61_90' => (int) ($fila->deuda_61_90 ?? 0),
            'deuda_90_mas' => (int) ($fila->deuda_90_mas ?? 0),
            'saldo_corriente' => (float) ($fila->saldo_corriente ?? 0),
            'saldo_0_30' => (float) ($fila->saldo_0_30 ?? 0),
            'saldo_31_60' => (float) ($fila->saldo_31_60 ?? 0),
            'saldo_61_90' => (float) ($fila->saldo_61_90 ?? 0),
            'saldo_90_mas' => (float) ($fila->saldo_90_mas ?? 0),
        ];
    }

    /**
     * Un comprobante del tracking con las columnas del índice ya resueltas.
     */
    public function findParaTracking(int $id): ?Comprobante_Proveedor
    {
        return $this->consultaBase()->where('comprobante_proveedor.id', $id)->first();
    }

    /**
     * Modelos completos (con relaciones) para alimentar los resolutores.
     *
     * La consulta de la grilla trae columnas planas del join; los resolutores
     * necesitan el modelo con sus relaciones, así que se recarga por ID.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Comprobante_Proveedor>
     */
    public function modelosPorIds(array $ids)
    {
        $query = $this->model->newQuery()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'empresas',
                'comprobante_proveedor_archivos',
                'precarga_comprobante_proveedores',
            ])
            ->whereIn('comprobante_proveedor.id', $ids);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor.empresa_id');

        return $query->get();
    }

    /**
     * Empresas que el usuario puede elegir en el filtro del encabezado.
     *
     * Sin empresas asignadas el ERP entiende acceso total (así lo trata
     * `aplicarFiltroEmpresasAsignadas`), de modo que la lista vacía se traduce
     * a todas y no a ninguna.
     *
     * @return array<int, string>
     */
    public function empresasParaFiltro(): array
    {
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $query = Empresa::query()->orderBy('nombre');
        if (count($asignadas) >= 1) {
            $query->whereIn('id', $asignadas);
        }

        return $query->pluck('nombre', 'id')->all();
    }

    /**
     * @return Builder<Comprobante_Proveedor>
     */
    private function consultaBase(): Builder
    {
        $query = $this->model->newQuery()
            ->select([
                'comprobante_proveedor.id as id',
                'comprobante_proveedor.empresa_id as empresa_id',
                'comprobante_proveedor.proveedor_id as proveedor_id',
                'comprobante_proveedor.tipotransaccion_compra_id as tipotransaccion_compra_id',
                'comprobante_proveedor.ordencompra_id as ordencompra_id',
                'comprobante_proveedor.letra as letra',
                'comprobante_proveedor.sucursal as sucursal',
                'comprobante_proveedor.numerocomprobante as numerocomprobante',
                'comprobante_proveedor.fechacomprobante as fechacomprobante',
                'comprobante_proveedor.fechaiva as fechaiva',
                'comprobante_proveedor.fechavencimiento as fechavencimiento',
                'comprobante_proveedor.total as total',
                'comprobante_proveedor.estado as estado',
                'comprobante_proveedor.asiento_id as asiento_id',
                'comprobante_proveedor.anita_nro_interno as anita_nro_interno',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'proveedor.codigo as codigoproveedor',
                'proveedor.nroinscripcion as cuitproveedor',
                'tipotransaccion_compra.nombre as nombretipotransaccion_compra',
                'tipotransaccion_compra.abreviatura as abreviaturatipotransaccion_compra',
                'tipotransaccion_compra.codigoafip as codigoafiptipotransaccion_compra',
                'comprobante_tracking_indice.pdf_disponible as pdf_disponible',
                'comprobante_tracking_indice.pdf_origen as pdf_origen',
                'comprobante_tracking_indice.pdf_documento_id as pdf_documento_id',
                'comprobante_tracking_indice.pdf_ruta as pdf_ruta',
                'comprobante_tracking_indice.fechacarga_efectiva as fechacarga_efectiva',
                'comprobante_tracking_indice.fechacarga_origen as fechacarga_origen',
                'comprobante_tracking_indice.pago_estado as pago_estado',
                'comprobante_tracking_indice.pago_origen as pago_origen',
                'comprobante_tracking_indice.pago_saldo as pago_saldo',
                'comprobante_tracking_indice.pago_fecha as pago_fecha',
                'comprobante_tracking_indice.pago_op_referencia as pago_op_referencia',
                'comprobante_tracking_indice.pago_op_cantidad as pago_op_cantidad',
                'comprobante_tracking_indice.pago_op_id as pago_op_id',
                'comprobante_tracking_indice.sincronizado_at as sincronizado_at',
                // Fecha real de contabilización: la del asiento, no la del
                // comprobante. En el histórico importado difieren en casi
                // todos los casos porque el asiento se armó días después.
                'asiento.fecha as fechacontabilizacion',
                'asiento.numeroasiento as numeroasiento',
                'ordencompra.numeroordencompra as numeroordencompra',
            ])
            ->join('empresa', 'empresa.id', '=', 'comprobante_proveedor.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'comprobante_proveedor.proveedor_id')
            ->join(
                'tipotransaccion_compra',
                'tipotransaccion_compra.id',
                '=',
                'comprobante_proveedor.tipotransaccion_compra_id'
            )
            ->leftJoin('asiento', 'asiento.id', '=', 'comprobante_proveedor.asiento_id')
            ->leftJoin('ordencompra', 'ordencompra.id', '=', 'comprobante_proveedor.ordencompra_id')
            // Left join: el comprobante recién cargado todavía no está indexado
            // y tiene que verse igual, con el PDF y el pago como «pendiente».
            ->leftJoin(
                'comprobante_tracking_indice',
                'comprobante_tracking_indice.comprobante_proveedor_id',
                '=',
                'comprobante_proveedor.id'
            );

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'comprobante_proveedor.empresa_id');

        return $query;
    }

    /**
     * @param  Builder<Comprobante_Proveedor>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function ordenar(Builder $query, array $filtros): void
    {
        $eje = (string) ($filtros['eje_fecha'] ?? TrackingFacturasListadoFiltros::EJE_FECHA_COMPROBANTE);

        // Se ordena por el mismo eje que se filtró: si el usuario pidió
        // «cargados entre fechas», espera ver la lista por fecha de carga.
        $query->orderByDesc(TrackingFacturasListadoFiltros::columnaEjeFecha($eje))
            ->orderByDesc('comprobante_proveedor.id');
    }

    /**
     * Familias presentes en el universo del usuario, para armar el filtro sin
     * ofrecer opciones que no devolverían nada.
     *
     * @return array<string, string>
     */
    public function familiasDisponibles(): array
    {
        $filas = $this->consultaBase()
            ->reorder()
            ->select('tipotransaccion_compra.codigoafip', 'tipotransaccion_compra.abreviatura')
            ->distinct()
            ->get();

        $familias = [];
        foreach ($filas as $fila) {
            $familia = TrackingComprobanteFamilia::desde($fila->codigoafip, $fila->abreviatura);
            $familias[$familia] = TrackingComprobanteFamilia::etiqueta($familia);
        }

        return array_intersect_key(TrackingComprobanteFamilia::opcionesFiltro(), $familias);
    }
}
