<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaCierresTurnoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\ConfiguracionPuntoventaGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use Throwable;

class CierreTurnoGastronomiaController extends Controller
{
    public function __construct(
        private GastronomiaCierreTurnoReporteSupport $reporteSupport,
        private EmpresaRepositoryInterface $empresaRepository,
        private GastronomiaCuentaService $cuentaService,
        private GastronomiaJornadaService $jornadaService,
        private GastronomiaTurnoOperativoService $turnoOperativoService,
        private GastronomiaTicketCanjePremioService $ticketCanjePremioService,
        private GastronomiaTicketTarjetaCanjeService $ticketTarjetaCanjeService,
        private GastronomiaCategoriafidelidadCanjeService $categoriafidelidadCanjeService,
        private ConfiguracionPuntoventaGastronomiaRepositoryInterface $configuracionPuntoventaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-cierres-turno-gastronomia');

        $identificadorPc = GastronomiaIdentificadorPc::resolver($request);
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $pcQuery = $this->configuracionPuntoventaRepository->all()
            ->unique(fn ($cfg) => (string) $cfg->identificador_pc)
            ->values();

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        $turnoOperativo = null;
        if ($cfg !== null && GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            $turnoOperativo = $this->turnoOperativoService->estadoParaTerminal($cfg, $identificadorPc);
        }

        $fechaDesdeDefault = Carbon::today()->subDays(7)->format('Y-m-d');
        $fechaHastaDefault = Carbon::today()->format('Y-m-d');
        if ($turnoOperativo !== null
            && ! empty($turnoOperativo['turno_habilitado'])
            && ! empty($turnoOperativo['habilitacion_en'])) {
            $fechaDesdeTurno = Carbon::parse((string) $turnoOperativo['habilitacion_en'])->format('Y-m-d');
            if ($fechaDesdeTurno < $fechaDesdeDefault) {
                $fechaDesdeDefault = $fechaDesdeTurno;
            }
        }

        $filtros = [
            'empresa_id' => $empresaId,
            'identificador_pc' => $request->input('identificador_pc', $identificadorPc),
            'fecha_desde' => $request->input('fecha_desde', $fechaDesdeDefault),
            'fecha_hasta' => $request->input('fecha_hasta', $fechaHastaDefault),
            'tipo' => $request->input('tipo', ''),
        ];

        $request->merge($filtros);
        $filas = $this->reporteSupport->listadoDesdeRequest($request);

        $empresaIdJornada = $empresaId > 0
            ? $empresaId
            : (int) ($cfg?->empresa_id ?? 0);
        $jornada = $empresaIdJornada > 0
            ? $this->jornadaService->estadoParaEmpresa($empresaIdJornada)
            : null;

        return view('ventas.gastronomia.cierres_turno.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'empresa_query' => $empresaQuery,
            'pc_query' => $pcQuery,
            'identificador_pc_default' => $identificadorPc,
            'turno_operativo' => $turnoOperativo,
            'jornada' => $jornada,
            'empresa_id_jornada' => $empresaIdJornada,
            'requiere_habilitacion_turno' => GastronomiaTurnoOperativoService::requiereHabilitacionTurno(),
            'puede_ver_comprobante' => can('ver-comprobante-cierre-turno-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
        ]);
    }

