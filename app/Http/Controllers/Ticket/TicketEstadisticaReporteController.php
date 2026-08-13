<?php

namespace App\Http\Controllers\Ticket;

use App\Exports\Ticket\TicketEstadisticaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket_Estado;
use App\Queries\Ticket\TicketEstadisticaReporteQuery;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Support\Ticket\TicketEstadisticaReporteCacheSupport;
use App\Support\Ticket\TicketEstadisticaReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel;

class TicketEstadisticaReporteController extends Controller
{
    public function __construct(
        private readonly TicketEstadisticaReporteQuery $query,
        private readonly SalaRepositoryInterface $salaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-informe-estadistico-ticket');

        $filtros = $this->aplicarDefaults(TicketEstadisticaReporteFiltros::resolverDesdeRequest($request));
        $consultado = ! empty($filtros['consultar']) && TicketEstadisticaReporteFiltros::tieneCriteriosAplicados($filtros);

        $filasPaginadas = new LengthAwarePaginator([], 0, 25, 1);
        $totales = $this->totalesVacios();
        $modoTiempo = TicketEstadisticaReporteFiltros::esPorTecnico($filtros) ? 'tecnico' : 'ticket';

        if ($consultado) {
            $resultado = $this->resolverResultado(
                $filtros,
                $request->boolean('consultar') && ! $request->filled('page')
            );
            $todas = $resultado['filas'];
            $totales = $resultado['totales'];
            $modoTiempo = $resultado['modo_tiempo'];
            $filasPaginadas = $this->paginar($todas, $request, TicketEstadisticaReporteFiltros::paraQueryString($filtros));
        }

        $tecnicoQuery = $this->query->tecnicosArea();
        $salaQuery = $this->salaRepository->all();
        $nombreTecnico = $this->nombreTecnico($filtros, $tecnicoQuery);
        $nombreSala = $this->nombreSala($filtros, $salaQuery);

        return view('ticket.estadistica_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => TicketEstadisticaReporteFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'filas' => $filasPaginadas,
            'totales' => $totales,
            'modo_tiempo' => $modoTiempo,
            'tecnico_query' => $tecnicoQuery,
            'sala_query' => $salaQuery,
            'estado_enum' => Ticket_Estado::$enumEstado,
            'subtitulo' => TicketEstadisticaReporteFiltros::subtitulo($filtros, $nombreSala, $nombreTecnico),
            'puede_ver_ticket' => can('editar-ticket', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-informe-estadistico-ticket');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->aplicarDefaults(TicketEstadisticaReporteFiltros::resolverDesdeRequest($request));
        $filtros['consultar'] = true;

        if (! TicketEstadisticaReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route(
                'informe_estadistico_ticket',
                TicketEstadisticaReporteFiltros::paraQueryString($filtros)
            )->with('errores', ['Indique el rango de fechas para generar el informe.']);
        }

        $resultado = $this->resolverResultado($filtros, false);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $modoTiempo = $resultado['modo_tiempo'];
        $tecnicoQuery = $this->query->tecnicosArea();
        $salaQuery = $this->salaRepository->all();
        $titulo = 'Informe estadístico de tickets — Área Tecnología';
        $subtitulo = TicketEstadisticaReporteFiltros::subtitulo(
            $filtros,
            $this->nombreSala($filtros, $salaQuery),
            $this->nombreTecnico($filtros, $tecnicoQuery)
        );

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ticket.estadistica_reporte.listado', [
                    'filas' => $filas,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'modo_tiempo' => $modoTiempo,
                    'puede_ver_ticket' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'informe_estadistico_ticket_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new TicketEstadisticaReporteExport($filas, $totales, $titulo, $subtitulo, $modoTiempo))
                    ->download('informe_estadistico_ticket.xlsx');

            case 'CSV':
                return (new TicketEstadisticaReporteExport($filas, $totales, $titulo, $subtitulo, $modoTiempo))
                    ->download('informe_estadistico_ticket.csv', Excel::CSV);
        }

        return redirect()->route(
            'informe_estadistico_ticket',
            TicketEstadisticaReporteFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaults(array $filtros): array
    {
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
     * @return array{filas: \Illuminate\Support\Collection, totales: array<string, mixed>, modo_tiempo: string}
     */
    private function resolverResultado(array $filtros, bool $forzarRecalculo): array
    {
        if (! $forzarRecalculo) {
            $cached = TicketEstadisticaReporteCacheSupport::recuperar($filtros);
            if ($cached !== null) {
                return $cached;
            }
        }

        $resultado = $this->query->generar($filtros);
        TicketEstadisticaReporteCacheSupport::guardar($filtros, $resultado);

        return $resultado;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $filas
     * @param  array<string, string|int>  $queryString
     */
    private function paginar($filas, Request $request, array $queryString): LengthAwarePaginator
    {
        $perPage = 25;
        $page = max(1, (int) $request->input('page', 1));
        $total = $filas->count();
        $slice = $filas->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $queryString,
        ]);
        $paginator->appends($queryString);

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    private function totalesVacios(): array
    {
        return [
            'cantidad' => 0,
            'suma_insumido' => 0,
            'suma_insumido_fmt' => '0',
            'promedio_insumido' => 0,
            'promedio_insumido_fmt' => '0',
            'cantidad_con_asignacion' => 0,
            'promedio_asignacion' => null,
            'promedio_asignacion_fmt' => '',
            'cantidad_con_resolucion' => 0,
            'promedio_resolucion' => null,
            'promedio_resolucion_fmt' => '',
        ];
    }

    private function nombreTecnico(array $filtros, $tecnicoQuery): string
    {
        $id = (int) ($filtros['tecnico_id'] ?? 0);
        if ($id <= 0) {
            return '';
        }
        $tec = $tecnicoQuery->firstWhere('id', $id);

        return trim((string) ($tec->nombre ?? ''));
    }

    private function nombreSala(array $filtros, $salaQuery): string
    {
        $id = (int) ($filtros['sala_id'] ?? 0);
        if ($id <= 0) {
            return '';
        }
        $sala = $salaQuery->firstWhere('id', $id);

        return trim((string) ($sala->nombre ?? ''));
    }
}
