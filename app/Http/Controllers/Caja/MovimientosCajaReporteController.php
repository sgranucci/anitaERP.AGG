<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\MovimientosCajaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\MovimientosCajaReporteService;
use App\Support\Caja\MovimientosCajaReporteFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MovimientosCajaReporteController extends Controller
{
    public function __construct(
        private readonly MovimientosCajaReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-movimientos-caja-reporte');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $permitidas = $empresaQuery->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $filtros = MovimientosCajaReporteFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');

        if (! $request->has('empresa_ids') && $filtros['empresa_ids'] === []) {
            $prefs = ReportePreferenciasUsuario::leerEmpresaIds('movimientos_caja_reporte');
            if (is_array($prefs) && $prefs !== []) {
                $filtros['empresa_ids'] = $prefs;
            }
        }

        if ($consultado) {
            $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas($filtros['empresa_ids'], $permitidas);
            ReportePreferenciasUsuario::persistir('movimientos_caja_reporte', [
                'empresa_ids' => $filtros['empresa_ids'],
            ]);
        } elseif ($filtros['empresa_ids'] === []) {
            $filtros['empresa_ids'] = $permitidas;
        } else {
            $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas($filtros['empresa_ids'], $permitidas);
        }

        $resultado = null;
        $filasPaginadas = null;
        $tieneEmpresas = $filtros['empresa_ids'] !== [];

        if ($consultado && $tieneEmpresas) {
            $resultado = $this->service->generar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $filas = $resultado['filas'];
            $filasPaginadas = new LengthAwarePaginator(
                array_slice($filas, ($page - 1) * $perPage, $perPage),
                count($filas),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $cuentaFiltroId = (int) ($filtros['cuentacaja_id'] ?? 0);

        return view('caja.movimientos_caja_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => MovimientosCajaReporteFiltros::paraQueryString($filtros, $consultado),
            'consultado' => $consultado,
            'empresa_query' => $empresaQuery,
            'cuentaFiltro' => $cuentaFiltroId > 0 ? Cuentacaja::query()->find($cuentaFiltroId) : null,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'puede_ver_ingresoegreso' => can('editar-ingresos-egresos-caja', false) || can('listar-ingresos-egresos-caja', false) || can('actualizar-ingresos-egresos-caja', false),
            'puede_ver_cobranza' => can('editar-cobranza', false) || can('listar-cobranza', false),
            'puede_ver_pagoproveedor' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
            'puede_ver_cuentacaja' => can('editar-cuentas-de-caja', false) || can('listar-cuentas-de-caja', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-movimientos-caja-reporte');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = MovimientosCajaReporteFiltros::resolverDesdeRequest($request);
        $filtros['empresa_ids'] = $this->filtrarEmpresaIdsPermitidas(
            $filtros['empresa_ids'],
            $empresaQuery->pluck('id')->all()
        );

        if ($filtros['empresa_ids'] === []) {
            return redirect()
                ->route('movimientos_caja_reporte', MovimientosCajaReporteFiltros::paraQueryString($filtros))
                ->with('errores', 'Seleccione al menos una empresa para exportar el reporte.');
        }

        $resultado = $this->service->generar($filtros);
        $filas = $resultado['filas'];
        $titulo = 'Movimientos de caja';
        $subtitulo = $resultado['subtitulo'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('caja.movimientos_caja_reporte.listado', [
                    'filas' => $filas,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'resultado' => $resultado,
                    'puede_ver_ingresoegreso' => false,
                    'puede_ver_cobranza' => false,
                    'puede_ver_pagoproveedor' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'reporte_movimientos_caja';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new MovimientosCajaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_movimientos_caja.xlsx');

            case 'CSV':
                return (new MovimientosCajaReporteExport($filas, $titulo, $subtitulo, $resultado))
                    ->download('reporte_movimientos_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('movimientos_caja_reporte', MovimientosCajaReporteFiltros::paraQueryString($filtros, true));
    }

    /**
     * @param  list<int>  $solicitados
     * @param  list<int|string>  $permitidos
     * @return list<int>
     */
    private function filtrarEmpresaIdsPermitidas(array $solicitados, array $permitidos): array
    {
        $permitidos = array_values(array_map('intval', $permitidos));
        if ($solicitados === []) {
            return $permitidos;
        }

        $set = array_flip($permitidos);

        return array_values(array_filter(
            $solicitados,
            static fn (int $id) => isset($set[$id])
        ));
    }
}
