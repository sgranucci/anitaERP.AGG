<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\TicketCanjeCajaReporteExport;
use App\Http\Controllers\Controller;
use App\Queries\Caja\TicketCanjeCajaReporteQuery;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\TicketCanjeCajaReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel;

class TicketCanjeCajaReporteController extends Controller
{
    public function __construct(
        private readonly TicketCanjeCajaReporteQuery $query,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly UsuarioRepositoryInterface $usuarioRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-informe-ticket-canje-caja');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtros = TicketCanjeCajaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaults($filtros, $empresa_query);
        $this->assertEmpresaPermitida((int) ($filtros['empresa_id'] ?? 0));

        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        $usuario_query = $this->usuariosParaSelector($filtros);
        $filtros['usuario_nombre'] = $this->nombreUsuarioFiltro($filtros, $usuario_query);
        $consultado = ! empty($filtros['consultar']) && TicketCanjeCajaReporteFiltros::tieneCriteriosAplicados($filtros);

        $filas = new LengthAwarePaginator([], 0, 25, 1);
        $totales = ['cantidad' => 0, 'monto_venta' => 0.0, 'monto_ticket' => 0.0];

        if ($consultado) {
            $perPage = max(10, min(200, (int) $request->input('per_page', 25)));
            $filas = $this->query->listado($filtros, true, $perPage);
            $filas->appends(TicketCanjeCajaReporteFiltros::paraQueryString($filtros));
            $totales = $this->query->totales($filtros);
        }

        return view('caja.canjes.informe.index', [
            'empresa_query' => $empresa_query,
            'usuario_query' => $usuario_query,
            'filtros' => $filtros,
            'filtrosQuery' => TicketCanjeCajaReporteFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'subtitulo' => TicketCanjeCajaReporteFiltros::subtitulo($filtros),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-informe-ticket-canje-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtros = TicketCanjeCajaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaults($filtros, $empresa_query);
        $filtros['consultar'] = true;
        $this->assertEmpresaPermitida((int) ($filtros['empresa_id'] ?? 0));
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['usuario_nombre'] = $this->nombreUsuarioFiltro($filtros, $this->usuariosParaSelector($filtros));

        if (! TicketCanjeCajaReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route(
                'informe_ticket_canje_caja',
                TicketCanjeCajaReporteFiltros::paraQueryString($filtros)
            )->with('errores', ['Debe indicar empresa y rango de fechas.']);
        }

        $filas = $this->query->listado($filtros, false);
        $totales = $this->query->totales($filtros);
        $titulo = 'Informe de Datos de Ventas / Canjes';
        $subtitulo = TicketCanjeCajaReporteFiltros::subtitulo($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('caja.canjes.informe.listado', [
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'informe_ticket_canje_caja_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new TicketCanjeCajaReporteExport($filas, $totales, $titulo, $subtitulo, $filtros))
                    ->download('informe_ticket_canje_caja.xlsx');

            case 'CSV':
                return (new TicketCanjeCajaReporteExport($filas, $totales, $titulo, $subtitulo, $filtros))
                    ->download('informe_ticket_canje_caja.csv', Excel::CSV);
        }

        return redirect()->route(
            'informe_ticket_canje_caja',
            TicketCanjeCajaReporteFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarDefaults(array $filtros, $empresaQuery): array
    {
        if (empty($filtros['empresa_id']) && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }
        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
        }
        if (empty($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function usuariosParaSelector(array $filtros)
    {
        $emisores = $this->query->idsUsuariosEmisores($filtros);
        if ($emisores === []) {
            return collect();
        }

        return $this->usuarioRepository
            ->listadoOperativoParaSelector(
                ! empty($filtros['empresa_id']) ? (int) $filtros['empresa_id'] : null,
                null,
                ['id', 'nombre', 'usuario'],
            )
            ->whereIn('id', $emisores)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $usuarioQuery
     */
    private function nombreUsuarioFiltro(array $filtros, $usuarioQuery): ?string
    {
        $usuarioId = (int) ($filtros['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            return null;
        }

        $usuario = $usuarioQuery->firstWhere('id', $usuarioId)
            ?? $this->usuarioRepository->findOperativo($usuarioId);

        return $usuario?->nombre;
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida.');
        }
    }
}
