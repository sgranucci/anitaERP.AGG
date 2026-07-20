<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\CanjeMarketingListadoExport;
use App\Http\Controllers\Controller;
use App\Queries\Ventas\CanjeMarketingListadoQuery;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use App\Support\Ventas\CanjeMarketingListadoFiltros;
use App\Support\Ventas\CanjeMarketingListadoListaprecioCmvSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class CanjeMarketingListadoController extends Controller
{
    public function __construct(
        private readonly CanjeMarketingListadoQuery $query,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly UbicacionGastronomiaRepositoryInterface $ubicacionRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-canje-marketing-gastronomia');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $this->assertAccesoEmpresa($empresaId);

        $filtros = CanjeMarketingListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaId);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $perPage = max(10, min(200, (int) $request->input('per_page', 10)));
        $filas = $this->query->listado($filtros, true, $perPage);
        $queryString = CanjeMarketingListadoFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $queryString['per_page'] = $perPage;
        }
        $filas->appends($queryString);

        $empresaIdSelectores = (int) ($filtros['empresa_id'] ?? 0) > 0
            ? (int) $filtros['empresa_id']
            : $empresaId;

        return view('ventas.gastronomia.canjes.listado_marketing.index', [
            'filas' => $filas,
            'filtros' => $filtros,
            'filtrosQuery' => CanjeMarketingListadoFiltros::paraQueryString($filtros),
            'empresa_query' => $empresaQuery,
            'ubicacion_query' => $this->ubicacionRepository->listarParaSelect(
                $empresaIdSelectores > 0 ? $empresaIdSelectores : null
            ),
            'empresa_id' => $empresaId,
            'totales' => $this->query->totales($filtros),
            'listaprecio_cmv_etiqueta' => CanjeMarketingListadoListaprecioCmvSupport::etiquetaLista(),
            'puede_ver_articulo' => can('editar-articulos', false),
            'puede_ver_cliente_vip' => can('editar-cliente-vip-gastronomia', false),
            'puede_ver_mozo' => can('editar-mozo-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-canje-marketing-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $this->assertAccesoEmpresa($empresaId);

        $filtros = CanjeMarketingListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaId);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $filas = $this->query->listado($filtros, false);
        $totales = $this->query->totales($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.canjes.listado_marketing.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'totales' => $totales,
                    'listaprecio_cmv_etiqueta' => CanjeMarketingListadoListaprecioCmvSupport::etiquetaLista(),
                ])->render();

                return $this->descargarPdf($view, 'listado_canje_marketing_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new CanjeMarketingListadoExport($this->query))
                    ->parametros($filtros)
                    ->download('listado_canje_marketing_gastronomia.xlsx');

            case 'CSV':
                return (new CanjeMarketingListadoExport($this->query))
                    ->parametros($filtros, true)
                    ->download('listado_canje_marketing_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route('canje_marketing_listado', CanjeMarketingListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, int $empresaId): array
    {
        if ($empresaId > 0 && (int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $filtros['empresa_id'] = $empresaId;
        }

        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }

        [$desde, $hasta] = CanjeMarketingListadoFiltros::normalizarRangoFechas(
            $filtros['fecha_desde'],
            $filtros['fecha_hasta'],
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombreBase.'.pdf');

        return response()->download($path.'/'.$nombreBase.'.pdf');
    }
}
