<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solapa "Novedades" del empleado: entradas del período para el motor.
 */
class Empleado_NovedadSueldosController extends Controller
{
    private Novedad_SueldosRepositoryInterface $repository;

    public function __construct(Novedad_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function panel($empleadoId)
    {
        can('editar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    public function guardar(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        if (! can('crear-novedad-sueldos', false)) {
            return response()->json(['message' => 'Sin permiso para crear novedades.'], 403);
        }
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $datos = $this->validar($request);
        $datos['empleado_id'] = $empleado->id;
        $datos['empresa_id'] = $empleado->empresa_id;
        $datos['origen'] = NovedadSueldosCatalogo::ORIGEN_MANUAL;
        $datos['usuario_id'] = optional(auth()->user())->id;

        $this->repository->create($datos);

        return $this->responderPanel($empleado, 'Novedad registrada con éxito');
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        if (! can('actualizar-novedad-sueldos', false)) {
            return response()->json(['message' => 'Sin permiso para actualizar novedades.'], 403);
        }
        $novedad = Novedad_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($novedad->empleado_id);

        if ($request->filled('solo_estado')) {
            $destino = (string) $request->input('solo_estado');
            if (! isset(NovedadSueldosCatalogo::ESTADOS[$destino])) {
                return response()->json(['message' => 'Estado no válido.'], 422);
            }
            $novedad->update(['estado' => $destino]);

            return $this->responderPanel($empleado, 'Estado actualizado.');
        }

        $datos = $this->validar($request);
        $datos['empleado_id'] = $empleado->id;
        $datos['empresa_id'] = $empleado->empresa_id;
        $this->repository->update($datos, $novedad->id);

        return $this->responderPanel($empleado, 'Novedad actualizada con éxito');
    }

    public function eliminar($id)
    {
        can('actualizar-empleado-sueldos');
        if (! can('borrar-novedad-sueldos', false)) {
            return response()->json(['message' => 'Sin permiso para borrar novedades.'], 403);
        }
        $novedad = Novedad_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($novedad->empleado_id);
        $this->repository->delete($id);

        return $this->responderPanel($empleado, 'Novedad eliminada');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'concepto_id' => ['required', 'integer', 'exists:concepto_sueldos,id'],
            'liquidacion_id' => ['nullable', 'integer', 'exists:liquidacion_sueldos,id'],
            'valor1' => ['nullable', 'numeric'],
            'valor2' => ['nullable', 'numeric'],
            'estado' => ['nullable', 'string', Rule::in(NovedadSueldosCatalogo::estadosPermitidos())],
            'fecha_vto' => ['nullable', 'date'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'nro_interno' => ['nullable', 'integer', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null)
    {
        $novedades = Novedad_Sueldos::query()
            ->with(['concepto:id,codigo,descripcion', 'liquidacion:id,numero,periodo,descripcion'])
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $liquidaciones = Liquidacion_Sueldos::query()
            ->where('empresa_id', $empleado->empresa_id)
            ->whereIn('estado', Liquidacion_Sueldos::ESTADOS_EDITABLES)
            ->orderByDesc('periodo')
            ->orderByDesc('numero')
            ->limit(40)
            ->get(['id', 'numero', 'periodo', 'descripcion', 'estado']);

        // Si no hay abiertas, mostrar últimas corridas igual.
        if ($liquidaciones->isEmpty()) {
            $liquidaciones = Liquidacion_Sueldos::query()
                ->where('empresa_id', $empleado->empresa_id)
                ->orderByDesc('periodo')
                ->orderByDesc('numero')
                ->limit(20)
                ->get(['id', 'numero', 'periodo', 'descripcion', 'estado']);
        }

        $html = view('sueldos.empleado.partials.novedades', [
            'empleado' => $empleado,
            'novedades' => $novedades,
            'liquidaciones' => $liquidaciones,
            'estados' => NovedadSueldosCatalogo::ESTADOS,
            'puedeEditar' => can('actualizar-empleado-sueldos', false) && can('crear-novedad-sueldos', false),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje]);
    }
}
