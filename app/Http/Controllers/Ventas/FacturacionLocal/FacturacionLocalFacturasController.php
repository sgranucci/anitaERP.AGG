<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\FacturacionLocalFacturasExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoOperativoLocal;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalFacturaMedioPagoService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalFacturaMedioPagoUiSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoUiSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FacturacionLocalFacturasController extends Controller
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoService $notaCreditoService,
        private readonly FacturacionLocalFacturaMedioPagoService $facturaMedioPagoService,
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-facturas-facturacion-local');

        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $localId = (int) $request->input('local_id', 0);
        $desde = $this->resolverFecha($request, 'desde', now()->format('Y-m-d'));
        $hasta = $this->resolverFecha($request, 'hasta', $desde);
        if ($hasta < $desde) {
            $hasta = $desde;
        }
        $busqueda = trim((string) $request->get('busqueda', ''));
        $turnoId = (int) $request->input('turno_operativo_local_id', 0);

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(10, min(200, $perPage));

        $registros = $this->registrosQuery($request)
            ->paginate($perPage)
            ->appends($request->except(['page']));

        $ventaIdsPagina = $registros->getCollection()->pluck('venta_id')->filter()->values();
        $notasCreditoPorFactura = [];
        if ($ventaIdsPagina->isNotEmpty()) {
            $notasCreditoPorFactura = FacturacionLocalEmision::query()
                ->whereIn('venta_id', $ventaIdsPagina)
                ->whereNotNull('venta_nc_id')
                ->pluck('venta_nc_id', 'venta_id')
                ->all();
        }

        $totales = $this->calcularTotales($request);
        $turnoAbierto = $localId > 0 ? $this->turnoService->turnoAbierto($localId) : null;
        $turnosSelector = $localId > 0
            ? $this->listarTurnosSelector($localId, $desde, $hasta)
            : [];

        $filtrosQuery = array_filter([
            'local_id' => $localId > 0 ? $localId : null,
            'desde' => $desde,
            'hasta' => $hasta,
            'busqueda' => $busqueda !== '' ? $busqueda : null,
            'turno_operativo_local_id' => $turnoId > 0 ? $turnoId : null,
        ], fn ($v) => $v !== null && $v !== '');

        return view('ventas.facturacion_local.facturas.index', [
            'registros' => $registros,
            'notas_credito_por_factura' => $notasCreditoPorFactura,
            'locales' => $locales,
            'local_id' => $localId,
            'desde' => $desde,
            'hasta' => $hasta,
            'busqueda' => $busqueda,
            'turno_operativo_local_id' => $turnoId > 0 ? $turnoId : null,
            'turnos_selector' => $turnosSelector,
            'turno_abierto' => $turnoAbierto,
            'totales_facturacion' => $totales,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertFerli();
        can('listar-facturas-facturacion-local');

        $registros = $this->registrosQuery($request)
            ->get()
            ->map(function (FacturacionLocalEmision $r) {
                $emp = $r->venta?->puntoventas?->empresas
                    ?? $r->localVenta?->empresa;
                $r->setAttribute('nombreempresa', $emp->nombre ?? '');

                return $r;
            });

        $desde = $this->resolverFecha($request, 'desde', now()->format('Y-m-d'));
        $hasta = $this->resolverFecha($request, 'hasta', $desde);
        $localId = (int) $request->input('local_id', 0);
        $localNombre = $localId > 0
            ? (LocalVenta::query()->whereKey($localId)->value('nombre') ?? '')
            : '';

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.facturacion_local.facturas.listado', compact(
                    'registros',
                    'desde',
                    'hasta',
                    'localNombre',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'listado_facturacion_local_facturas';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new FacturacionLocalFacturasExport($registros, $desde, $hasta, $localNombre))
                    ->download('facturacion_local_facturas.xlsx');

            case 'CSV':
                return (new FacturacionLocalFacturasExport($registros, $desde, $hasta, $localNombre, true))
                    ->download('facturacion_local_facturas.csv', Excel::CSV);
        }

        abort(404);
    }

    public function ver(int $ventaId)
    {
        $this->assertFerli();
        can('ver-factura-facturacion-local');

        $meta = $this->resolverEmisionPorVentaId($ventaId);
        if ($meta === null) {
            abort(404);
        }

        $venta = Venta::query()
            ->with([
                'clientes',
                'venta_emisiones.articulos',
                'venta_impuestos',
                'caja_movimientos.cobranzas',
                'cobranzasDirectas',
                'puntoventas.empresas',
                'monedas',
                'tipotransacciones',
                'asientos.asiento_movimientos.cuentacontables',
            ])
            ->findOrFail($ventaId);

        $cobranzas = FacturacionLocalVentaDetalleSupport::cobranzasDeVenta($venta);
        $itemsFacturados = FacturacionLocalVentaDetalleSupport::itemsFacturadosParaDetalle($venta);
        $cobranzaMedios = FacturacionLocalVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);

        $esComprobanteNc = FacturacionLocalFacturaMedioPagoUiSupport::esComprobanteNotaCredito($meta, $ventaId);
        $ncVentaId = $esComprobanteNc
            ? null
            : FacturacionLocalNotaCreditoService::notaCreditoExistenteParaFactura((int) $meta->venta_id);

        $puedeNc = FacturacionLocalNotaCreditoUiSupport::puedeGenerarNotaCredito($meta, $venta, $ventaId);
        $puedeCambiarMedioPago = FacturacionLocalFacturaMedioPagoUiSupport::puedeCambiarMedioPago(
            $meta,
            $cobranzas->isNotEmpty(),
            $ventaId,
        );
        $evaluacionCambioMedio = FacturacionLocalFacturaMedioPagoUiSupport::evaluarTurnoEmision($meta);
        $motivoNoCambioMedio = (! $puedeCambiarMedioPago
            && can('cambiar-medio-pago-facturacion-local', false)
            && $cobranzas->isNotEmpty()
            && ! $esComprobanteNc)
            ? ($evaluacionCambioMedio['motivo'] ?? null)
            : null;

        $turnoAbierto = $this->turnoService->turnoAbierto((int) $meta->local_venta_id);

        return view('ventas.facturacion_local.facturas.ver', [
            'meta' => $meta,
            'venta' => $venta,
            'cobranzas' => $cobranzas,
            'itemsFacturados' => $itemsFacturados,
            'cobranzaMedios' => $cobranzaMedios,
            'puede_nc' => $puedeNc,
            'nc_venta_id' => $ncVentaId,
            'es_comprobante_nc' => $esComprobanteNc,
            'turno_abierto' => $turnoAbierto,
            'puede_cambiar_medio_pago' => $puedeCambiarMedioPago,
            'motivo_no_cambio_medio' => $motivoNoCambioMedio,
            'url_turnos' => route('facturacion_local_turnos'),
        ]);
    }

    public function generarNotaCredito(Request $request, int $ventaId)
    {
        $this->assertFerli();
        can('generar-nota-credito-facturacion-local');

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
                ->route('facturacion_local_facturas')
                ->with('mensaje', $resultado['mensaje'] ?? 'Nota de crédito generada.');
        }

        return redirect()
            ->back()
            ->with('mensaje-error', $resultado['error'] ?? 'No se pudo generar la nota de crédito.');
    }

    public function reimprimir(int $ventaId)
    {
        $this->assertFerli();
        can('ver-factura-facturacion-local');

        $meta = $this->resolverEmisionPorVentaId($ventaId);
        if ($meta === null) {
            return response()->json([
                'ok' => false,
                'error' => 'La venta no corresponde a una emisión de Facturación Local.',
            ], 404);
        }

        $pdfUrl = url('ventas/listaunafactura/'.$ventaId);

        return response()->json([
            'ok' => true,
            'pdf_url' => $pdfUrl,
            'mensaje' => 'Abriendo PDF del comprobante.',
        ]);
    }

    public function apiMediosPagoCambio(int $ventaId)
    {
        $this->assertFerli();
        can('cambiar-medio-pago-facturacion-local');

        $resultado = $this->facturaMedioPagoService->datosParaCambio($ventaId);

        if (! ($resultado['ok'] ?? false)) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function apiCuentacajaPorCodigo(int $ventaId, string $codigo)
    {
        $this->assertFerli();
        can('cambiar-medio-pago-facturacion-local');

        $resultado = $this->facturaMedioPagoService->cuentaPorCodigo($ventaId, $codigo);
        if ((int) ($resultado['id'] ?? 0) <= 0) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function actualizarMediosPago(Request $request, int $ventaId)
    {
        $this->assertFerli();
        can('cambiar-medio-pago-facturacion-local');

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
            ->route('facturacion_local_facturas_ver', ['ventaId' => $ventaId])
            ->with('mensaje', $resultado['mensaje'] ?? 'Medio de pago actualizado.');
    }

    private function registrosQuery(Request $request): Builder
    {
        $localId = (int) $request->input('local_id', 0);
        $desde = $this->resolverFecha($request, 'desde', now()->format('Y-m-d'));
        $hasta = $this->resolverFecha($request, 'hasta', $desde);
        if ($hasta < $desde) {
            $hasta = $desde;
        }
        $busqueda = trim((string) $request->get('busqueda', ''));
        $turnoId = (int) $request->input('turno_operativo_local_id', 0);

        $q = FacturacionLocalEmision::query()
            ->with([
                'localVenta:id,codigo,nombre,empresa_id',
                'venta.clientes',
                'venta.puntoventas.empresas',
                'venta.tipotransacciones',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'ventaNc:id,codigo,total',
                'turno.turnoLocal',
            ]);

        if ($busqueda !== '' && ctype_digit($busqueda)) {
            $id = (int) $busqueda;
            $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
            $numeroComprobantePadded = str_pad((string) $id, max(1, $digitosComprobante), '0', STR_PAD_LEFT);

            return $q->where(function ($w) use ($id, $busqueda, $numeroComprobantePadded) {
                $w->where('venta_id', $id)
                    ->orWhere('venta_nc_id', $id)
                    ->orWhereHas('venta.cobranzasDirectas', fn ($c) => $c->where('id', $id))
                    ->orWhereHas('venta.caja_movimientos.cobranzas', fn ($c) => $c->where('id', $id))
                    ->orWhereHas('venta', function ($vq) use ($id, $busqueda, $numeroComprobantePadded) {
                        $vq->where('numerocomprobante', $id)
                            ->orWhere('codigo', 'like', '%'.$busqueda.'%')
                            ->orWhere('codigo', 'like', '%-'.$numeroComprobantePadded)
                            ->orWhere('cae', 'like', '%'.$busqueda.'%');
                    })
                    ->orWhereHas('ventaNc', function ($vq) use ($id, $busqueda, $numeroComprobantePadded) {
                        $vq->where('id', $id)
                            ->orWhere('numerocomprobante', $id)
                            ->orWhere('codigo', 'like', '%'.$busqueda.'%')
                            ->orWhere('codigo', 'like', '%-'.$numeroComprobantePadded)
                            ->orWhere('cae', 'like', '%'.$busqueda.'%');
                    });
            })->orderByDesc('id');
        }

        if ($localId > 0) {
            $q->where('local_venta_id', $localId);
        }

        if ($turnoId > 0) {
            $q->where('turno_operativo_local_id', $turnoId);
        }

        $q->whereHas('venta', function ($vq) use ($desde, $hasta) {
            $vq->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta);
        });

        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $q->where(function ($w) use ($like) {
                $w->whereHas('venta', function ($vq) use ($like) {
                    $vq->where('codigo', 'like', $like)
                        ->orWhere('cae', 'like', $like)
                        ->orWhere('nombre', 'like', $like)
                        ->orWhereHas('clientes', fn ($c) => $c->where('nombre', 'like', $like));
                })->orWhereHas('localVenta', function ($lq) use ($like) {
                    $lq->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like);
                });
            });
        }

        return $q->orderByDesc('id');
    }

    /**
     * @return array{
     *   cantidad_comprobantes:int,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   total_facturas:float,
     *   total_notas_credito:float,
     *   total_neto:float
     * }
     */
    private function calcularTotales(Request $request): array
    {
        $vacios = [
            'cantidad_comprobantes' => 0,
            'cantidad_facturas' => 0,
            'cantidad_notas_credito' => 0,
            'total_facturas' => 0.0,
            'total_notas_credito' => 0.0,
            'total_neto' => 0.0,
        ];

        $emisionTable = (new FacturacionLocalEmision)->getTable();

        $row = $this->registrosQuery($request)
            ->reorder()
            ->join('venta', 'venta.id', '=', $emisionTable.'.venta_id')
            ->leftJoin('venta as venta_nc', 'venta_nc.id', '=', $emisionTable.'.venta_nc_id')
            ->selectRaw('COUNT(*) as cantidad_comprobantes')
            ->selectRaw('COUNT(*) as cantidad_facturas')
            ->selectRaw('SUM(CASE WHEN '.$emisionTable.'.venta_nc_id IS NOT NULL THEN 1 ELSE 0 END) as cantidad_notas_credito')
            ->selectRaw('SUM(venta.total) as total_facturas')
            ->selectRaw('SUM(COALESCE(venta_nc.total, 0)) as total_notas_credito')
            ->selectRaw('SUM(venta.total) + SUM(COALESCE(venta_nc.total, 0)) as total_neto')
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

    private function resolverEmisionPorVentaId(int $ventaId): ?FacturacionLocalEmision
    {
        return FacturacionLocalEmision::query()
            ->where(function ($q) use ($ventaId) {
                $q->where('venta_id', $ventaId)
                    ->orWhere('venta_nc_id', $ventaId);
            })
            ->with(['localVenta', 'turno.turnoLocal', 'ventaNc'])
            ->orderByRaw('CASE WHEN venta_id = ? THEN 0 ELSE 1 END', [$ventaId])
            ->first();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function listarTurnosSelector(int $localId, string $desde, string $hasta): array
    {
        return TurnoOperativoLocal::query()
            ->with('turnoLocal')
            ->where('local_venta_id', $localId)
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('apertura_en', [$desde.' 00:00:00', $hasta.' 23:59:59'])
                    ->orWhere(function ($w) use ($desde, $hasta) {
                        $w->whereNotNull('cierre_en')
                            ->whereBetween('cierre_en', [$desde.' 00:00:00', $hasta.' 23:59:59']);
                    })
                    ->orWhere('estado', TurnoOperativoLocal::ESTADO_ABIERTO);
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(function (TurnoOperativoLocal $t) {
                $nombre = $t->turnoLocal?->nombre ?? 'Turno';
                $desde = $t->apertura_en?->format('d/m H:i') ?? '?';
                $hasta = $t->estaAbierto()
                    ? 'activo'
                    : ($t->cierre_en?->format('d/m H:i') ?? 'cerrado');

                return [
                    'id' => (int) $t->id,
                    'label' => '#'.$t->id.' '.$nombre.' — '.$desde.' → '.$hasta,
                ];
            })
            ->values()
            ->all();
    }

    private function resolverFecha(Request $request, string $campo, string $default): string
    {
        if ($request->filled($campo)) {
            try {
                return Carbon::parse((string) $request->input($campo))->format('Y-m-d');
            } catch (\Throwable) {
                return $default;
            }
        }

        // Compat: ?fecha=YYYY-MM-DD
        if ($campo === 'desde' && $request->filled('fecha')) {
            try {
                return Carbon::parse((string) $request->input('fecha'))->format('Y-m-d');
            } catch (\Throwable) {
                return $default;
            }
        }

        return $default;
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
