<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorReporteFiltros;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecepcionProveedorReporteService
{

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     totales: array<string, mixed>,
     *     kpis: array<string, mixed>,
     *     advertencia_cotizacion: ?string
     * }
     */
    public function generar(array $filtros): array
    {
        $lineas = $this->consultarLineas($filtros);
        $lineas = $this->enriquecerMonedaLocal($lineas);

        $modo = (string) ($filtros['modo'] ?? RecepcionProveedorReporteFiltros::MODO_DETALLE);
        $filasDatos = $modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN
            ? $this->agruparPorCom($lineas)
            : $lineas;

        $totales = $this->calcularTotales($lineas);
        $kpis = $this->calcularKpis($lineas);
        $advertencia = $this->advertenciaCotizacion($lineas);

        $filas = $this->aplicarPresentacion($filasDatos, $filtros);

        return [
            'filas' => $filas,
            'totales' => $totales,
            'kpis' => $kpis,
            'advertencia_cotizacion' => $advertencia,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $filas
     */
    public function paginarFilas(Collection|array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $coleccion = $filas instanceof Collection ? $filas->values() : collect($filas)->values();
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = $coleccion->count();
        $items = $coleccion->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery): string
    {
        $partes = [];
        $partes[] = 'Período '.RecepcionProveedorReporteFiltros::formatearPeriodoTexto($filtros);
        $partes[] = RecepcionProveedorReporteFiltros::etiquetaModo((string) ($filtros['modo'] ?? ''));
        $partes[] = 'Orden: '.RecepcionProveedorReporteFiltros::etiquetaOrden((string) ($filtros['orden'] ?? ''));

        $ids = RecepcionProveedorReporteFiltros::empresaIds($filtros);
        $nombres = $empresaQuery
            ->filter(fn ($e) => in_array((int) $e->id, $ids, true))
            ->pluck('nombre')
            ->filter()
            ->values();
        if ($nombres->isNotEmpty()) {
            $etiquetaEmp = $nombres->count() <= 3
                ? $nombres->implode(', ')
                : $nombres->count().' empresas';
            if (empty($filtros['consolidar_empresas']) && $nombres->count() > 1) {
                $etiquetaEmp .= ' (por empresa)';
            }
            $partes[] = $etiquetaEmp;
        }

        $facturacion = (string) ($filtros['facturacion'] ?? RecepcionProveedorReporteFiltros::FACTURACION_TODAS);
        if ($facturacion !== RecepcionProveedorReporteFiltros::FACTURACION_TODAS) {
            $partes[] = RecepcionProveedorReporteFiltros::etiquetaFacturacion($facturacion);
        }
        if (($filtros['tipo'] ?? '') === Recepcion_Proveedor::TIPO_DEVOLUCION) {
            $partes[] = 'Solo devoluciones';
        } elseif (($filtros['tipo'] ?? '') === Recepcion_Proveedor::TIPO_RECEPCION) {
            $partes[] = 'Solo recepciones';
        }
        if (! empty($filtros['solo_diferencias'])) {
            $partes[] = 'Solo con diferencias';
        }
        if (! empty($filtros['solo_rechazadas'])) {
            $partes[] = 'Solo rechazadas';
        }
        if (trim((string) ($filtros['proveedor'] ?? '')) !== '') {
            $partes[] = 'Proveedor: '.$filtros['proveedor'];
        }
        if (trim((string) ($filtros['sku'] ?? '')) !== '') {
            $partes[] = 'SKU: '.$filtros['sku'];
        }
        if (trim((string) ($filtros['deposito'] ?? '')) !== '') {
            $partes[] = 'Depósito: '.$filtros['deposito'];
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    private function consultarLineas(array $filtros): Collection
    {
        $npuSub = DB::table('recepcion_proveedor_parte_unica')
            ->groupBy('recepcion_proveedor_articulo_id')
            ->select([
                'recepcion_proveedor_articulo_id',
                DB::raw('MIN(numeroparte) as npu_desde'),
                DB::raw('MAX(numeroparte) as npu_hasta'),
            ]);

        $entregadoSub = DB::table('recepcion_proveedor_articulo as rpa_ent')
            ->join('recepcion_proveedor as rp_ent', 'rp_ent.id', '=', 'rpa_ent.recepcion_proveedor_id')
            ->where('rp_ent.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp_ent.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->whereNotNull('rpa_ent.ordencompra_articulo_id')
            ->groupBy('rpa_ent.ordencompra_articulo_id')
            ->select([
                'rpa_ent.ordencompra_articulo_id',
                DB::raw('SUM(rpa_ent.cantidad) as cantidad_entregada_oc'),
            ]);

        $facturaSub = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->where('cp.estado', '!=', ComprobanteProveedorEstados::ANULADO)
            ->whereNull('cp.deleted_at')
            ->groupBy('cpr.recepcion_proveedor_id')
            ->select([
                'cpr.recepcion_proveedor_id',
                DB::raw('MAX(cp.id) as comprobante_proveedor_id'),
            ]);

        $query = Recepcion_Proveedor_Articulo::query()
            ->from('recepcion_proveedor_articulo')
            ->join('recepcion_proveedor', 'recepcion_proveedor.id', '=', 'recepcion_proveedor_articulo.recepcion_proveedor_id')
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->leftJoin('ordencompra', 'ordencompra.id', '=', 'recepcion_proveedor.ordencompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->leftJoin('articulo', 'articulo.id', '=', 'recepcion_proveedor_articulo.articulo_id')
            ->leftJoin('categoria', 'categoria.id', '=', 'articulo.categoria_id')
            ->leftJoin('subcategoria', 'subcategoria.id', '=', 'articulo.subcategoria_id')
            ->leftJoin('tipoarticulo', 'tipoarticulo.id', '=', 'articulo.tipoarticulo_id')
            ->leftJoin('cuentacontable', 'cuentacontable.id', '=', 'articulo.cuentacontablecompra_id')
            ->leftJoin('centrocosto as cc_lin', 'cc_lin.id', '=', 'recepcion_proveedor_articulo.centrocosto_id')
            ->leftJoin('centrocosto as cc_cab', 'cc_cab.id', '=', 'recepcion_proveedor.centrocosto_id')
            ->leftJoin('centrocosto as cc_req', 'cc_req.id', '=', 'requisicion.centrocostodestino_arbol_id')
            ->leftJoin('depmae as dep_lin', 'dep_lin.id', '=', 'recepcion_proveedor_articulo.deposito_id')
            ->leftJoin('depmae as dep_cab', 'dep_cab.id', '=', 'recepcion_proveedor.deposito_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'recepcion_proveedor.creousuario_id')
            ->leftJoin('recepcion_proveedor as rp_orig', 'rp_orig.id', '=', 'recepcion_proveedor.recepcion_referencia_id')
            ->leftJoin('usuario as u_orig', 'u_orig.id', '=', 'rp_orig.creousuario_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'recepcion_proveedor.moneda_id')
            ->leftJoin('asiento', 'asiento.id', '=', 'recepcion_proveedor.asiento_id')
            ->leftJoin('unidadmedida', 'unidadmedida.id', '=', 'recepcion_proveedor_articulo.unidadmedida_id')
            ->leftJoinSub($npuSub, 'npu', 'npu.recepcion_proveedor_articulo_id', '=', 'recepcion_proveedor_articulo.id')
            ->leftJoinSub($entregadoSub, 'ent', 'ent.ordencompra_articulo_id', '=', 'recepcion_proveedor_articulo.ordencompra_articulo_id')
            ->leftJoinSub($facturaSub, 'fac', 'fac.recepcion_proveedor_id', '=', 'recepcion_proveedor.id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'fac.comprobante_proveedor_id')
            ->select([
                'recepcion_proveedor_articulo.id as linea_id',
                'recepcion_proveedor_articulo.articulo_id',
                'recepcion_proveedor_articulo.cantidad',
                'recepcion_proveedor_articulo.cantidad_oc',
                'recepcion_proveedor_articulo.cantidad_rechazada',
                'recepcion_proveedor_articulo.precio',
                'recepcion_proveedor_articulo.precio_ordencompra',
                'recepcion_proveedor_articulo.descuento',
                'recepcion_proveedor_articulo.fl_precio_diferencia',
                'recepcion_proveedor_articulo.fl_cantidad_diferencia',
                'recepcion_proveedor_articulo.fl_articulo_distinto',
                'recepcion_proveedor_articulo.motivorechazo',
                'recepcion_proveedor_articulo.comentario_diferencia',
                'recepcion_proveedor_articulo.comentario_precio',
                'recepcion_proveedor_articulo.detalle',
                'recepcion_proveedor_articulo.tipo_linea',
                'recepcion_proveedor_articulo.orden as linea_orden',
                'recepcion_proveedor.id as recepcion_id',
                'recepcion_proveedor.numerorecepcion',
                'recepcion_proveedor.fecha',
                'recepcion_proveedor.tipo',
                'recepcion_proveedor.estado',
                'recepcion_proveedor.numerofactura',
                'recepcion_proveedor.empresa_id',
                'recepcion_proveedor.proveedor_id',
                'recepcion_proveedor.ordencompra_id',
                'recepcion_proveedor.moneda_id',
                'recepcion_proveedor.cotizacion',
                'recepcion_proveedor.observacion',
                'recepcion_proveedor.anita_letra',
                'recepcion_proveedor.anita_sucursal',
                'recepcion_proveedor.anita_nro',
                'recepcion_proveedor.fl_precio_diferencia as fl_precio_diferencia_cab',
                'recepcion_proveedor.fl_diferencia_cantidad as fl_diferencia_cantidad_cab',
                'recepcion_proveedor.fl_articulo_extra as fl_articulo_extra_cab',
                'recepcion_proveedor.fl_faltante_oc as fl_faltante_oc_cab',
                'recepcion_proveedor.fl_linea_rechazada as fl_linea_rechazada_cab',
                'recepcion_proveedor.fl_precio_pendiente_aprobacion',
                'recepcion_proveedor.asiento_id',
                'empresa.nombre as nombreempresa',
                'proveedor.codigo as codigo_proveedor',
                'proveedor.nombre as nombreproveedor',
                'articulo.sku',
                'articulo.descripcion as descripcion_articulo',
                'categoria.nombre as nombre_categoria',
                'subcategoria.nombre as nombre_subcategoria',
                'tipoarticulo.nombre as nombre_tipoarticulo',
                'cuentacontable.id as cuentacontable_id',
                'cuentacontable.codigo as codigo_cuenta',
                'cuentacontable.nombre as nombre_cuenta',
                'cc_lin.codigo as codigo_cc_linea',
                'cc_lin.nombre as nombre_cc_linea',
                'cc_cab.codigo as codigo_cc_cab',
                'cc_req.codigo as codigo_cc_req',
                'dep_lin.codigo as codigo_deposito_linea',
                'dep_lin.nombre as nombre_deposito_linea',
                'dep_cab.codigo as codigo_deposito_cab',
                'dep_cab.nombre as nombre_deposito_cab',
                'usuario.nombre as nombre_usuario',
                'usuario.usuario as login_usuario',
                'u_orig.nombre as nombre_usuario_orig',
                'u_orig.usuario as login_usuario_orig',
                'rp_orig.numerorecepcion as numerorecepcion_orig',
                'moneda.abreviatura as moneda_abreviatura',
                'asiento.numeroasiento',
                'unidadmedida.abreviatura as um_abreviatura',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha as fecha_oc',
                'requisicion.id as requisicion_id',
                'requisicion.numerorequisicion',
                'requisicion.fecha as fecha_requisicion',
                'npu.npu_desde',
                'npu.npu_hasta',
                'ent.cantidad_entregada_oc',
                'fac.comprobante_proveedor_id',
                'cp.letra as factura_letra',
                'cp.sucursal as factura_sucursal',
                'cp.numerocomprobante as factura_numero',
                'cp.fechacomprobante as factura_fecha',
                'cp.estado as factura_estado',
            ]);

        RecepcionProveedorVisibilidadSupport::aplicarFiltroListado($query);
        $this->aplicarFiltros($query, $filtros);
        $this->aplicarOrden($query, $filtros);

        return $query->get()->map(fn ($row) => $this->mapearLinea($row))->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Recepcion_Proveedor_Articulo>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros($query, array $filtros): void
    {
        $empresaIds = RecepcionProveedorReporteFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('recepcion_proveedor.empresa_id', $empresaIds);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('recepcion_proveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('recepcion_proveedor.fecha', '<=', $filtros['fecha_hasta']);
        }

        $estado = (string) ($filtros['estado'] ?? RecepcionProveedorReporteFiltros::ESTADO_CONFIRMADA);
        if ($estado !== RecepcionProveedorReporteFiltros::ESTADO_TODAS) {
            $query->where('recepcion_proveedor.estado', $estado);
        }

        $tipo = (string) ($filtros['tipo'] ?? RecepcionProveedorReporteFiltros::TIPO_TODAS);
        if ($tipo !== RecepcionProveedorReporteFiltros::TIPO_TODAS) {
            $query->where('recepcion_proveedor.tipo', $tipo);
        }

        $facturacion = (string) ($filtros['facturacion'] ?? RecepcionProveedorReporteFiltros::FACTURACION_TODAS);
        if ($facturacion === RecepcionProveedorReporteFiltros::FACTURACION_FACTURADAS) {
            $query->whereNotNull('fac.comprobante_proveedor_id');
        } elseif ($facturacion === RecepcionProveedorReporteFiltros::FACTURACION_NO_FACTURADAS) {
            $query->whereNull('fac.comprobante_proveedor_id')
                ->where('recepcion_proveedor.tipo', Recepcion_Proveedor::TIPO_RECEPCION);
        }

        if (! empty($filtros['solo_diferencias'])) {
            $query->where(function ($q) {
                $q->where('recepcion_proveedor_articulo.fl_precio_diferencia', true)
                    ->orWhere('recepcion_proveedor_articulo.fl_cantidad_diferencia', true)
                    ->orWhere('recepcion_proveedor_articulo.fl_articulo_distinto', true)
                    ->orWhere('recepcion_proveedor.fl_precio_diferencia', true)
                    ->orWhere('recepcion_proveedor.fl_diferencia_cantidad', true)
                    ->orWhere('recepcion_proveedor.fl_articulo_extra', true)
                    ->orWhere('recepcion_proveedor.fl_faltante_oc', true);
            });
        }

        if (! empty($filtros['solo_rechazadas'])) {
            $query->where(function ($q) {
                $q->where('recepcion_proveedor_articulo.cantidad_rechazada', '>', 0)
                    ->orWhere('recepcion_proveedor.fl_linea_rechazada', true);
            });
        }

        $proveedor = trim((string) ($filtros['proveedor'] ?? ''));
        if ($proveedor !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $proveedor).'%';
            $query->where(function ($q) use ($like, $proveedor) {
                $q->where('proveedor.codigo', 'like', $like)
                    ->orWhere('proveedor.nombre', 'like', $like);
                if (ctype_digit($proveedor)) {
                    $q->orWhere('proveedor.codigo', $proveedor);
                }
            });
        }

        $sku = trim((string) ($filtros['sku'] ?? ''));
        if ($sku !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $sku).'%';
            $query->where(function ($q) use ($like) {
                $q->where('articulo.sku', 'like', $like)
                    ->orWhere('articulo.descripcion', 'like', $like);
            });
        }

        $deposito = trim((string) ($filtros['deposito'] ?? ''));
        if ($deposito !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $deposito).'%';
            $query->where(function ($q) use ($like) {
                $q->where('dep_lin.codigo', 'like', $like)
                    ->orWhere('dep_cab.codigo', 'like', $like)
                    ->orWhere('dep_lin.nombre', 'like', $like)
                    ->orWhere('dep_cab.nombre', 'like', $like);
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Recepcion_Proveedor_Articulo>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarOrden($query, array $filtros): void
    {
        $orden = (string) ($filtros['orden'] ?? RecepcionProveedorReporteFiltros::ORDEN_FECHA);

        match ($orden) {
            RecepcionProveedorReporteFiltros::ORDEN_ARTICULO => $query
                ->orderBy('articulo.sku')
                ->orderBy('recepcion_proveedor.fecha')
                ->orderBy('recepcion_proveedor.numerorecepcion'),
            RecepcionProveedorReporteFiltros::ORDEN_PROVEEDOR => $query
                ->orderBy('proveedor.nombre')
                ->orderBy('recepcion_proveedor.fecha')
                ->orderBy('recepcion_proveedor.numerorecepcion'),
            RecepcionProveedorReporteFiltros::ORDEN_CENTROCOSTO => $query
                ->orderBy('cc_lin.codigo')
                ->orderBy('articulo.sku')
                ->orderBy('recepcion_proveedor.fecha'),
            RecepcionProveedorReporteFiltros::ORDEN_CUENTA => $query
                ->orderBy('cuentacontable.codigo')
                ->orderBy('articulo.sku')
                ->orderBy('recepcion_proveedor.fecha'),
            RecepcionProveedorReporteFiltros::ORDEN_COMPROBANTE => $query
                ->orderBy('recepcion_proveedor.numerorecepcion')
                ->orderBy('recepcion_proveedor_articulo.orden'),
            default => $query
                ->orderBy('recepcion_proveedor.fecha')
                ->orderBy('recepcion_proveedor.numerorecepcion')
                ->orderBy('recepcion_proveedor_articulo.orden'),
        };

        $query->orderBy('recepcion_proveedor_articulo.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearLinea(object $row): array
    {
        $signo = ((string) ($row->tipo ?? '')) === Recepcion_Proveedor::TIPO_DEVOLUCION ? -1.0 : 1.0;
        $cantidad = (float) ($row->cantidad ?? 0) * $signo;
        $cantidadOc = (float) ($row->cantidad_oc ?? 0);
        $cantidadRechazada = (float) ($row->cantidad_rechazada ?? 0) * $signo;
        $precio = (float) ($row->precio ?? 0);
        $precioOc = (float) ($row->precio_ordencompra ?? 0);
        $descuento = (float) ($row->descuento ?? 0);
        $precioNeto = $precio * (1 - ($descuento / 100));
        $total = round($cantidad * $precioNeto, 4);
        $difUnidades = $cantidadOc > 0 ? round($cantidad - ($cantidadOc * $signo), 4) : 0.0;
        $entregadoOc = (float) ($row->cantidad_entregada_oc ?? 0);
        $pendiente = $cantidadOc > 0 ? round($cantidadOc - $entregadoOc, 4) : 0.0;
        $varPrecioPct = ($precioOc > 0.0001)
            ? round((($precioNeto - $precioOc) / $precioOc) * 100, 2)
            : 0.0;

        $codigoDep = (string) ($row->codigo_deposito_linea ?: $row->codigo_deposito_cab ?: '');
        $nombreDep = (string) ($row->nombre_deposito_linea ?: $row->nombre_deposito_cab ?: '');
        $codigoCc = (string) ($row->codigo_cc_linea ?: $row->codigo_cc_cab ?: '');
        $facturado = (int) ($row->comprobante_proveedor_id ?? 0) > 0;
        $fecha = $this->fechaYmd($row->fecha ?? null);
        $diasSinFacturar = null;
        if (! $facturado
            && ((string) ($row->tipo ?? '')) === Recepcion_Proveedor::TIPO_RECEPCION
            && ((string) ($row->estado ?? '')) === RecepcionProveedorEstados::CONFIRMADA
            && $fecha !== '') {
            try {
                $diasSinFacturar = Carbon::parse($fecha)->diffInDays(Carbon::today());
            } catch (\Throwable) {
                $diasSinFacturar = null;
            }
        }

        $tieneDiff = (bool) ($row->fl_precio_diferencia ?? false)
            || (bool) ($row->fl_cantidad_diferencia ?? false)
            || (bool) ($row->fl_articulo_distinto ?? false)
            || (bool) ($row->fl_precio_diferencia_cab ?? false)
            || (bool) ($row->fl_diferencia_cantidad_cab ?? false)
            || (bool) ($row->fl_articulo_extra_cab ?? false)
            || (bool) ($row->fl_faltante_oc_cab ?? false);

        $comentario = trim(implode(' · ', array_filter([
            trim((string) ($row->motivorechazo ?? '')),
            trim((string) ($row->comentario_diferencia ?? '')),
            trim((string) ($row->comentario_precio ?? '')),
            trim((string) ($row->detalle ?? '')),
        ])));

        $estadoFacturacion = $this->etiquetaEstadoFacturacion(
            (string) ($row->tipo ?? ''),
            $facturado,
            (string) ($row->factura_estado ?? ''),
        );

        return [
            'tipo_fila' => 'dato',
            'linea_id' => (int) ($row->linea_id ?? 0),
            'recepcion_id' => (int) ($row->recepcion_id ?? 0),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'ordencompra_id' => (int) ($row->ordencompra_id ?? 0),
            'requisicion_id' => (int) ($row->requisicion_id ?? 0),
            'cuentacontable_id' => (int) ($row->cuentacontable_id ?? 0),
            'comprobante_proveedor_id' => (int) ($row->comprobante_proveedor_id ?? 0),
            'asiento_id' => (int) ($row->asiento_id ?? 0),
            'empresa_id' => (int) ($row->empresa_id ?? 0),
            'proveedor_id' => (int) ($row->proveedor_id ?? 0),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'sku' => (string) ($row->sku ?? ''),
            'descripcion_articulo' => (string) ($row->descripcion_articulo ?? ''),
            'npu_desde' => $row->npu_desde !== null ? (string) $row->npu_desde : '',
            'npu_hasta' => $row->npu_hasta !== null ? (string) $row->npu_hasta : '',
            'nombre_categoria' => (string) ($row->nombre_categoria ?? ''),
            'nombre_subcategoria' => (string) ($row->nombre_subcategoria ?? ''),
            'nombre_tipoarticulo' => (string) ($row->nombre_tipoarticulo ?? ''),
            'numerorecepcion' => (string) ($row->numerorecepcion ?? ''),
            'com_anita' => $this->formatoAnitaCom(
                $row->anita_letra ?? null,
                $row->anita_sucursal ?? null,
                $row->anita_nro ?? null,
            ),
            'codigo_proveedor' => (string) ($row->codigo_proveedor ?? ''),
            'nombreproveedor' => (string) ($row->nombreproveedor ?? ''),
            'fecha' => $fecha,
            'fecha_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($fecha),
            'tipo' => (string) ($row->tipo ?? ''),
            'estado' => (string) ($row->estado ?? ''),
            'cantidad' => $cantidad,
            'cantidad_oc' => $cantidadOc,
            'cantidad_rechazada' => $cantidadRechazada,
            'cantidad_entregada_oc' => $entregadoOc,
            'um_abreviatura' => (string) ($row->um_abreviatura ?? ''),
            'precio' => $precioNeto,
            'precio_oc' => $precioOc,
            'total' => $total,
            'numeroordencompra' => $row->numeroordencompra !== null ? (string) $row->numeroordencompra : '',
            'fecha_oc' => $this->fechaYmd($row->fecha_oc ?? null),
            'fecha_oc_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($this->fechaYmd($row->fecha_oc ?? null)),
            'codigo_cc' => $codigoCc,
            'nombre_cc' => (string) ($row->nombre_cc_linea ?? ''),
            'codigo_cc_req' => (string) ($row->codigo_cc_req ?? ''),
            'codigo_cuenta' => (string) ($row->codigo_cuenta ?? ''),
            'nombre_cuenta' => (string) ($row->nombre_cuenta ?? ''),
            'dif_unidades' => $difUnidades,
            'pendiente' => $pendiente,
            'var_precio_pct' => $varPrecioPct,
            'numerofactura' => (string) ($row->numerofactura ?? ''),
            'numerorequisicion' => $row->numerorequisicion !== null ? (string) $row->numerorequisicion : '',
            'fecha_requisicion' => $this->fechaYmd($row->fecha_requisicion ?? null),
            'fecha_requisicion_fmt' => RecepcionProveedorReporteFiltros::formatearFechaPantalla($this->fechaYmd($row->fecha_requisicion ?? null)),
            'comentario' => $comentario,
            'usuario' => (string) ($row->nombre_usuario ?: $row->login_usuario ?: ''),
            'usuario_orig' => (string) ($row->nombre_usuario_orig ?: $row->login_usuario_orig ?: ''),
            'numerorecepcion_orig' => $row->numerorecepcion_orig !== null ? (string) $row->numerorecepcion_orig : '',
            'codigo_deposito' => $codigoDep,
            'nombre_deposito' => $nombreDep,
            'numeroasiento' => $row->numeroasiento !== null ? (string) $row->numeroasiento : '',
            'moneda_id' => (int) ($row->moneda_id ?? 1),
            'moneda_abreviatura' => (string) ($row->moneda_abreviatura ?? ''),
            'cotizacion' => (float) ($row->cotizacion ?? 0),
            'facturado' => $facturado,
            'estado_facturacion' => $estadoFacturacion,
            'factura_erp' => $this->formatoFacturaErp(
                $row->factura_letra ?? null,
                $row->factura_sucursal ?? null,
                $row->factura_numero ?? null,
            ),
            'factura_fecha' => $this->fechaYmd($row->factura_fecha ?? null),
            'dias_sin_facturar' => $diasSinFacturar,
            'tiene_diff' => $tieneDiff,
            'fl_precio_pendiente' => (bool) ($row->fl_precio_pendiente_aprobacion ?? false),
            'tipo_linea' => (string) ($row->tipo_linea ?? ''),
            'clave_grupo' => '',
            'etiqueta_grupo' => '',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @return Collection<int, array<string, mixed>>
     */
    private function enriquecerMonedaLocal(Collection $lineas): Collection
    {
        return $lineas->map(function (array $fila) {
            $monedaId = (int) ($fila['moneda_id'] ?? 1);
            $cotDoc = (float) ($fila['cotizacion'] ?? 0);
            $fecha = (string) ($fila['fecha'] ?? '');
            $total = (float) ($fila['total'] ?? 0);

            $resuelto = $this->resolverCotizacionMn($monedaId, $cotDoc, $fecha);
            $fila['cotizacion_usada'] = $resuelto['valor'];
            $fila['cotizacion_fecha'] = $resuelto['fecha'];
            $fila['cotizacion_exacta'] = $resuelto['exacta'];
            $fila['cotizacion_hacia_adelante'] = $resuelto['hacia_adelante'];
            $fila['importe_mn'] = round($total * $resuelto['valor'], 4);

            return $fila;
        });
    }

    /**
     * @return array{valor: float, fecha: ?string, exacta: bool, hacia_adelante: bool}
     */
    private function resolverCotizacionMn(int $monedaId, float $cotizacionDoc, string $fecha): array
    {
        if ($monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return ['valor' => 1.0, 'fecha' => $fecha !== '' ? $fecha : null, 'exacta' => true, 'hacia_adelante' => false];
        }

        if ($cotizacionDoc > 0.0001 && abs($cotizacionDoc - 1.0) > 0.0001) {
            return ['valor' => $cotizacionDoc, 'fecha' => $fecha !== '' ? $fecha : null, 'exacta' => true, 'hacia_adelante' => false];
        }

        $cot = CotizacionVigenteSupport::venta($fecha !== '' ? $fecha : null, $monedaId);

        return [
            'valor' => (float) ($cot['valor'] ?? 0),
            'fecha' => $cot['fecha'] ?? null,
            'exacta' => (bool) ($cot['exacta'] ?? false),
            'hacia_adelante' => (bool) ($cot['hacia_adelante'] ?? false),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @return Collection<int, array<string, mixed>>
     */
    private function agruparPorCom(Collection $lineas): Collection
    {
        return $lineas
            ->groupBy(fn (array $f) => (int) ($f['recepcion_id'] ?? 0))
            ->map(function (Collection $grupo) {
                $base = $grupo->first() ?? [];
                $cantidad = round($grupo->sum(fn (array $f) => (float) ($f['cantidad'] ?? 0)), 4);
                $total = round($grupo->sum(fn (array $f) => (float) ($f['total'] ?? 0)), 4);
                $importeMn = round($grupo->sum(fn (array $f) => (float) ($f['importe_mn'] ?? 0)), 4);
                $rechazada = round($grupo->sum(fn (array $f) => (float) ($f['cantidad_rechazada'] ?? 0)), 4);
                $categorias = $grupo->pluck('nombre_categoria')->filter()->unique()->values();

                return array_merge($base, [
                    'tipo_fila' => 'dato',
                    'linea_id' => 0,
                    'articulo_id' => 0,
                    'sku' => '',
                    'descripcion_articulo' => $categorias->take(3)->implode(', '),
                    'nombre_categoria' => $categorias->implode(', '),
                    'cantidad' => $cantidad,
                    'cantidad_rechazada' => $rechazada,
                    'total' => $total,
                    'importe_mn' => $importeMn,
                    'lineas_com' => $grupo->count(),
                    'tiene_diff' => $grupo->contains(fn (array $f) => ! empty($f['tiene_diff'])),
                ]);
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private function calcularTotales(Collection $lineas): array
    {
        $datos = $lineas->filter(fn (array $f) => ($f['tipo_fila'] ?? 'dato') === 'dato');

        return [
            'cantidad_filas' => $datos->count(),
            'cantidad_com' => $datos->pluck('recepcion_id')->unique()->count(),
            'cantidad_total' => round($datos->sum(fn (array $f) => (float) ($f['cantidad'] ?? 0)), 4),
            'importe_total' => round($datos->sum(fn (array $f) => (float) ($f['total'] ?? 0)), 4),
            'importe_mn' => round($datos->sum(fn (array $f) => (float) ($f['importe_mn'] ?? 0)), 4),
            'con_diferencia' => $datos->filter(fn (array $f) => ! empty($f['tiene_diff']))->count(),
            'sin_facturar' => $datos
                ->filter(fn (array $f) => empty($f['facturado']) && ($f['tipo'] ?? '') === Recepcion_Proveedor::TIPO_RECEPCION)
                ->pluck('recepcion_id')
                ->unique()
                ->count(),
            'devoluciones' => $datos->filter(fn (array $f) => ($f['tipo'] ?? '') === Recepcion_Proveedor::TIPO_DEVOLUCION)->count(),
            'rechazadas' => $datos->filter(fn (array $f) => abs((float) ($f['cantidad_rechazada'] ?? 0)) > 0.0001)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    private function calcularKpis(Collection $lineas): array
    {
        $totales = $this->calcularTotales($lineas);
        $aging = $lineas
            ->filter(fn (array $f) => ($f['dias_sin_facturar'] ?? null) !== null)
            ->pluck('dias_sin_facturar')
            ->map(fn ($d) => (int) $d);

        $totales['aging_promedio'] = $aging->isNotEmpty() ? (int) round($aging->avg()) : null;
        $totales['aging_maximo'] = $aging->isNotEmpty() ? (int) $aging->max() : null;
        $totales['precio_pendiente'] = $lineas
            ->filter(fn (array $f) => ! empty($f['fl_precio_pendiente']))
            ->pluck('recepcion_id')
            ->unique()
            ->count();

        return $totales;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineas
     */
    private function advertenciaCotizacion(Collection $lineas): ?string
    {
        $otras = $lineas->filter(function (array $f) {
            $monedaId = (int) ($f['moneda_id'] ?? 1);
            if ($monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
                return false;
            }
            if (! empty($f['cotizacion_exacta'])) {
                return false;
            }

            return (float) ($f['cotizacion_usada'] ?? 0) > 0;
        });

        if ($otras->isEmpty()) {
            return null;
        }

        $fechaMin = $otras->pluck('cotizacion_fecha')->filter()->sort()->first();
        $n = $otras->count();

        return $n.' línea(s) con moneda extranjera usaron cotización vigente de otra fecha'
            .($fechaMin ? ' (más antigua: '.RecepcionProveedorReporteFiltros::formatearFechaPantalla((string) $fechaMin).')' : '')
            .'.';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    private function aplicarPresentacion(Collection $filas, array $filtros): Collection
    {
        $orden = (string) ($filtros['orden'] ?? RecepcionProveedorReporteFiltros::ORDEN_FECHA);
        $conGrupos = in_array($orden, [
            RecepcionProveedorReporteFiltros::ORDEN_ARTICULO,
            RecepcionProveedorReporteFiltros::ORDEN_PROVEEDOR,
            RecepcionProveedorReporteFiltros::ORDEN_CENTROCOSTO,
            RecepcionProveedorReporteFiltros::ORDEN_CUENTA,
        ], true);

        if ($conGrupos) {
            $filas = $this->insertarGrupos($filas, $orden);
        }

        if (empty($filtros['consolidar_empresas']) && count(RecepcionProveedorReporteFiltros::empresaIds($filtros)) > 1) {
            $filas = $this->insertarHeadersEmpresa($filas);
        }

        return $filas->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function insertarGrupos(Collection $filas, string $orden): Collection
    {
        $salida = collect();
        $grupoActual = null;
        $acumCantidad = 0.0;
        $acumTotal = 0.0;
        $acumMn = 0.0;
        $etiquetaActual = '';

        $flush = function () use (&$salida, &$acumCantidad, &$acumTotal, &$acumMn, &$etiquetaActual) {
            if ($etiquetaActual === '') {
                return;
            }
            $salida->push([
                'tipo_fila' => 'subtotal_grupo',
                'etiqueta_grupo' => $etiquetaActual,
                'cantidad' => round($acumCantidad, 4),
                'total' => round($acumTotal, 4),
                'importe_mn' => round($acumMn, 4),
                'nombreempresa' => '',
            ]);
            $acumCantidad = 0.0;
            $acumTotal = 0.0;
            $acumMn = 0.0;
        };

        foreach ($filas as $fila) {
            [$clave, $etiqueta] = $this->claveGrupo($fila, $orden);
            if ($grupoActual !== null && $clave !== $grupoActual) {
                $flush();
            }
            if ($clave !== $grupoActual) {
                $salida->push([
                    'tipo_fila' => 'header_grupo',
                    'etiqueta_grupo' => $etiqueta,
                    'nombreempresa' => (string) ($fila['nombreempresa'] ?? ''),
                ]);
                $grupoActual = $clave;
                $etiquetaActual = $etiqueta;
            }
            $acumCantidad += (float) ($fila['cantidad'] ?? 0);
            $acumTotal += (float) ($fila['total'] ?? 0);
            $acumMn += (float) ($fila['importe_mn'] ?? 0);
            $salida->push($fila);
        }
        $flush();

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{0: string, 1: string}
     */
    private function claveGrupo(array $fila, string $orden): array
    {
        return match ($orden) {
            RecepcionProveedorReporteFiltros::ORDEN_ARTICULO => [
                (string) ($fila['sku'] ?? ''),
                'Artículo: '.trim(($fila['sku'] ?? '').' '.($fila['descripcion_articulo'] ?? '')),
            ],
            RecepcionProveedorReporteFiltros::ORDEN_PROVEEDOR => [
                (string) ($fila['proveedor_id'] ?? ''),
                'Proveedor: '.trim(($fila['codigo_proveedor'] ?? '').' '.($fila['nombreproveedor'] ?? '')),
            ],
            RecepcionProveedorReporteFiltros::ORDEN_CENTROCOSTO => [
                (string) ($fila['codigo_cc'] ?? ''),
                'Centro de costo: '.trim(($fila['codigo_cc'] ?? '').' '.($fila['nombre_cc'] ?? '')),
            ],
            RecepcionProveedorReporteFiltros::ORDEN_CUENTA => [
                (string) ($fila['codigo_cuenta'] ?? ''),
                'Cuenta: '.trim(($fila['codigo_cuenta'] ?? '').' '.($fila['nombre_cuenta'] ?? '')),
            ],
            default => ['', ''],
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return Collection<int, array<string, mixed>>
     */
    private function insertarHeadersEmpresa(Collection $filas): Collection
    {
        $salida = collect();
        $empresaActual = null;

        foreach ($filas as $fila) {
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $nombre = (string) ($fila['nombreempresa'] ?? '');
            if (($fila['tipo_fila'] ?? '') === 'dato' && $empresaId > 0 && $empresaId !== $empresaActual) {
                $salida->push([
                    'tipo_fila' => 'header_empresa',
                    'empresa_id' => $empresaId,
                    'nombreempresa' => $nombre,
                    'etiqueta_grupo' => $nombre,
                ]);
                $empresaActual = $empresaId;
            }
            $salida->push($fila);
        }

        return $salida;
    }

    private function etiquetaEstadoFacturacion(string $tipo, bool $facturado, string $estadoFactura): string
    {
        if ($tipo === Recepcion_Proveedor::TIPO_DEVOLUCION) {
            return 'Devolución';
        }
        if ($facturado) {
            return $estadoFactura !== '' ? 'Facturada ('.$estadoFactura.')' : 'Facturada';
        }

        return 'No facturada';
    }

    private function formatoAnitaCom($letra, $sucursal, $nro): string
    {
        $nro = (int) $nro;
        if ($nro <= 0) {
            return '';
        }

        return sprintf('%s%04d-%08d', trim((string) $letra), (int) $sucursal, $nro);
    }

    private function formatoFacturaErp($letra, $sucursal, $numero): string
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return '';
        }
        $letra = trim((string) $letra);
        $sucursal = (int) $sucursal;

        return trim($letra.' '.sprintf('%04d', $sucursal).'-'.$numero);
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        return substr($fecha, 0, 10);
    }
}