    public function apiComprobantes(Request $request)
    {
        can('listar-cierres-turno-gastronomia');

        $tipo = trim((string) $request->input('tipo', ''));
        $id = (int) $request->input('id', 0);
        if ($id <= 0 || ! in_array($tipo, ['parcial', 'cierre'], true)) {
            return response()->json(['ok' => false, 'error' => 'Registro de cierre inválido.'], 422);
        }

        try {
            $alcance = $this->reporteSupport->alcanceComprobantesRegistro($tipo, $id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $this->assertAccesoEmpresa((int) $alcance['empresa_id']);

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', GastronomiaTurnoOperativoTotalesSupport::CONCILIACION_FILAS_POR_PAGINA);
        $soloDiferencias = $request->boolean('solo_diferencias');

        try {
            $grilla = GastronomiaTurnoOperativoTotalesSupport::grillaConciliacionRespuesta(
                $alcance['identificador_pc'],
                (int) $alcance['empresa_id'],
                $alcance['fecha_jornada'],
                $alcance['desde'],
                max(1, $page),
                $perPage,
                $soloDiferencias,
                $alcance['hasta'],
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Error al listar comprobantes: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'alcance' => [
                'titulo' => $alcance['titulo'],
                'subtitulo' => $alcance['subtitulo'],
            ],
            'grilla' => $grilla,
            'url_factura_ver_base' => can('ver-factura-gastronomia', false)
                ? url('ventas/gastronomia/facturas-dia')
                : null,
        ]);
    }

    public function apiCanjesPremio(Request $request)
    {
        can('listar-cierres-turno-gastronomia');

        try {
            $alcance = $this->resolverAlcanceCierre($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $canjes = $this->ticketCanjePremioService->listarPorAlcanceTurno(
            (int) $alcance['empresa_id'],
            $alcance['fecha_jornada'],
            $alcance['identificador_pc'],
            $alcance['desde'],
            $alcance['hasta'],
        );

        $pagina = $this->slicePaginado($this->mapCanjesPremioJson($canjes), $request);

        return response()->json([
            'ok' => true,
            'alcance' => [
                'titulo' => 'Canjes de premios — '.$alcance['titulo'],
                'subtitulo' => $alcance['subtitulo'],
            ],
            'canjes' => $pagina['items'],
            'paginacion' => $pagina['paginacion'],
        ]);
    }

    public function apiTicketsTarjeta(Request $request)
    {
        can('listar-cierres-turno-gastronomia');

        try {
            $alcance = $this->resolverAlcanceCierre($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $tickets = $this->ticketTarjetaCanjeService->listarPorAlcanceTurno(
            (int) $alcance['empresa_id'],
            $alcance['fecha_jornada'],
            $alcance['identificador_pc'],
            $alcance['desde'],
            $alcance['hasta'],
        );

        $pagina = $this->slicePaginado($this->mapTicketsTarjetaJson($tickets), $request);

        return response()->json([
            'ok' => true,
            'alcance' => [
                'titulo' => 'Tickets tarjeta — '.$alcance['titulo'],
                'subtitulo' => $alcance['subtitulo'],
            ],
            'tickets' => $pagina['items'],
            'paginacion' => $pagina['paginacion'],
        ]);
    }

    public function apiCanjesFidelidad(Request $request)
    {
        can('listar-cierres-turno-gastronomia');

        try {
            $alcance = $this->resolverAlcanceCierre($request);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $canjes = $this->categoriafidelidadCanjeService->listarPorAlcanceTurno(
            (int) $alcance['empresa_id'],
            $alcance['fecha_jornada'],
            $alcance['identificador_pc'],
            $alcance['desde'],
            $alcance['hasta'],
        );

        $pagina = $this->slicePaginado($this->mapCanjesFidelidadJson($canjes), $request);

        return response()->json([
            'ok' => true,
            'alcance' => [
                'titulo' => 'Canjes de fidelidad — '.$alcance['titulo'],
                'subtitulo' => $alcance['subtitulo'],
            ],
            'canjes' => $pagina['items'],
            'paginacion' => $pagina['paginacion'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, paginacion: array{page:int, per_page:int, total:int, total_pages:int}}
     */
    private function slicePaginado(array $items, Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 40);
        $perPage = max(10, min(200, $perPage));

        $total = count($items);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($items, $offset, $perPage);

        return [
            'items' => array_values($slice),
            'paginacion' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-cierres-turno-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filas = $this->reporteSupport->listadoDesdeRequest($request);
        $filtros = $request->only(['empresa_id', 'identificador_pc', 'fecha_desde', 'fecha_hasta', 'tipo']);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.cierres_turno.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                ])->render();

                return $this->descargarPdf($view, 'listado_cierres_turno_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new GastronomiaCierresTurnoExport($filas, $filtros))
                    ->download('cierres_turno_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaCierresTurnoExport($filas, $filtros))
                    ->download('cierres_turno_gastronomia.csv', Excel::CSV);
        }

        abort(404);
    }

    public function comprobanteParcial(Request $request, int $id)
    {
        can('ver-comprobante-cierre-turno-gastronomia');

        $parcial = CierreParcialTurnoGastronomia::query()->findOrFail($id);
        $this->assertAccesoEmpresa((int) ($parcial->turnoOperativo?->empresa_id ?? 0));

        $datos = $this->reporteSupport->datosComprobanteParcial($parcial);
        $nombre = 'cierre_parcial_turno_'.$parcial->turno_operativo_gastronomia_id.'_'.$parcial->numero_parcial.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    public function comprobanteCierre(Request $request, int $id)
    {
        can('ver-comprobante-cierre-turno-gastronomia');

        $turno = TurnoOperativoGastronomia::query()
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->findOrFail($id);

        $this->assertAccesoEmpresa((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteCierreDefinitivo($turno);
        $nombre = 'cierre_turno_gastronomia_'.$turno->id.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    public function verCierre(Request $request, int $id)
    {
        can('listar-cierres-turno-gastronomia');

        $turno = TurnoOperativoGastronomia::query()
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->findOrFail($id);

        $this->assertAccesoEmpresa((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteCierreDefinitivo($turno);

        return view('ventas.gastronomia.cierres_turno.ver', [
            'turno' => $turno,
            'datos' => $datos,
            'referencia' => (string) ($datos['subtitulo'] ?? 'Op. #'.$turno->id),
            'puede_ver_comprobante' => can('ver-comprobante-cierre-turno-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'desde_modal' => $request->query('origen') === 'modal_consulta',
        ]);
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if (count($asignadas) > 1 && ! in_array($empresaId, $asignadas, true)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    /**
     * @return array{
     *   identificador_pc: string,
     *   empresa_id: int,
     *   fecha_jornada: string,
     *   desde: Carbon,
     *   hasta: Carbon,
     *   titulo: string,
     *   subtitulo: string
     * }
     */
    private function resolverAlcanceCierre(Request $request): array
    {
        $tipo = trim((string) $request->input('tipo', ''));
        $id = (int) $request->input('id', 0);
        if ($id <= 0 || ! in_array($tipo, ['parcial', 'cierre'], true)) {
            throw new InvalidArgumentException('Registro de cierre inválido.');
        }

        $alcance = $this->reporteSupport->alcanceComprobantesRegistro($tipo, $id);
        $this->assertAccesoEmpresa((int) $alcance['empresa_id']);

        return $alcance;
    }

    /**
     * @param  list<\App\Models\Ventas\TicketcanjeGastronomia>  $canjes
     * @return list<array<string, mixed>>
     */
    private function mapCanjesPremioJson(array $canjes): array
    {
        return collect($canjes)->map(fn ($t) => [
            'id' => $t->id,
            'numerocupon' => $t->numerocupon,
            'ticket_id' => $t->ticket_id,
            'renglon' => $t->renglon,
            'sku' => $t->articulo->sku ?? '',
            'articulo' => $t->articulo->descripcion ?? '',
            'cantidad' => round((float) $t->cantidad, 4),
            'puntos' => (int) $t->puntos,
            'venta_id' => $t->venta_id,
            'venta_codigo' => $t->venta->codigo ?? '',
            'mozo' => $t->mozo->nombre ?? '',
            'apellido' => $t->apellido,
            'nombre' => $t->nombre,
            'numerodocumento' => $t->numerodocumento,
            'fechacanje' => $t->fechacanje?->format('d/m/Y H:i:s'),
        ])->values()->all();
    }

    /**
     * @param  list<\App\Models\Ventas\TickettarjetaGastronomia>  $tickets
     * @return list<array<string, mixed>>
     */
    private function mapTicketsTarjetaJson(array $tickets): array
    {
        return collect($tickets)->map(fn ($t) => [
            'id' => $t->id,
            'ticket_id' => $t->ticket_id,
            'numeroticket' => $t->numeroticket,
            'numerodocumento' => $t->numerodocumento,
            'fecha_emision' => $t->fecha?->format('d/m/Y'),
            'monto' => round((float) $t->monto, 2),
            'montoticket' => round((float) $t->montoticket, 2),
            'numerocupon' => $t->numerocupon,
            'venta_id' => $t->venta_id,
            'venta_codigo' => $t->venta->codigo ?? '',
            'created_at' => $t->created_at?->format('d/m/Y H:i:s'),
        ])->values()->all();
    }

    /**
     * @param  list<\App\Models\Ventas\CategoriafidelidadEntregaGastronomia>  $entregas
     * @return list<array<string, mixed>>
     */
    private function mapCanjesFidelidadJson(array $entregas): array
    {
        return collect($entregas)->map(fn ($e) => [
            'id' => $e->id,
            'venta_id' => $e->venta_id,
            'venta_codigo' => $e->venta->codigo ?? '',
            'tarjeta' => $e->tarjeta,
            'trackdata' => $e->trackdata,
            'documento' => $e->documento,
            'apellido' => $e->apellido,
            'nombre' => $e->nombre,
            'titular' => trim((string) $e->apellido.' '.(string) $e->nombre),
            'categoria_codigo' => $e->categoriafidelidad->codigo ?? '',
            'categoria_nombre' => $e->categoriafidelidad->nombre ?? '',
            'sku' => $e->articulo->sku ?? '',
            'articulo' => $e->articulo->descripcion ?? '',
            'fechacanje' => $e->fechacanje?->format('d/m/Y H:i:s'),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobante(array $datos, string $nombreArchivo, bool $inline)
    {
        $html = view('ventas.gastronomia.cierres_turno.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombreArchivo)
            : $pdf->download($nombreArchivo);
    }

    private function descargarPdf(string $html, string $baseNombre, string $papel, string $orientacion)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($papel, $orientacion);
        $pdf->loadHTML($html, 'UTF-8')->save($path.'/'.$baseNombre.'.pdf');

        return response()->download($path.'/'.$baseNombre.'.pdf');
    }
}
