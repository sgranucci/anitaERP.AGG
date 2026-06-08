<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaFacturasDiaExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\MozoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaTicketService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaNotaCreditoService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaMedioPagoService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\Gastronomia\GastronomiaVentaWaitryComandasSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class GastronomiaFacturasDiaController extends Controller
{
    public function __construct(
        private readonly GastronomiaFacturaTicketService $facturaTicketService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaNotaCreditoService $notaCreditoService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly GastronomiaTicketTarjetaCanjeService $ticketTarjetaCanjeService,
        private readonly GastronomiaTicketCanjePremioService $ticketCanjePremioService,
        private readonly GastronomiaCategoriafidelidadCanjeService $categoriafidelidadCanjeService,
        private readonly GastronomiaFacturaMedioPagoService $facturaMedioPagoService,
        private readonly WaitryOrdenesExternasService $waitryOrdenesExternasService,
    ) {}

    public function index(Request $request)
    {
        can('listar-facturas-gastronomia-dia');

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $fecha = $this->resolverFechaFiltro($request);
        $jornada = $this->estadoJornadaParaRequest($request);
        $fechaCalendario = $jornada['fecha_factura_hoy'] ?? Carbon::today()->format('Y-m-d');
        $busqueda = trim((string) $request->get('busqueda', ''));

        $requiereTurno = GastronomiaTurnoOperativoService::requiereHabilitacionTurno();
        $turnoActivo = $requiereTurno ? $this->turnoOperativoService->turnoHabilitadoEnPc($pc) : null;
        $cfgPv = $this->cuentaService->resolverConfiguracionPv($request);
        $empresaId = (int) ($cfgPv?->empresa_id ?? 0);
        $filtroTurno = $this->resolverFiltroTurno($request, $turnoActivo, $pc, $empresaId, $fecha);
        $turnosSelector = $requiereTurno && $empresaId > 0
            ? $this->listarTurnosParaSelector($pc, $empresaId, $fecha)
            : [];

        $todasPc = $request->boolean('todas_pc');
        $articuloSku = trim((string) $request->get('articulo_sku', ''));
        $articuloFiltro = GastronomiaVentaDetalleSupport::resolverArticuloFiltro(
            (int) $request->get('articulo_id', 0),
            $articuloSku,
        );
        $mozoFiltroId = (int) $request->input('mozo_gastronomia_id', 0);
        $mozosSelector = $empresaId > 0
            ? $this->listarMozosParaSelector($empresaId)
            : [];

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(10, min(200, $perPage));
        $registros = $this->registrosFacturasDiaQuery($request, $articuloFiltro)
            ->paginate($perPage)
            ->appends($request->except(['page']));

        $ventaIdsPagina = $registros->getCollection()->pluck('venta_id');

        $insumosPorVenta = [];
        if ($articuloFiltro !== null && $ventaIdsPagina->isNotEmpty()) {
            $insumosPorVenta = GastronomiaVentaDetalleSupport::mapInsumosPorVentaYArticuloPadre(
                $ventaIdsPagina,
                (int) $articuloFiltro->id,
            );
        }

        $notasCreditoPorFactura = [];
        if ($ventaIdsPagina->isNotEmpty()) {
            $notasCreditoPorFactura = VentaGastronomiaEmision::query()
                ->whereIn('venta_factura_origen_id', $ventaIdsPagina)
                ->pluck('venta_id', 'venta_factura_origen_id')
                ->all();
        }

        $turnoHabilitado = ! $requiereTurno || $turnoActivo !== null;
        $urlHabilitacionTurno = route('gastronomia_habilitacion_turno');
        $totalesFacturacion = $this->calcularTotalesFacturasDia($request, $articuloFiltro);

        return view('ventas.gastronomia.facturas_dia.index', [
            'registros' => $registros,
            'notas_credito_por_factura' => $notasCreditoPorFactura,
            'fecha' => $fecha,
            'fecha_calendario' => $fechaCalendario,
            'busqueda' => $busqueda,
            'identificador_pc' => $pc,
            'tiene_cfg_pv' => $cfgPv !== null,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'empresa_nombre' => $cfgPv?->empresa?->nombre,
            'todas_pc' => $todasPc,
            'articulo_sku' => $articuloSku,
            'articulo_filtro' => $articuloFiltro,
            'mozo_gastronomia_id' => $mozoFiltroId > 0 ? $mozoFiltroId : null,
            'mozos_selector' => $mozosSelector,
            'insumos_por_venta' => $insumosPorVenta,
            'jornada' => $jornada,
            'requiere_habilitacion_turno' => $requiereTurno,
            'turno_habilitado' => $turnoHabilitado,
            'turno_activo' => $turnoActivo,
            'turno_filtro_val' => $filtroTurno['valor'],
            'turno_filtro' => $filtroTurno['turno'],
            'turnos_selector' => $turnosSelector,
            'totales_facturacion' => $totalesFacturacion,
            'url_habilitacion_turno' => $urlHabilitacionTurno,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-facturas-gastronomia-dia');

        $articuloFiltro = GastronomiaVentaDetalleSupport::resolverArticuloFiltro(
            (int) $request->get('articulo_id', 0),
            trim((string) $request->get('articulo_sku', '')),
        );

        $registros = $this->registrosFacturasDiaQuery($request, $articuloFiltro)
            ->get()
            ->map(function (VentaGastronomiaEmision $r) {
                $emp = $r->venta?->puntoventas?->empresas;
                $r->setAttribute('nombreempresa', $emp->nombre ?? '');

                return $r;
            });

        $fecha = $this->resolverFechaFiltro($request);
        $identificador_pc = GastronomiaIdentificadorPc::resolver($request);
        $cfgPv = $this->cuentaService->resolverConfiguracionPv($request);
        $empresa_nombre = $cfgPv?->empresa?->nombre;

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.facturas_dia.listado', compact('registros', 'fecha', 'identificador_pc', 'empresa_nombre'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_gastronomia_facturas_dia';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new GastronomiaFacturasDiaExport($registros))
                    ->download('gastronomia_facturas_dia.xlsx');

            case 'CSV':
                return (new GastronomiaFacturasDiaExport($registros))
                    ->download('gastronomia_facturas_dia.csv', Excel::CSV);
        }

        abort(404);
    }

    public function generarNotaCredito(Request $request, int $ventaId)
    {
        can('generar-nota-credito-gastronomia-facturas-dia');

        $leyenda = trim((string) $request->input('leyenda', ''));
        if (mb_strlen($leyenda) > 255) {
            $leyenda = mb_substr($leyenda, 0, 255);
        }

        $resultado = $this->notaCreditoService->generarDesdeFactura($ventaId, $request, $leyenda);

        if ($request->expectsJson() || $request->ajax()) {
            if (! empty($resultado['ok'])) {
                return response()->json($resultado);
            }

            return response()->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No se pudo generar la nota de crédito.',
            ], 422);
        }

        if (! empty($resultado['ok'])) {
            return redirect()
                ->route('gastronomia_facturas_dia')
                ->with('mensaje', $resultado['mensaje'] ?? 'Nota de crédito generada.');
        }

        return redirect()
            ->back()
            ->with('mensaje-error', $resultado['error'] ?? 'No se pudo generar la nota de crédito.');
    }

    public function reimprimirTicket(int $ventaId)
    {
        can('ver-factura-gastronomia');

        if (! VentaGastronomiaEmision::query()->where('venta_id', $ventaId)->exists()) {
            return response()->json(['ok' => false, 'error' => 'La venta no corresponde a una emisión gastronomía.'], 404);
        }

        $resultado = $this->facturaTicketService->reimprimirTicketVenta($ventaId);

        if (! empty($resultado['ok'])) {
            return response()->json(['ok' => true, 'mensaje' => 'Ticket enviado a la impresora.']);
        }

        return response()->json([
            'ok' => false,
            'error' => $resultado['mensaje'] ?? 'No se pudo reimprimir el ticket.',
        ], 422);
    }

    public function apiTicketsTarjeta(int $ventaId)
    {
        can('ver-factura-gastronomia');

        if (! VentaGastronomiaEmision::query()->where('venta_id', $ventaId)->exists()) {
            return response()->json(['ok' => false, 'error' => 'La venta no corresponde a una emisión gastronomía.'], 404);
        }

        $tickets = $this->ticketTarjetaCanjeService->listarPorVenta($ventaId);

        return response()->json([
            'ok' => true,
            'venta_id' => $ventaId,
            'tickets' => collect($tickets)->map(fn ($t) => [
                'id' => $t->id,
                'ticket_id' => $t->ticket_id,
                'numeroticket' => $t->numeroticket,
                'numerodocumento' => $t->numerodocumento,
                'fecha_emision' => $t->fecha?->format('d/m/Y'),
                'monto' => round((float) $t->monto, 2),
                'montoticket' => round((float) $t->montoticket, 2),
                'numerocupon' => $t->numerocupon,
                'estado' => $t->estado,
                'created_at' => $t->created_at?->format('d/m/Y H:i:s'),
            ])->values(),
        ]);
    }

    public function apiTicketsCanjePremio(int $ventaId)
    {
        can('ver-factura-gastronomia');

        if (! VentaGastronomiaEmision::query()->where('venta_id', $ventaId)->exists()) {
            return response()->json(['ok' => false, 'error' => 'La venta no corresponde a una emisión gastronomía.'], 404);
        }

        $tickets = $this->ticketCanjePremioService->listarPorVenta($ventaId);

        return response()->json([
            'ok' => true,
            'venta_id' => $ventaId,
            'canjes' => collect($tickets)->map(fn ($t) => [
                'id' => $t->id,
                'numerocupon' => $t->numerocupon,
                'ticket_id' => $t->ticket_id,
                'renglon' => $t->renglon,
                'sku' => $t->articulo->sku ?? '',
                'articulo' => $t->articulo->descripcion ?? '',
                'cantidad' => round((float) $t->cantidad, 4),
                'puntos' => (int) $t->puntos,
                'apellido' => $t->apellido,
                'nombre' => $t->nombre,
                'numerodocumento' => $t->numerodocumento,
                'mozo' => $t->mozo->nombre ?? '',
                'fechacanje' => $t->fechacanje?->format('d/m/Y H:i:s'),
            ])->values(),
        ]);
    }

    public function apiCanjesFidelidad(int $ventaId)
    {
        can('ver-factura-gastronomia');

        if (! VentaGastronomiaEmision::query()->where('venta_id', $ventaId)->exists()) {
            return response()->json(['ok' => false, 'error' => 'La venta no corresponde a una emisión gastronomía.'], 404);
        }

        $entregas = $this->categoriafidelidadCanjeService->listarPorVenta($ventaId);

        return response()->json([
            'ok' => true,
            'venta_id' => $ventaId,
            'canjes' => collect($entregas)->map(fn ($e) => [
                'id' => $e->id,
                'documento' => $e->documento,
                'tarjeta' => $e->tarjeta,
                'trackdata' => $e->trackdata,
                'apellido' => $e->apellido,
                'nombre' => $e->nombre,
                'titular' => trim($e->apellido.' '.$e->nombre),
                'categoria_codigo' => $e->categoriafidelidad->codigo ?? '',
                'categoria_nombre' => $e->categoriafidelidad->nombre ?? '',
                'sku' => $e->articulo->sku ?? '',
                'articulo' => $e->articulo->descripcion ?? '',
                'fechacanje' => $e->fechacanje?->format('d/m/Y H:i:s'),
            ])->values(),
        ]);
    }

    public function ver(int $ventaId, Request $request)
    {
        can('ver-factura-gastronomia');

        $meta = VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaId)
            ->with(['cuenta.lineas.articulo', 'configuracionPuntoventa'])
            ->firstOrFail();

        $venta = Venta::query()
            ->with([
                'clientes',
                'venta_emisiones.articulos',
                'venta_impuestos',
                'asientos.asiento_movimientos.cuentacontables',
                'caja_movimientos.cobranzas',
                'cobranzasDirectas',
                'puntoventas',
                'monedas',
                'tipotransacciones',
            ])
            ->findOrFail($ventaId);

        if ($meta->cuenta !== null && (int) ($meta->cuenta->waitry_order_id ?? 0) > 0) {
            $this->waitryOrdenesExternasService->completarDisplayIdEnCuenta($meta->cuenta);
            $meta->load('cuenta');
        }
        $venta->setRelation('gastronomiaEmision', $meta);
        $meta->setRelation('venta', $venta);

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $movimientosInsumos = GastronomiaVentaDetalleSupport::movimientosInsumos($ventaId);
        $insumosPorDeposito = GastronomiaVentaDetalleSupport::agruparPorDeposito($movimientosInsumos);
        $cfgPv = $meta->configuracionPuntoventa;
        $depositoVentaConfig = GastronomiaVentaDetalleSupport::depositoVentaGastronomia($cfgPv);
        $depositoInsumosConfig = GastronomiaDepositoConfigSupport::depositoInsumosDto($cfgPv);
        $itemsFacturados = GastronomiaVentaDetalleSupport::itemsFacturadosParaDetalle($venta, $meta->cuenta);
        $itemsConInsumos = GastronomiaVentaDetalleSupport::itemsFacturadosConInsumos($venta, $meta->cuenta);
        $cobranzaMedios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $articuloFiltroId = (int) request()->get('articulo_id', 0);

        $esComprobanteNc = $meta->venta_factura_origen_id !== null;
        $ncVentaId = $esComprobanteNc
            ? null
            : GastronomiaNotaCreditoService::notaCreditoExistenteParaFactura($ventaId);

        $tipoFactura = $venta->tipotransacciones;
        $esFacturaVenta = ! $esComprobanteNc
            && (! $tipoFactura || $tipoFactura->signo === 'S');

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $requiereTurno = GastronomiaTurnoOperativoService::requiereHabilitacionTurno();
        $turnoHabilitado = ! $requiereTurno;
        if ($requiereTurno && $cfgPv !== null) {
            $turnoHabilitado = $this->turnoOperativoService->turnoHabilitadoEnPc($pc) !== null;
        }

        $puedeNc = can('generar-nota-credito-gastronomia-facturas-dia', false)
            && $esFacturaVenta
            && (float) ($venta->total ?? 0) >= 0.01
            && $ncVentaId === null
            && (! $requiereTurno || $turnoHabilitado);

        $puedeCambiarMedioPago = can('cambiar-medio-pago-gastronomia-facturas-dia', false)
            && $cobranzas->isNotEmpty()
            && ! ($esComprobanteNc ?? false);

        $waitryComandas = GastronomiaVentaWaitryComandasSupport::comandasDesdeEmision($meta);

        return view('ventas.gastronomia.facturas_dia.ver', [
            'meta' => $meta,
            'venta' => $venta,
            'waitry_comandas' => $waitryComandas,
            'waitry_comandas_total' => GastronomiaVentaWaitryComandasSupport::totalComandas($meta),
            'es_factura_cierre_jornada' => GastronomiaVentaWaitryComandasSupport::esFacturaCierreJornadaProceso($meta),
            'cierre_jornada_proceso_lote' => $meta->cierre_jornada_proceso_lote,
            'cobranzas' => $cobranzas,
            'movimientosInsumos' => $movimientosInsumos,
            'insumosPorDeposito' => $insumosPorDeposito,
            'depositoVentaConfig' => $depositoVentaConfig,
            'depositoInsumosConfig' => $depositoInsumosConfig,
            'itemsFacturados' => $itemsFacturados,
            'itemsConInsumos' => $itemsConInsumos,
            'cobranzaMedios' => $cobranzaMedios,
            'articulo_filtro_id' => $articuloFiltroId,
            'puede_nc' => $puedeNc,
            'nc_venta_id' => $ncVentaId,
            'es_comprobante_nc' => $esComprobanteNc,
            'requiere_habilitacion_turno' => $requiereTurno,
            'turno_habilitado' => $turnoHabilitado,
            'url_habilitacion_turno' => route('gastronomia_habilitacion_turno'),
            'identificador_pc' => $pc,
            'puede_cambiar_medio_pago' => $puedeCambiarMedioPago,
            'puede_ver_formula' => can('listar-formula-articulo', false) || can('listar-articulos', false),
        ]);
    }

    public function apiMediosPagoCambio(int $ventaId)
    {
        can('cambiar-medio-pago-gastronomia-facturas-dia');

        $resultado = $this->facturaMedioPagoService->datosParaCambio($ventaId);

        if (! ($resultado['ok'] ?? false)) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function apiCuentacajaPorCodigo(int $ventaId, string $codigo)
    {
        can('cambiar-medio-pago-gastronomia-facturas-dia');

        $resultado = $this->facturaMedioPagoService->cuentaPorCodigo($ventaId, $codigo);
        if ((int) ($resultado['id'] ?? 0) <= 0) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function actualizarMediosPago(Request $request, int $ventaId)
    {
        can('cambiar-medio-pago-gastronomia-facturas-dia');

        $request->validate([
            'cambios' => 'required|array|min:1',
            'cambios.*.caja_movimiento_cuentacaja_id' => 'required|integer|min:1',
            'cambios.*.cuentacaja_id' => 'required|integer|min:1',
            'cambios.*.monto' => 'nullable|numeric',
        ]);

        $resultado = $this->facturaMedioPagoService->aplicarCambio(
            $ventaId,
            array_values($request->input('cambios', [])),
        );

        if (! ($resultado['ok'] ?? false)) {
            return response()->json($resultado, 422);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($resultado);
        }

        return redirect()
            ->route('gastronomia_facturas_dia_ver', ['ventaId' => $ventaId])
            ->with('mensaje', $resultado['mensaje'] ?? 'Medio de pago actualizado.');
    }

    /**
     * @param  object{id:int,sku:string,descripcion:string}|null  $articuloFiltro
     */
    private function registrosFacturasDiaQuery(Request $request, ?object $articuloFiltro = null): Builder
    {
        $pc = GastronomiaIdentificadorPc::resolver($request);
        $fecha = $this->resolverFechaFiltro($request);
        $busqueda = trim((string) $request->get('busqueda', ''));
        $todasPc = $request->boolean('todas_pc');
        $requiereTurno = GastronomiaTurnoOperativoService::requiereHabilitacionTurno();
        $turnoActivo = $requiereTurno ? $this->turnoOperativoService->turnoHabilitadoEnPc($pc) : null;
        $cfgPv = $this->cuentaService->resolverConfiguracionPv($request);
        $empresaId = (int) ($cfgPv?->empresa_id ?? 0);
        $filtroTurno = $this->resolverFiltroTurno($request, $turnoActivo, $pc, $empresaId, $fecha);
        $desdeHabilitacion = $filtroTurno['desde'];
        $hastaTurno = $filtroTurno['hasta'];
        $mozoFiltroId = (int) $request->input('mozo_gastronomia_id', 0);

        $q = VentaGastronomiaEmision::query()
            ->with([
                'venta' => fn ($qq) => $qq->withExists([
                    'ticketcanjesGastronomia as tiene_canje_premio',
                    'categoriafidelidadEntregasGastronomia as tiene_canje_fidelidad',
                    'tickettarjetasGastronomia as tiene_ticket_tarjeta',
                ]),
                'venta.clientes',
                'venta.puntoventas.empresas',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta.mozo',
            ]);

        // Búsqueda numérica: id venta/cuenta/cobranza o número de comprobante (sin filtrar PC ni fecha).
        if ($busqueda !== '' && ctype_digit($busqueda)) {
            $id = (int) $busqueda;
            $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
            $numeroComprobantePadded = str_pad((string) $id, max(1, $digitosComprobante), '0', STR_PAD_LEFT);

            return $q->where(function ($w) use ($id, $busqueda, $numeroComprobantePadded) {
                $w->where('venta_id', $id)
                    ->orWhere('cuenta_gastronomia_id', $id)
                    ->orWhereHas('venta.cobranzasDirectas', fn ($c) => $c->where('id', $id))
                    ->orWhereHas('venta.caja_movimientos.cobranzas', fn ($c) => $c->where('id', $id))
                    ->orWhereHas('venta', function ($vq) use ($id, $busqueda, $numeroComprobantePadded) {
                        $vq->where('numerocomprobante', $id)
                            ->orWhere('codigo', 'like', '%'.$busqueda.'%')
                            ->orWhere('codigo', 'like', '%-'.$numeroComprobantePadded)
                            ->orWhere('codigo', 'like', '%'.$numeroComprobantePadded);
                    });
            })->orderByDesc('venta_id');
        }

        if (! $todasPc) {
            $q->where('identificador_pc', $pc);
        }

        if ($empresaId > 0) {
            $q->where(function ($w) use ($empresaId) {
                $w->whereHas('configuracionPuntoventa', fn ($c) => $c->where('empresa_id', $empresaId))
                    ->orWhereHas('venta.puntoventas', fn ($p) => $p->where('empresa_id', $empresaId));
            });
        }

        $q->whereHas('venta', function ($vq) use ($fecha, $desdeHabilitacion, $hastaTurno) {
            $vq->whereDate('fechajornada', $fecha);
            if ($desdeHabilitacion !== null) {
                $vq->where('created_at', '>=', $desdeHabilitacion);
            }
            if ($hastaTurno !== null) {
                $vq->where('created_at', '<=', $hastaTurno);
            }
        });

        if ($articuloFiltro !== null) {
            $articuloId = (int) $articuloFiltro->id;
            $q->where(function ($w) use ($articuloId) {
                $w->whereHas('venta.venta_emisiones', fn ($e) => $e->where('articulo_id', $articuloId))
                    ->orWhereHas('cuenta.lineas', fn ($l) => $l->where('articulo_id', $articuloId));
            });
        }

        if ($mozoFiltroId > 0) {
            $q->whereHas('cuenta', fn ($c) => $c->where('mozo_gastronomia_id', $mozoFiltroId));
        }

        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $q->where(function ($w) use ($like) {
                $w->whereHas('venta', function ($vq) use ($like) {
                    $vq->where('codigo', 'like', $like)
                        ->orWhere('nombre', 'like', $like)
                        ->orWhereHas('clientes', fn ($c) => $c->where('nombre', 'like', $like))
                        ->orWhereHas('puntoventas', fn ($p) => $p->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like));
                })->orWhereHas('cuenta.mozo', function ($mq) use ($like) {
                    $mq->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like);
                });
            });
        }

        return $q->orderByDesc('venta_id');
    }

    /**
     * Totales del listado filtrado (todos los registros, no solo la página visible).
     *
     * @param  object{id:int,sku:string,descripcion:string}|null  $articuloFiltro
     * @return array{
     *   cantidad_comprobantes:int,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   total_facturas:float,
     *   total_notas_credito:float,
     *   total_neto:float
     * }
     */
    private function calcularTotalesFacturasDia(Request $request, ?object $articuloFiltro = null): array
    {
        $vacios = [
            'cantidad_comprobantes' => 0,
            'cantidad_facturas' => 0,
            'cantidad_notas_credito' => 0,
            'total_facturas' => 0.0,
            'total_notas_credito' => 0.0,
            'total_neto' => 0.0,
        ];

        $emisionTable = (new VentaGastronomiaEmision)->getTable();

        $row = $this->registrosFacturasDiaQuery($request, $articuloFiltro)
            ->reorder()
            ->join('venta', 'venta.id', '=', $emisionTable.'.venta_id')
            ->selectRaw('COUNT(*) as cantidad_comprobantes')
            ->selectRaw('SUM(CASE WHEN '.$emisionTable.'.venta_factura_origen_id IS NULL THEN 1 ELSE 0 END) as cantidad_facturas')
            ->selectRaw('SUM(CASE WHEN '.$emisionTable.'.venta_factura_origen_id IS NOT NULL THEN 1 ELSE 0 END) as cantidad_notas_credito')
            ->selectRaw('SUM(CASE WHEN '.$emisionTable.'.venta_factura_origen_id IS NULL THEN venta.total ELSE 0 END) as total_facturas')
            ->selectRaw('SUM(CASE WHEN '.$emisionTable.'.venta_factura_origen_id IS NOT NULL THEN venta.total ELSE 0 END) as total_notas_credito')
            ->selectRaw('SUM(venta.total) as total_neto')
            ->first();

        if ($row === null) {
            return $vacios;
        }

        return [
            'cantidad_comprobantes' => (int) ($row->cantidad_comprobantes ?? 0),
            'cantidad_facturas' => (int) ($row->cantidad_facturas ?? 0),
            'cantidad_notas_credito' => (int) ($row->cantidad_notas_credito ?? 0),
            'total_facturas' => round((float) ($row->total_facturas ?? 0), 2),
            'total_notas_credito' => round((float) ($row->total_notas_credito ?? 0), 2),
            'total_neto' => round((float) ($row->total_neto ?? 0), 2),
        ];
    }

    /**
     * @return array{
     *   valor: string,
     *   turno: ?TurnoOperativoGastronomia,
     *   desde: ?Carbon,
     *   hasta: ?Carbon,
     *   todo_el_dia: bool
     * }
     */
    private function resolverFiltroTurno(
        Request $request,
        ?TurnoOperativoGastronomia $turnoActivo,
        string $pc,
        int $empresaId,
        string $fechaJornada,
    ): array {
        $todoElDia = [
            'valor' => '0',
            'turno' => null,
            'desde' => null,
            'hasta' => null,
            'todo_el_dia' => true,
        ];

        if (! GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            return $todoElDia;
        }

        $valor = $this->resolverValorTurnoFiltro($request, $turnoActivo);

        if ($valor === '0' || $valor === '') {
            return $todoElDia;
        }

        if ($valor === 'activo') {
            if ($turnoActivo === null || $turnoActivo->habilitacion_en === null) {
                return $todoElDia;
            }

            return [
                'valor' => 'activo',
                'turno' => $turnoActivo,
                'desde' => $turnoActivo->habilitacion_en,
                'hasta' => null,
                'todo_el_dia' => false,
            ];
        }

        $turnoId = (int) $valor;
        if ($turnoId <= 0 || $empresaId <= 0 || $pc === '') {
            return $this->filtroTurnoPorDefecto($turnoActivo);
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with('turno')
            ->whereKey($turnoId)
            ->where('identificador_pc', $pc)
            ->where('empresa_id', $empresaId)
            ->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada))
            ->first();

        if ($turno === null || $turno->habilitacion_en === null) {
            return $this->filtroTurnoPorDefecto($turnoActivo);
        }

        return [
            'valor' => (string) $turnoId,
            'turno' => $turno,
            'desde' => $turno->habilitacion_en,
            'hasta' => $turno->estado === TurnoOperativoGastronomia::ESTADO_CERRADO
                ? $turno->cierre_en
                : null,
            'todo_el_dia' => false,
        ];
    }

    /**
     * @return array{valor: string, turno: ?TurnoOperativoGastronomia, desde: ?Carbon, hasta: ?Carbon, todo_el_dia: bool}
     */
    private function filtroTurnoPorDefecto(?TurnoOperativoGastronomia $turnoActivo): array
    {
        if ($turnoActivo !== null && $turnoActivo->habilitacion_en !== null) {
            return [
                'valor' => 'activo',
                'turno' => $turnoActivo,
                'desde' => $turnoActivo->habilitacion_en,
                'hasta' => null,
                'todo_el_dia' => false,
            ];
        }

        return [
            'valor' => '0',
            'turno' => null,
            'desde' => null,
            'hasta' => null,
            'todo_el_dia' => true,
        ];
    }

    private function resolverValorTurnoFiltro(Request $request, ?TurnoOperativoGastronomia $turnoActivo): string
    {
        if ($request->has('turno_filtro')) {
            return trim((string) $request->input('turno_filtro', '0'));
        }

        if ($request->has('solo_turno_activo')) {
            return $request->boolean('solo_turno_activo') ? 'activo' : '0';
        }

        return $turnoActivo !== null ? 'activo' : '0';
    }

    /**
     * @return list<array{
     *   id:int,
     *   nombre:string,
     *   estado:string,
     *   habilitacion_en:string,
     *   cierre_en:?string,
     *   label:string,
     *   es_activo:bool
     * }>
     */
    private function listarTurnosParaSelector(string $pc, int $empresaId, string $fechaJornada): array
    {
        if ($pc === '' || $empresaId <= 0) {
            return [];
        }

        return TurnoOperativoGastronomia::query()
            ->with('turno')
            ->where('identificador_pc', $pc)
            ->where('empresa_id', $empresaId)
            ->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada))
            ->whereNotNull('habilitacion_en')
            ->orderBy('habilitacion_en')
            ->get()
            ->map(fn (TurnoOperativoGastronomia $t) => [
                'id' => (int) $t->id,
                'nombre' => (string) ($t->turno?->nombre ?? 'Turno'),
                'estado' => (string) $t->estado,
                'habilitacion_en' => $t->habilitacion_en?->format('Y-m-d H:i') ?? '',
                'cierre_en' => $t->cierre_en?->format('Y-m-d H:i'),
                'label' => $this->etiquetaTurnoSelector($t),
                'es_activo' => $t->estado === TurnoOperativoGastronomia::ESTADO_HABILITADO,
            ])
            ->values()
            ->all();
    }

    private function etiquetaTurnoSelector(TurnoOperativoGastronomia $turno): string
    {
        $nombre = $turno->turno?->nombre ?? 'Turno';
        $desde = $turno->habilitacion_en?->format('Y-m-d H:i') ?? '?';

        if ($turno->estado === TurnoOperativoGastronomia::ESTADO_CERRADO && $turno->cierre_en !== null) {
            return $nombre.' — '.$desde.' → '.$turno->cierre_en->format('Y-m-d H:i');
        }

        return $nombre.' — '.$desde.' → activo';
    }

    /**
     * Sin ?fecha= en la URL: fecha de jornada abierta (empresa del PV de esta terminal); si no hay, hoy.
     */
    private function resolverFechaFiltro(Request $request): string
    {
        if ($request->filled('fecha')) {
            return (string) $request->input('fecha');
        }

        $jornada = $this->estadoJornadaParaRequest($request);
        if (! empty($jornada['jornada_abierta']) && ! empty($jornada['fecha_jornada'])) {
            return (string) $jornada['fecha_jornada'];
        }

        return Carbon::today()->format('Y-m-d');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function estadoJornadaParaRequest(Request $request): ?array
    {
        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return null;
        }

        return $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id);
    }

    /**
     * @return list<array{id:int, nombre:string, codigo:string}>
     */
    private function listarMozosParaSelector(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        return MozoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo'])
            ->map(fn (MozoGastronomia $m) => [
                'id' => (int) $m->id,
                'nombre' => (string) $m->nombre,
                'codigo' => (string) ($m->codigo ?? ''),
            ])
            ->values()
            ->all();
    }
}
