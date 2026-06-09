<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Exports\Caja\Estacionamiento\EstacionamientoCierresTurnoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Estacionamiento\CierreParcialTurnoEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamientoRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoOperativoService;
use App\Support\Caja\Estacionamiento\EstacionamientoCierresTurnoListadoFiltros;
use App\Support\Caja\Estacionamiento\EstacionamientoCierreTurnoReporteSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;
use Throwable;

class CierreTurnoEstacionamientoController extends Controller
{
    public function __construct(
        private EstacionamientoCierreTurnoReporteSupport $reporteSupport,
        private EmpresaRepositoryInterface $empresaRepository,
        private EstacionamientoPvService $pvService,
        private JornadaEstacionamientoService $jornadaService,
        private EstacionamientoTurnoOperativoService $turnoOperativoService,
        private ConfiguracionPuntoventaEstacionamientoRepositoryInterface $configuracionPuntoventaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-cierres-turno-estacionamiento');

        $identificadorPc = EstacionamientoIdentificadorPc::resolver($request);
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }

        $pcQuery = $this->configuracionPuntoventaRepository->all()
            ->unique(fn ($cfg) => (string) $cfg->identificador_pc)
            ->values();

        $cfg = $this->pvService->resolverConfiguracionPv($request);
        $turnoOperativo = null;
        if ($cfg !== null && EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
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

        $filtros = EstacionamientoCierresTurnoListadoFiltros::resolverDesdeRequest($request);

        if ($filtros['fecha_desde'] === '') {
            $filtros['fecha_desde'] = $fechaDesdeDefault;
        }
        if ($filtros['fecha_hasta'] === '') {
            $filtros['fecha_hasta'] = $fechaHastaDefault;
        }

        if ($filtros['identificador_pc'] === '') {
            $filtros['identificador_pc'] = $identificadorPc;
        }
        if ($empresaId > 0 && (int) $filtros['empresa_id'] <= 0) {
            $filtros['empresa_id'] = $empresaId;
        }

        $filas = $this->reporteSupport->listadoConFiltros($filtros);

        $empresaIdJornada = $empresaId > 0
            ? $empresaId
            : (int) ($cfg?->empresa_id ?? 0);
        $jornada = $empresaIdJornada > 0
            ? $this->jornadaService->estadoParaEmpresa($empresaIdJornada)
            : null;

        return view('caja.estacionamiento.cierres_turno.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => EstacionamientoCierresTurnoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => EstacionamientoCierresTurnoListadoFiltros::CAMPOS,
            'empresa_query' => $empresaQuery,
            'pc_query' => $pcQuery,
            'identificador_pc_default' => $identificadorPc,
            'turno_operativo' => $turnoOperativo,
            'jornada' => $jornada,
            'empresa_id_jornada' => $empresaIdJornada,
            'requiere_habilitacion_turno' => EstacionamientoTurnoOperativoService::requiereHabilitacionTurno(),
            'puede_ver_comprobante' => can('ver-comprobante-cierre-turno-estacionamiento', false),
            'puede_ver_factura' => can('ver-factura-estacionamiento', false),
        ]);
    }

    public function apiComprobantes(Request $request)
    {
        can('listar-cierres-turno-estacionamiento');

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
        $perPage = (int) $request->input('per_page', EstacionamientoTurnoOperativoTotalesSupport::CONCILIACION_FILAS_POR_PAGINA);
        $soloDiferencias = $request->boolean('solo_diferencias');

        try {
            $grilla = EstacionamientoTurnoOperativoTotalesSupport::grillaConciliacionRespuesta(
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
            'url_factura_ver_base' => can('ver-factura-estacionamiento', false)
                ? url('caja/estacionamiento/facturas-dia')
                : null,
        ]);
    }




    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, paginacion: array{page:int, per_page:int, total:int, total_pages:int}}
     */

    public function exportar(Request $request, string $formato)
    {
        can('listar-cierres-turno-estacionamiento');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = EstacionamientoCierresTurnoListadoFiltros::resolverDesdeRequest($request);
        $filas = $this->reporteSupport->listadoConFiltros($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('caja.estacionamiento.cierres_turno.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                ])->render();

                return $this->descargarPdf($view, 'listado_cierres_turno_estacionamiento', 'legal', 'landscape');

            case 'EXCEL':
                return (new EstacionamientoCierresTurnoExport($filas, $filtros))
                    ->download('cierres_turno_estacionamiento.xlsx');

            case 'CSV':
                return (new EstacionamientoCierresTurnoExport($filas, $filtros))
                    ->download('cierres_turno_estacionamiento.csv', Excel::CSV);
        }

        abort(404);
    }

    public function comprobanteParcial(Request $request, int $id)
    {
        can('ver-comprobante-cierre-turno-estacionamiento');

        $parcial = CierreParcialTurnoEstacionamiento::query()->findOrFail($id);
        $this->assertAccesoEmpresa((int) ($parcial->turnoOperativo?->empresa_id ?? 0));

        $datos = $this->reporteSupport->datosComprobanteParcial($parcial);
        $nombre = 'cierre_parcial_turno_'.$parcial->turno_operativo_estacionamiento_id.'_'.$parcial->numero_parcial.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    public function comprobanteCierre(Request $request, int $id)
    {
        can('ver-comprobante-cierre-turno-estacionamiento');

        $turno = TurnoOperativoEstacionamiento::query()
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->findOrFail($id);

        $this->assertAccesoEmpresa((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteCierreDefinitivo($turno);
        $nombre = 'cierre_turno_estacionamiento_'.$turno->id.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    public function verCierre(Request $request, int $id)
    {
        can('listar-cierres-turno-estacionamiento');

        $turno = TurnoOperativoEstacionamiento::query()
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->findOrFail($id);

        $this->assertAccesoEmpresa((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteCierreDefinitivo($turno);

        return view('caja.estacionamiento.cierres_turno.ver', [
            'turno' => $turno,
            'datos' => $datos,
            'referencia' => (string) ($datos['subtitulo'] ?? 'Op. #'.$turno->id),
            'puede_ver_comprobante' => can('ver-comprobante-cierre-turno-estacionamiento', false),
            'puede_ver_factura' => can('ver-factura-estacionamiento', false),
            'desde_modal' => $request->query('origen') === 'modal_consulta',
        ]);
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobante(array $datos, string $nombreArchivo, bool $inline)
    {
        $html = view('caja.estacionamiento.cierres_turno.comprobante', compact('datos'))->render();
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
