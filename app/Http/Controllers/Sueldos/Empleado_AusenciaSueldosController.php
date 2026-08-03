<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEmpleadoAusencia_Sueldos;
use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Services\Sueldos\AusenciaNovedadSyncService;
use App\Services\Sueldos\DevengamientoVacacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class Empleado_AusenciaSueldosController extends Controller
{
    private DevengamientoVacacionesService $devengamiento;

    private AusenciaNovedadSyncService $novedadSync;

    public function __construct(
        DevengamientoVacacionesService $devengamiento,
        AusenciaNovedadSyncService $novedadSync
    ) {
        $this->devengamiento = $devengamiento;
        $this->novedadSync = $novedadSync;
    }

    /**
     * Panel HTML de la solapa (saldos + eventos + form).
     * Solo lectura del ledger: el motor corre al abrir/guardar el empleado o al mutar ausencias.
     */
    public function panel($empleadoId)
    {
        can('editar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    public function guardar(ValidacionEmpleadoAusencia_Sueldos $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $datos = $this->normalizar($request, $empleado);
        $ausencia = Empleado_Ausencia_Sueldos::create($datos);

        $this->devengamiento->recalcularEmpleado($empleado, $this->usuarioId());
        $this->novedadSync->sincronizar($ausencia->fresh(['tipo.concepto', 'empleado']));

        return $this->responderPanel($empleado, 'Ausencia registrada con éxito');
    }

    public function actualizar(ValidacionEmpleadoAusencia_Sueldos $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $ausencia = Empleado_Ausencia_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($ausencia->empleado_id);

        $ausencia->update($this->normalizar($request, $empleado));

        $this->devengamiento->recalcularEmpleado($empleado, $this->usuarioId());
        $this->novedadSync->sincronizar($ausencia->fresh(['tipo.concepto', 'empleado']));

        return $this->responderPanel($empleado, 'Ausencia actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $ausencia = Empleado_Ausencia_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($ausencia->empleado_id);
        $ausenciaId = (int) $ausencia->id;
        $ausencia->delete();

        $this->novedadSync->anularPorAusencia($ausenciaId);
        $this->devengamiento->recalcularEmpleado($empleado, $this->usuarioId());

        return $this->responderPanel($empleado, 'Ausencia eliminada');
    }

    public function devengar(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);
        $this->devengamiento->recalcularEmpleado($empleado, $this->usuarioId());

        return $this->responderPanel($empleado, 'Saldos de vacaciones recalculados');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizar(ValidacionEmpleadoAusencia_Sueldos $request, Empleado_Sueldos $empleado): array
    {
        $tipo = Tipo_Ausencia_Sueldos::findOrFail($request->input('tipo_ausencia_id'));

        $desde = Carbon::parse($request->input('fecha_desde'));
        $hasta = Carbon::parse($request->input('fecha_hasta'));
        $tipoDias = $request->input('tipo_dias') ?: $tipo->tipo_dias ?: 'corridos';

        $dias = $request->input('dias');
        if ($dias === null || $dias === '' || (float) $dias <= 0) {
            $dias = DevengamientoVacacionesService::contarDias($desde, $hasta, $tipoDias);
        }

        $anioImputacion = $request->input('anio_imputacion');
        if ($tipo->esVacaciones() && ! $anioImputacion) {
            $anioImputacion = (int) $desde->year;
        }

        return [
            'empleado_id' => $empleado->id,
            'tipo_ausencia_id' => $tipo->id,
            'anio_imputacion' => $anioImputacion ? (int) $anioImputacion : null,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'dias' => (float) $dias,
            'tipo_dias' => $tipoDias,
            'estado' => $request->input('estado'),
            'observacion' => $request->input('observacion'),
            'usuario_id' => $this->usuarioId(),
        ];
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null)
    {
        $resumen = $this->devengamiento->resumen($empleado);
        $ausencias = $empleado->ausencias()->with('tipo')->get();
        $tipos = Tipo_Ausencia_Sueldos::query()->activos()->orderBy('orden')->orderBy('nombre')->get();

        $html = view('sueldos.empleado.partials.ausencias', [
            'empleado' => $empleado,
            'resumen' => $resumen,
            'ausencias' => $ausencias,
            'tipos' => $tipos,
            'puedeEditar' => can('actualizar-empleado-sueldos', false),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje, 'resumen' => $resumen]);
    }

    private function usuarioId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }
}
