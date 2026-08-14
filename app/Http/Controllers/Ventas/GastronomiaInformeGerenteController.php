<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaInformeGerenteExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\JornadaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaInformeGerentePowerpointService;
use App\Services\Ventas\Gastronomia\GastronomiaInformeGerenteService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Ventas\GastronomiaInformeGerenteCacheSupport;
use App\Support\Ventas\GastronomiaInformeGerenteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class GastronomiaInformeGerenteController extends Controller
{
    public function __construct(
        private readonly GastronomiaInformeGerenteService $informeService,
        private readonly GastronomiaInformeGerentePowerpointService $powerpointService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
        private readonly GastronomiaJornadaService $jornadaService,
    ) {}

    public function index(Request $request)
    {
        can('ver-informe-gerente-gastronomia');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->aplicarDefaults(
            GastronomiaInformeGerenteFiltros::resolverDesdeRequest($request),
            $empresaQuery,
        );
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');

        $informe = null;
        if (GastronomiaInformeGerenteFiltros::tieneCriteriosAplicados($filtros)) {
            if ($request->boolean('refrescar_cache')) {
                GastronomiaInformeGerenteCacheSupport::limpiar($filtros);
            }
            $informe = $this->obtenerInforme($filtros);
        }

        $jornadas = $empresaId > 0
            ? $this->jornadaRepository->historialPorEmpresa($empresaId, 40)
            : collect();

        $jornadaRegistro = null;
        if ($empresaId > 0 && GastronomiaInformeGerenteFiltros::esUnSoloDia($filtros)) {
            $jornadaRegistro = JornadaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha_jornada', $fechaDesde)
                ->orderByDesc('id')
                ->first();
        }

        $filtrosQuery = GastronomiaInformeGerenteFiltros::paraQueryString($filtros);

        return view('ventas.gastronomia.informe_gerente.index', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'fecha_jornada' => $fechaHasta, // compat partials legacy
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'informe' => $informe,
            'jornadas' => $jornadas,
            'jornada_registro' => $jornadaRegistro,
            'periodo_texto' => GastronomiaInformeGerenteFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('ver-informe-gerente-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->aplicarDefaults(
            GastronomiaInformeGerenteFiltros::resolverDesdeRequest($request),
            $empresaQuery,
        );
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! GastronomiaInformeGerenteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()
                ->route('gastronomia_informe_gerente', GastronomiaInformeGerenteFiltros::paraQueryString($filtros))
                ->with('errores', ['Seleccione empresa y rango de fechas antes de exportar.']);
        }

        $empresaId = (int) $filtros['empresa_id'];
        if ($request->boolean('refrescar_cache')) {
            GastronomiaInformeGerenteCacheSupport::limpiar($filtros);
        }
        $informe = $this->obtenerInforme($filtros);

        $empresaTexto = $this->etiquetaEmpresa($empresaId, $empresaQuery);
        $titulo = 'Informe gerente gastronomía';
        $subtitulo = 'Empresa: '.$empresaTexto.' · Período: '
            .GastronomiaInformeGerenteFiltros::formatearPeriodoTexto($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.informe_gerente.listado', [
                    'informe' => $informe,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                ])->render();

                return $this->descargarPdf($view);

            case 'EXCEL':
                return (new GastronomiaInformeGerenteExport)
                    ->parametros($informe, $titulo, $subtitulo, $empresaTexto)
                    ->download('informe_gerente_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaInformeGerenteExport)
                    ->parametros($informe, $titulo, $subtitulo, $empresaTexto, true)
                    ->download('informe_gerente_gastronomia.csv', Excel::CSV);

            case 'PPTX':
            case 'POWERPOINT':
            case 'PPT':
                return $this->powerpointService->descargar(
                    $informe,
                    $titulo,
                    $subtitulo,
                    $empresaTexto,
                );
        }

        return redirect()->route(
            'gastronomia_informe_gerente',
            GastronomiaInformeGerenteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * Snapshot por firma de filtros: pantalla y export reutilizan sin regenerar.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function obtenerInforme(array $filtros): array
    {
        $cached = GastronomiaInformeGerenteCacheSupport::recuperar($filtros);
        if ($cached !== null) {
            return $cached;
        }

        $informe = $this->informeService->generar(
            (int) $filtros['empresa_id'],
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
            $filtros,
        );
        GastronomiaInformeGerenteCacheSupport::guardar($filtros, $informe);

        return GastronomiaInformeGerenteCacheSupport::recuperar($filtros) ?? $informe;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaults(array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $fechaJornadaDefault = Carbon::today()->format('Y-m-d');
        if ($empresaId > 0) {
            $jornadaAbierta = $this->jornadaService->estadoParaEmpresa($empresaId);
            if (! empty($jornadaAbierta['fecha_jornada'])) {
                $fechaJornadaDefault = (string) $jornadaAbierta['fecha_jornada'];
            }
        }

        if (trim((string) ($filtros['fecha_desde'] ?? '')) === '') {
            $filtros['fecha_desde'] = $fechaJornadaDefault;
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) === '') {
            $filtros['fecha_hasta'] = $fechaJornadaDefault;
        }

        [$desde, $hasta] = GastronomiaInformeGerenteFiltros::normalizarRangoFechas(
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        return $filtros;
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        $empresa = $empresaQuery->firstWhere('id', $empresaId);
        if ($empresa === null) {
            return 'Empresa #'.$empresaId;
        }

        return trim((string) ($empresa->nombre ?? $empresa->codigo ?? ('#'.$empresaId)));
    }

    private function descargarPdf(string $html)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $archivo = 'informe_gerente_gastronomia_'.date('Ymd_His').'_'.uniqid('', true).'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($path.'/'.$archivo);

        return response()->download($path.'/'.$archivo);
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
}
