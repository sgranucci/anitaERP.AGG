<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Services\Sueldos\FalloCuentaCorrienteReporteService;
use Illuminate\Http\Request;

/**
 * Solapa Fallos del padrón de empleados.
 */
class Empleado_FalloSueldosController extends Controller
{
    public function __construct(
        private readonly FalloCuentaCorrienteReporteService $service,
    ) {}

    public function panel(Request $request, $empleadoId)
    {
        can('editar-empleado-sueldos');
        $empleado = Empleado_Sueldos::query()
            ->with(['agrupamiento'])
            ->findOrFail($empleadoId);

        $desde = (string) $request->input('fecha_desde', now()->subYear()->toDateString());
        $hasta = (string) $request->input('fecha_hasta', now()->toDateString());
        $resumen = $this->service->resumenEmpleado($empleado, $desde, $hasta);

        $html = view('sueldos.empleado.partials.fallos', [
            'empleado' => $empleado,
            'resumen' => $resumen,
            'puedeVerReporte' => can('listar-fallo-reporte-sueldos', false),
            'puedeVerProceso' => can('listar-dtofallo-sueldos', false),
        ])->render();

        return response()->json(['html' => $html]);
    }
}
