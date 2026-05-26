<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaFacturasDiaExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaTicketService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaNotaCreditoService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
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
    ) {}

    public function index(Request $request)
    {
        can('listar-facturas-gastronomia-dia');

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $fecha = $this->resolverFechaFiltro($request);
        $jornada = $this->estadoJornadaParaRequest($request);
        $busqueda = trim((string) $request->get('busqueda', ''));

        $todasPc = $request->boolean('todas_pc');
        $articuloSku = trim((string) $request->get('articulo_sku', ''));
        $articuloFiltro = GastronomiaVentaDetalleSupport::resolverArticuloFiltro(
            (int) $request->get('articulo_id', 0),
            $articuloSku,
        );

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

        $requiereTurno = GastronomiaTurnoOperativoService::requiereHabilitacionTurno();
        $turnoHabilitado = ! $requiereTurno;
        $urlHabilitacionTurno = route('gastronomia_habilitacion_turno');
        $cfgPv = $this->cuentaService->resolverConfiguracionPv($request);
        if ($requiereTurno && $cfgPv !== null) {
            $turnoHabilitado = $this->turnoOperativoService->turnoHabilitadoEnPc($pc) !== null;
        }

        return view('ventas.gastronomia.facturas_dia.index', [
            'registros' => $registros,
            'notas_credito_por_factura' => $notasCreditoPorFactura,
            'fecha' => $fecha,
            'busqueda' => $busqueda,
            'identificador_pc' => $pc,
            'todas_pc' => $todasPc,
            'articulo_sku' => $articuloSku,
            'articulo_filtro' => $articuloFiltro,
            'insumos_por_venta' => $insumosPorVenta,
            'jornada' => $jornada,
            'requiere_habilitacion_turno' => $requiereTurno,
            'turno_habilitado' => $turnoHabilitado,
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

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.facturas_dia.listado', compact('registros', 'fecha', 'identificador_pc'))
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

    public function ver(int $ventaId)
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
            ])
            ->findOrFail($ventaId);

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

        return view('ventas.gastronomia.facturas_dia.ver', [
            'meta' => $meta,
            'venta' => $venta,
            'cobranzas' => $cobranzas,
            'movimientosInsumos' => $movimientosInsumos,
            'insumosPorDeposito' => $insumosPorDeposito,
            'depositoVentaConfig' => $depositoVentaConfig,
            'depositoInsumosConfig' => $depositoInsumosConfig,
            'itemsFacturados' => $itemsFacturados,
            'itemsConInsumos' => $itemsConInsumos,
            'cobranzaMedios' => $cobranzaMedios,
            'articulo_filtro_id' => $articuloFiltroId,
        ]);
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
                'cuenta',
            ]);

        // Búsqueda numérica: venta / cuenta / cobranza sin filtrar PC ni fecha.
        if ($busqueda !== '' && ctype_digit($busqueda)) {
            $id = (int) $busqueda;

            return $q->where(function ($w) use ($id) {
                $w->where('venta_id', $id)
                    ->orWhere('cuenta_gastronomia_id', $id)
                    ->orWhereHas('venta.cobranzasDirectas', fn ($c) => $c->where('id', $id))
                    ->orWhereHas('venta.caja_movimientos.cobranzas', fn ($c) => $c->where('id', $id));
            })->orderByDesc('venta_id');
        }

        if (! $todasPc) {
            $q->where('identificador_pc', $pc);
        }

        $q->whereHas('venta', fn ($qq) => $qq->whereDate('fechajornada', $fecha));

        if ($articuloFiltro !== null) {
            $articuloId = (int) $articuloFiltro->id;
            $q->where(function ($w) use ($articuloId) {
                $w->whereHas('venta.venta_emisiones', fn ($e) => $e->where('articulo_id', $articuloId))
                    ->orWhereHas('cuenta.lineas', fn ($l) => $l->where('articulo_id', $articuloId));
            });
        }

        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $q->where(function ($w) use ($like) {
                $w->whereHas('venta', function ($vq) use ($like) {
                    $vq->where('codigo', 'like', $like)
                        ->orWhere('nombre', 'like', $like)
                        ->orWhereHas('clientes', fn ($c) => $c->where('nombre', 'like', $like))
                        ->orWhereHas('puntoventas', fn ($p) => $p->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like));
                });
            });
        }

        return $q->orderByDesc('venta_id');
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
}
