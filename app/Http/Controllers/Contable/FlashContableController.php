<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\FlashContableExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\FlashContableListadoFiltros;
use App\Support\Contable\FlashContableReporteSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FlashContableController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'flash_contable';

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-flash-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FlashContableListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsEmpresas($request, $filtros, $empresaQuery);
        $this->assertAccesoEmpresas(FlashContableListadoFiltros::empresaIds($filtros));

        $consultado = $request->boolean('consultar')
            && FlashContableListadoFiltros::tieneCriteriosAplicados($filtros);

        $reporte = null;
        $empresasTexto = null;

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => FlashContableListadoFiltros::empresaIds($filtros),
            ]);
            $reporte = $this->generarReporte($filtros, $empresaQuery);
            $empresasTexto = $reporte['empresas_texto'] ?? null;
        }

        return view('contable.flash_contable.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => FlashContableListadoFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'reporte' => $reporte,
            'subtitulo' => FlashContableListadoFiltros::subtitulo($filtros, $empresasTexto),
            'mes_actual' => (int) date('n'),
            'anio_actual' => (int) date('Y'),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-flash-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FlashContableListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsEmpresas($request, $filtros, $empresaQuery);

        if (! FlashContableListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('flash_contable');
        }

        $this->assertAccesoEmpresas(FlashContableListadoFiltros::empresaIds($filtros));
        $reporte = $this->generarReporte($filtros, $empresaQuery);
        $slug = 'flash_contable_'.$filtros['anio']
            .str_pad((string) $filtros['mes'], 2, '0', STR_PAD_LEFT)
            .'_'.implode('-', FlashContableListadoFiltros::empresaIds($filtros));

        return match (strtoupper((string) $formato)) {
            'EXCEL' => (new FlashContableExport($reporte))->download($slug.'.xlsx'),
            'CSV' => (new FlashContableExport($reporte, true))
                ->download($slug.'.csv', Excel::CSV),
            'PDF' => $this->descargarPdf($reporte, $slug),
            default => redirect()->route('flash_contable', FlashContableListadoFiltros::paraQueryString($filtros)),
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function generarReporte(array $filtros, $empresaQuery): array
    {
        $empresaIds = $this->ordenarEmpresaIds(
            FlashContableListadoFiltros::empresaIds($filtros),
            $empresaQuery,
        );
        $nombres = [];
        foreach ($empresaIds as $empresaId) {
            $empresa = $empresaQuery->firstWhere('id', $empresaId)
                ?? $this->empresaRepository->find($empresaId);
            $nombres[$empresaId] = (string) ($empresa->nombre ?? ('#'.$empresaId));
        }

        $reporte = FlashContableReporteSupport::armar(
            $empresaIds,
            (int) $filtros['anio'],
            (int) $filtros['mes'],
            $nombres,
        );
        $reporte['empresas_texto'] = implode(', ', array_values($nombres));

        return $reporte;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarDefaultsEmpresas(Request $request, array $filtros, $empresaQuery): array
    {
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (FlashContableListadoFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (FlashContableListadoFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $filtros['empresa_ids'] = $this->ordenarEmpresaIds(
            ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
                FlashContableListadoFiltros::empresaIds($filtros),
                $permitidos,
            ),
            $empresaQuery,
        );

        return $filtros;
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return list<int>
     */
    private function ordenarEmpresaIds(array $empresaIds, $empresaQuery): array
    {
        $seleccion = array_fill_keys($empresaIds, true);
        $ordenados = [];
        foreach ($empresaQuery as $empresa) {
            $id = (int) $empresa->id;
            if (isset($seleccion[$id])) {
                $ordenados[] = $id;
                unset($seleccion[$id]);
            }
        }
        foreach (array_keys($seleccion) as $id) {
            $ordenados[] = (int) $id;
        }

        return $ordenados;
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function assertAccesoEmpresas(array $empresaIds): void
    {
        foreach ($empresaIds as $empresaId) {
            if (! $this->empresaRepository->empresaIdPermitida((int) $empresaId)) {
                abort(403, 'No tiene acceso a la empresa seleccionada.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function descargarPdf(array $reporte, string $nombreBase)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $html = view('contable.flash_contable.listado', compact('reporte'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($path.'/'.$nombreBase.'.pdf');

        return response()->download($path.'/'.$nombreBase.'.pdf');
    }
}
