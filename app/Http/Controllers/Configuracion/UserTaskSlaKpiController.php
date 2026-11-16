<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\UserTaskSlaKpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserTaskSlaKpiController extends Controller
{
    public function __construct(
        private UserTaskSlaKpiService $kpiService,
    ) {}

    public function index(Request $request): View
    {
        can('listar-modulo-aviso');

        $desde = $request->input('desde', now()->subDays(30)->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());
        $datos = $this->kpiService->construir($desde, $hasta);

        return view('configuracion.user_task_sla_kpi.index', [
            'resumen' => $datos['resumen'],
            'por_fuente' => $datos['por_fuente'],
            'por_usuario' => $datos['por_usuario'],
            'por_empresa' => $datos['por_empresa'] ?? [],
            'tendencia' => $datos['tendencia'] ?? [],
            'vencidas' => $datos['vencidas'],
            'desde' => $desde,
            'hasta' => $hasta,
            'consultar' => true,
        ]);
    }

    public function exportarCsv(Request $request): StreamedResponse
    {
        can('listar-modulo-aviso');

        $desde = $request->input('desde', now()->subDays(30)->toDateString());
        $hasta = $request->input('hasta', now()->toDateString());
        $datos = $this->kpiService->construir($desde, $hasta);

        $filename = 'kpi_sla_aprobaciones_'.$desde.'_'.$hasta.'.csv';

        return response()->streamDownload(function () use ($datos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['KPI', 'Valor']);
            foreach ($datos['resumen'] as $k => $v) {
                fputcsv($out, [$k, is_scalar($v) || $v === null ? $v : json_encode($v)]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Fuente', 'Nombre', 'Pendientes', 'Vencidas', 'Escaladas']);
            foreach ($datos['por_fuente'] as $row) {
                fputcsv($out, [$row['fuente'], $row['nombre'], $row['total'], $row['vencidas'], $row['escaladas']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Usuario ID', 'Nombre', 'Login', 'Pendientes', 'Vencidas']);
            foreach ($datos['por_usuario'] as $row) {
                fputcsv($out, [$row['usuario_id'], $row['nombre'], $row['login'], $row['total'], $row['vencidas']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Empresa ID', 'Nombre', 'Pendientes', 'Vencidas']);
            foreach (($datos['por_empresa'] ?? []) as $row) {
                fputcsv($out, [$row['empresa_id'], $row['nombre'], $row['total'], $row['vencidas']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Semana', 'Desde', 'Hasta', 'Cerradas', 'Dentro SLA', 'Fuera SLA', 'Cumplimiento %']);
            foreach (($datos['tendencia'] ?? []) as $row) {
                fputcsv($out, [
                    $row['semana'], $row['desde'], $row['hasta'], $row['cerradas'],
                    $row['dentro_sla'], $row['fuera_sla'], $row['cumplimiento_pct'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
