<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaCierresTurnoExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class CierreTurnoGastronomiaController extends Controller
{
    public function __construct(
        private GastronomiaCierreTurnoReporteSupport $reporteSupport,
        private EmpresaRepositoryInterface $empresaRepository,
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

        $filtros = [
            'empresa_id' => $empresaId,
            'identificador_pc' => $request->input('identificador_pc', $identificadorPc),
            'fecha_desde' => $request->input('fecha_desde', Carbon::today()->subDays(7)->format('Y-m-d')),
            'fecha_hasta' => $request->input('fecha_hasta', Carbon::today()->format('Y-m-d')),
            'tipo' => $request->input('tipo', ''),
        ];

        $request->merge($filtros);
        $filas = $this->reporteSupport->listadoDesdeRequest($request);

        return view('ventas.gastronomia.cierres_turno.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'empresa_query' => $empresaQuery,
            'identificador_pc_default' => $identificadorPc,
        ]);
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
