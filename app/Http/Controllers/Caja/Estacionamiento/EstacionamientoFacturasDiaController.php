<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Exports\Caja\Estacionamiento\EstacionamientoFacturasDiaExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Ventas\Venta;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoOperativoService;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class EstacionamientoFacturasDiaController extends Controller
{
    public function __construct(
        private readonly EstacionamientoPvService $pvService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
    ) {}

    public function index(Request $request)
    {
        can('listar-facturas-estacionamiento-dia');

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $fecha = $this->resolverFechaFiltro($request);
        $jornada = $this->estadoJornadaParaRequest($request);
        $fechaCalendario = $jornada['fecha_factura_hoy'] ?? Carbon::today()->format('Y-m-d');
        $busqueda = trim((string) $request->get('busqueda', ''));

        $requiereTurno = EstacionamientoTurnoOperativoService::requiereHabilitacionTurno();
        $turnoActivo = $requiereTurno ? $this->turnoOperativoService->turnoHabilitadoEnPc($pc) : null;
        $cfgPv = $this->pvService->resolverConfiguracionPv($request);
        $empresaId = (int) ($cfgPv?->empresa_id ?? 0);
        $filtroTurno = $this->resolverFiltroTurno($request, $turnoActivo, $pc, $empresaId, $fecha);
        $turnosSelector = $requiereTurno && $empresaId > 0
            ? $this->listarTurnosParaSelector($pc, $empresaId, $fecha)
            : [];

        $todasPc = $request->boolean('todas_pc');
        $articuloSku = trim((string) $request->get('item_nombre', ''));
        $itemFiltro = EstacionamientoVentaDetalleSupport::resolverItemFiltro(
            (int) $request->get('item_id', 0),
            $articuloSku,
        );

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(10, min(200, $perPage));
        $registros = $this->registrosFacturasDiaQuery($request, $itemFiltro)
            ->paginate($perPage)
            ->appends($request->except(['page']));

        $ventaIdsPagina = $registros->getCollection()->pluck('venta_id');


        $notasCreditoPorFactura = [];
        if ($ventaIdsPagina->isNotEmpty()) {
            $notasCreditoPorFactura = VentaEstacionamientoEmision::query()
                ->whereIn('venta_factura_origen_id', $ventaIdsPagina)
                ->pluck('venta_id', 'venta_factura_origen_id')
                ->all();
        }

        $turnoHabilitado = ! $requiereTurno || $turnoActivo !== null;
        $urlHabilitacionTurno = route('estacionamiento_habilitacion_turno');
        $totalesFacturacion = $this->calcularTotalesFacturasDia($request, $itemFiltro);

        return view('caja.estacionamiento.facturas_dia.index', [
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
            'item_nombre' => $articuloSku,
            'item_filtro' => $itemFiltro,
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
        can('listar-facturas-estacionamiento-dia');

        $itemFiltro = EstacionamientoVentaDetalleSupport::resolverItemFiltro(
            (int) $request->get('item_id', 0),
            trim((string) $request->get('item_nombre', '')),
        );

        $registros = $this->registrosFacturasDiaQuery($request, $itemFiltro)
            ->get()
            ->map(function (VentaEstacionamientoEmision $r) {
                $emp = $r->venta?->puntoventas?->empresas;
                $r->setAttribute('nombreempresa', $emp->nombre ?? '');

                return $r;
            });

        $fecha = $this->resolverFechaFiltro($request);
        $identificador_pc = EstacionamientoIdentificadorPc::resolver($request);
        $cfgPv = $this->pvService->resolverConfiguracionPv($request);
        $empresa_nombre = $cfgPv?->empresa?->nombre;

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.estacionamiento.facturas_dia.listado', compact('registros', 'fecha', 'identificador_pc', 'empresa_nombre'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_estacionamiento_facturas_dia';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new EstacionamientoFacturasDiaExport($registros))
                    ->download('estacionamiento_facturas_dia.xlsx');

            case 'CSV':
                return (new EstacionamientoFacturasDiaExport($registros))
                    ->download('estacionamiento_facturas_dia.csv', Excel::CSV);
        }

        abort(404);
    }






    public function ver(int $ventaId, Request $request)
    {
        can('ver-factura-estacionamiento');

        $meta = VentaEstacionamientoEmision::query()
            ->where('venta_id', $ventaId)
            ->with(['configuracionPuntoventa', 'ticket'])
            ->firstOrFail();

        $venta = Venta::query()
            ->with([
                'clientes',
                'venta_emisiones.articulos',
                'venta_impuestos',
                'caja_movimientos.cobranzas',
                'cobranzasDirectas',
                'puntoventas',
                'monedas',
                'tipotransacciones',
            ])
            ->findOrFail($ventaId);

        $venta->setRelation('estacionamientoEmision', $meta);
        $meta->setRelation('venta', $venta);

        $cobranzas = EstacionamientoVentaDetalleSupport::cobranzasDeVenta($venta);
        $itemsFacturados = EstacionamientoVentaDetalleSupport::itemsFacturadosParaDetalle($venta);
        $cobranzaMedios = EstacionamientoVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $itemFiltroId = (int) request()->get('item_id', 0);

        $esComprobanteNc = $meta->venta_factura_origen_id !== null;
        $ncVentaId = null;
        if (! $esComprobanteNc) {
            $ncVentaId = VentaEstacionamientoEmision::query()
                ->where('venta_factura_origen_id', $ventaId)
                ->value('venta_id');
            $ncVentaId = $ncVentaId ? (int) $ncVentaId : null;
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $requiereTurno = EstacionamientoTurnoOperativoService::requiereHabilitacionTurno();
        $turnoHabilitado = ! $requiereTurno;
        $cfgPv = $meta->configuracionPuntoventa;
        if ($requiereTurno && $cfgPv !== null) {
            $turnoHabilitado = $this->turnoOperativoService->turnoHabilitadoEnPc($pc) !== null;
        }

        return view('caja.estacionamiento.facturas_dia.ver', [
            'meta' => $meta,
            'venta' => $venta,
            'cobranzas' => $cobranzas,
            'itemsFacturados' => $itemsFacturados,
            'cobranzaMedios' => $cobranzaMedios,
            'item_filtro_id' => $itemFiltroId,
            'puede_nc' => false,
            'nc_venta_id' => $ncVentaId,
            'es_comprobante_nc' => $esComprobanteNc,
            'requiere_habilitacion_turno' => $requiereTurno,
            'turno_habilitado' => $turnoHabilitado,
            'url_habilitacion_turno' => route('estacionamiento_habilitacion_turno'),
            'identificador_pc' => $pc,
            'puede_cambiar_medio_pago' => false,
        ]);
    }





    /**
     * @param  object{id:int,sku:string,descripcion:string}|null  $itemFiltro
     */
    private function registrosFacturasDiaQuery(Request $request, ?object $itemFiltro = null): Builder
    {
        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $fecha = $this->resolverFechaFiltro($request);
        $busqueda = trim((string) $request->get('busqueda', ''));
        $todasPc = $request->boolean('todas_pc');
        $requiereTurno = EstacionamientoTurnoOperativoService::requiereHabilitacionTurno();
        $turnoActivo = $requiereTurno ? $this->turnoOperativoService->turnoHabilitadoEnPc($pc) : null;
        $cfgPv = $this->pvService->resolverConfiguracionPv($request);
        $empresaId = (int) ($cfgPv?->empresa_id ?? 0);
        $filtroTurno = $this->resolverFiltroTurno($request, $turnoActivo, $pc, $empresaId, $fecha);
        $desdeHabilitacion = $filtroTurno['desde'];
        $hastaTurno = $filtroTurno['hasta'];

        $q = VentaEstacionamientoEmision::query()
            ->with([
                'venta.clientes',
                'venta.puntoventas.empresas',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'configuracionPuntoventa',
                'ticket',
            ]);

        // Búsqueda numérica: id venta/cuenta/cobranza o número de comprobante (sin filtrar PC ni fecha).
        if ($busqueda !== '' && ctype_digit($busqueda)) {
            $id = (int) $busqueda;
            $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
            $numeroComprobantePadded = str_pad((string) $id, max(1, $digitosComprobante), '0', STR_PAD_LEFT);

            return $q->where(function ($w) use ($id, $busqueda, $numeroComprobantePadded) {
                $w->where('venta_id', $id)
                    
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

        if ($itemFiltro !== null) {
            $q->whereHas('venta', function ($vq) use ($itemFiltro) {
                $vq->whereHas('venta_emisiones', function ($e) use ($itemFiltro) {
                    $nombre = (string) ($itemFiltro->nombre ?? '');
                    if ($nombre !== '') {
                        $e->where('detalle', 'like', '%'.addcslashes($nombre, '%_\\').'%');
                    }
                });
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
     * Totales del listado filtrado (todos los registros, no solo la página visible).
     *
     * @param  object{id:int,sku:string,descripcion:string}|null  $itemFiltro
     * @return array{
     *   cantidad_comprobantes:int,
     *   cantidad_facturas:int,
     *   cantidad_notas_credito:int,
     *   total_facturas:float,
     *   total_notas_credito:float,
     *   total_neto:float
     * }
     */
    private function calcularTotalesFacturasDia(Request $request, ?object $itemFiltro = null): array
    {
        $vacios = [
            'cantidad_comprobantes' => 0,
            'cantidad_facturas' => 0,
            'cantidad_notas_credito' => 0,
            'total_facturas' => 0.0,
            'total_notas_credito' => 0.0,
            'total_neto' => 0.0,
        ];

        $emisionTable = (new VentaEstacionamientoEmision)->getTable();

        $row = $this->registrosFacturasDiaQuery($request, $itemFiltro)
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
     *   turno: ?TurnoOperativoEstacionamiento,
     *   desde: ?Carbon,
     *   hasta: ?Carbon,
     *   todo_el_dia: bool
     * }
     */
    private function resolverFiltroTurno(
        Request $request,
        ?TurnoOperativoEstacionamiento $turnoActivo,
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

        if (! EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
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

        $turno = TurnoOperativoEstacionamiento::query()
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
            'hasta' => $turno->estado === TurnoOperativoEstacionamiento::ESTADO_CERRADO
                ? $turno->cierre_en
                : null,
            'todo_el_dia' => false,
        ];
    }

    /**
     * @return array{valor: string, turno: ?TurnoOperativoEstacionamiento, desde: ?Carbon, hasta: ?Carbon, todo_el_dia: bool}
     */
    private function filtroTurnoPorDefecto(?TurnoOperativoEstacionamiento $turnoActivo): array
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

    private function resolverValorTurnoFiltro(Request $request, ?TurnoOperativoEstacionamiento $turnoActivo): string
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

        return TurnoOperativoEstacionamiento::query()
            ->with('turno')
            ->where('identificador_pc', $pc)
            ->where('empresa_id', $empresaId)
            ->whereHas('jornada', fn ($j) => $j->whereDate('fecha_jornada', $fechaJornada))
            ->whereNotNull('habilitacion_en')
            ->orderBy('habilitacion_en')
            ->get()
            ->map(fn (TurnoOperativoEstacionamiento $t) => [
                'id' => (int) $t->id,
                'nombre' => (string) ($t->turno?->nombre ?? 'Turno'),
                'estado' => (string) $t->estado,
                'habilitacion_en' => $t->habilitacion_en?->format('Y-m-d H:i') ?? '',
                'cierre_en' => $t->cierre_en?->format('Y-m-d H:i'),
                'label' => $this->etiquetaTurnoSelector($t),
                'es_activo' => $t->estado === TurnoOperativoEstacionamiento::ESTADO_HABILITADO,
            ])
            ->values()
            ->all();
    }

    private function etiquetaTurnoSelector(TurnoOperativoEstacionamiento $turno): string
    {
        $nombre = $turno->turno?->nombre ?? 'Turno';
        $desde = $turno->habilitacion_en?->format('Y-m-d H:i') ?? '?';

        if ($turno->estado === TurnoOperativoEstacionamiento::ESTADO_CERRADO && $turno->cierre_en !== null) {
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
        $cfg = $this->pvService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return null;
        }

        return $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id);
    }

}
