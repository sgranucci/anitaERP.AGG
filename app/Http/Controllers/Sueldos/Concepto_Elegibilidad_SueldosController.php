<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Concepto_Elegibilidad_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Reglas de elegibilidad del concepto:
 * AND entre grupos OR; dentro del mismo grupo_or basta una regla.
 * Vigencia opcional (effective dating) por fecha de liquidación.
 */
class Concepto_Elegibilidad_SueldosController extends Controller
{
    public function panel($conceptoId)
    {
        can('editar-concepto-sueldos');
        $concepto = Concepto_Sueldos::findOrFail($conceptoId);

        return $this->responder($concepto);
    }

    public function guardar(Request $request, $conceptoId)
    {
        can('actualizar-concepto-sueldos');
        $concepto = Concepto_Sueldos::findOrFail($conceptoId);

        $rules = [
            'campo' => ['required', Rule::in(array_keys(ConceptoElegibilidadCatalogo::CAMPOS))],
            'operador' => ['required', Rule::in(array_keys(ConceptoElegibilidadCatalogo::OPERADORES))],
            'valor' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'grupo_or' => ['nullable', 'integer', 'min:1', 'max:999'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
        ];
        $datos = $request->validate($rules);

        $op = $datos['operador'];
        $valor = trim((string) ($datos['valor'] ?? ''));
        if (! in_array($op, ['vacio', 'no_vacio'], true) && $valor === '') {
            return response()->json([
                'message' => 'Indique un valor para el operador elegido.',
                'errors' => ['valor' => ['Indique un valor para el operador elegido.']],
            ], 422);
        }

        $grupoOr = (int) ($datos['grupo_or'] ?? 0);
        if ($grupoOr <= 0 && Schema::hasColumn('concepto_elegibilidad_sueldos', 'grupo_or')) {
            $grupoOr = (int) Concepto_Elegibilidad_Sueldos::query()
                ->where('concepto_id', $concepto->id)
                ->max('grupo_or') + 1;
            if ($grupoOr < 1) {
                $grupoOr = 1;
            }
        }

        $payload = [
            'concepto_id' => $concepto->id,
            'campo' => $datos['campo'],
            'operador' => $op,
            'valor' => in_array($op, ['vacio', 'no_vacio'], true) ? null : $valor,
            'activo' => $request->boolean('activo', true),
        ];
        if (Schema::hasColumn('concepto_elegibilidad_sueldos', 'grupo_or')) {
            $payload['grupo_or'] = $grupoOr;
        }
        if (Schema::hasColumn('concepto_elegibilidad_sueldos', 'vigente_desde')) {
            $payload['vigente_desde'] = $datos['vigente_desde'] ?? null;
            $payload['vigente_hasta'] = $datos['vigente_hasta'] ?? null;
        }

        Concepto_Elegibilidad_Sueldos::create($payload);

        return $this->responder($concepto, 'Regla agregada');
    }

    public function eliminar($id)
    {
        can('actualizar-concepto-sueldos');
        $regla = Concepto_Elegibilidad_Sueldos::findOrFail($id);
        $concepto = Concepto_Sueldos::findOrFail($regla->concepto_id);
        $regla->delete();

        return $this->responder($concepto, 'Regla eliminada');
    }

    private function responder(Concepto_Sueldos $concepto, ?string $mensaje = null)
    {
        $q = Concepto_Elegibilidad_Sueldos::query()->where('concepto_id', $concepto->id);
        if (Schema::hasColumn('concepto_elegibilidad_sueldos', 'grupo_or')) {
            $q->orderBy('grupo_or');
        }
        $reglas = $q->orderBy('id')->get();

        $html = view('sueldos.concepto.partials.elegibilidad', [
            'concepto' => $concepto,
            'reglas' => $reglas,
            'campos' => ConceptoElegibilidadCatalogo::CAMPOS,
            'operadores' => ConceptoElegibilidadCatalogo::OPERADORES,
            'puedeEditar' => can('actualizar-concepto-sueldos', false),
            'siguienteGrupoOr' => (int) $reglas->max('grupo_or') + 1,
        ])->render();

        return response()->json([
            'html' => $html,
            'mensaje' => $mensaje,
            'cantidad' => $reglas->count(),
        ]);
    }
}
