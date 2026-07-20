<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Familiar_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Empleado_FamiliarSueldosController extends Controller
{
    public function panel($empleadoId)
    {
        can('editar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        return $this->responderPanel($empleado);
    }

    public function guardar(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);
        $datos = $this->validar($request);
        $datos['empleado_id'] = $empleado->id;
        Empleado_Familiar_Sueldos::create($datos);

        return $this->responderPanel($empleado, 'Familiar registrado con éxito');
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $fam = Empleado_Familiar_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($fam->empleado_id);

        if ($request->boolean('solo_activo')) {
            $fam->update(['activo' => $request->boolean('activo')]);

            return $this->responderPanel($empleado, $fam->activo ? 'Familiar activado' : 'Familiar desactivado');
        }

        $fam->update($this->validar($request));

        return $this->responderPanel($empleado, 'Familiar actualizado con éxito');
    }

    public function eliminar($id)
    {
        can('actualizar-empleado-sueldos');
        $fam = Empleado_Familiar_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($fam->empleado_id);
        $fam->delete();

        return $this->responderPanel($empleado, 'Familiar eliminado');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(Empleado_Familiar_Sueldos::TIPOS))],
            'apellido' => ['nullable', 'string', 'max:60'],
            'nombre' => ['nullable', 'string', 'max:60'],
            'documento' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'porcentaje_deduccion' => ['nullable', 'integer', Rule::in([50, 100])],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'activo' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $datos['tipo'] = strtoupper($datos['tipo']);
        $datos['porcentaje_deduccion'] = (int) ($datos['porcentaje_deduccion'] ?? 100);
        if ($datos['tipo'] === 'HIJOS_50') {
            $datos['porcentaje_deduccion'] = 50;
        } elseif ($datos['tipo'] === 'HIJOS' && $datos['porcentaje_deduccion'] === 50) {
            $datos['tipo'] = 'HIJOS_50';
        }
        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null)
    {
        $familiares = Empleado_Familiar_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->orderByRaw("FIELD(tipo,'CONYUGE','HIJOS','HIJOS_50','HIJO_INCAP')")
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        $html = view('sueldos.empleado.partials.familiares', [
            'empleado' => $empleado,
            'familiares' => $familiares,
            'tipos' => Empleado_Familiar_Sueldos::TIPOS,
            'puedeEditar' => can('actualizar-empleado-sueldos', false),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje]);
    }
}
