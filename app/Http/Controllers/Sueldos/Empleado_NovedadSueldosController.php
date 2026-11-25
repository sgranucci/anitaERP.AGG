<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Support\Sueldos\EmpleadoLegajoVisualizacion;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Repositories\Sueldos\Novedad_SueldosRepositoryInterface;
use App\Services\Sueldos\NovedadMultiempresaReplicatorService;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solapa "Novedades" del empleado: entradas del período para el motor.
 */
class Empleado_NovedadSueldosController extends Controller
{
    private Novedad_SueldosRepositoryInterface $repository;

    private NovedadMultiempresaReplicatorService $replicator;

    public function __construct(
        Novedad_SueldosRepositoryInterface $repository,
        NovedadMultiempresaReplicatorService $replicator
    ) {
        $this->repository = $repository;
        $this->replicator = $replicator;
    }

    public function panel($empleadoId)
    {
        EmpleadoLegajoVisualizacion::autorizarComoEdicion();
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

        $creada = $this->repository->create($datos);
        if ($creada instanceof Novedad_Sueldos) {
            $this->replicator->replicarNovedad($creada);
        }

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
            $this->replicator->replicarNovedad($novedad->fresh());

            return $this->responderPanel($empleado, 'Estado actualizado.');
        }

        $datos = $this->validar($request);
        $datos['empleado_id'] = $empleado->id;
        $datos['empresa_id'] = $empleado->empresa_id;
        $this->repository->update($datos, $novedad->id);
        $this->replicator->replicarNovedad($novedad->fresh());

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
        $this->replicator->anularNovedadHermanas($novedad);
        $this->repository->delete($id);

        return $this->responderPanel($empleado, 'Novedad eliminada');
    }

    /**
     * Replica novedades seleccionadas (o todas las no anuladas) a legajos activos del mismo legajo en otras empresas.
     */
    public function replicar(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        if (! can('crear-novedad-sueldos', false) && ! can('actualizar-novedad-sueldos', false)) {
            return response()->json(['message' => 'Sin permiso para replicar novedades.'], 403);
        }

        $empleado = Empleado_Sueldos::findOrFail($empleadoId);
        $ids = $request->input('novedad_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $resultado = $this->replicator->replicarNovedadesEmpleado($empleado, $ids);
        if ($resultado['hermanos'] === 0) {
            return $this->responderPanel(
                $empleado,
                'Este legajo no tiene alta activa en otras empresas.'
            );
        }
        if ($resultado['replicadas'] === 0) {
            return $this->responderPanel(
                $empleado,
                'No hay novedades para replicar (seleccioná filas o cargá novedades no anuladas).'
            );
        }

        $destino = $resultado['empresas'] !== []
            ? implode(', ', $resultado['empresas'])
            : ((string) $resultado['hermanos'].' empresa(s)');

        return $this->responderPanel(
            $empleado,
            'Se replicaron '.$resultado['replicadas'].' novedad(es) a: '.$destino.'.'
        );
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

        $hermanos = $this->replicator->hermanos($empleado)->loadMissing('empresa:id,nombre,codigo');
        $puedeEditar = EmpleadoLegajoVisualizacion::puedeMutar(
            can('actualizar-empleado-sueldos', false) && can('crear-novedad-sueldos', false)
        );

        $html = view('sueldos.empleado.partials.novedades', [
            'empleado' => $empleado,
            'novedades' => $novedades,
            'estados' => NovedadSueldosCatalogo::ESTADOS,
            'puedeEditar' => $puedeEditar,
            'hermanosMultiempresa' => $hermanos,
            'puedeReplicarMultiempresa' => $puedeEditar && $hermanos->isNotEmpty(),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje]);
    }
}
