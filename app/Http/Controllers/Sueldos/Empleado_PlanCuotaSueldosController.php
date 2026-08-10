<?php

namespace App\Http\Controllers\Sueldos;

use App\Support\Database\SqlDialectSupport;
use App\Http\Controllers\Controller;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Plan_Cuota_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solapa "Préstamos / Cuotas" del empleado: planes de un concepto que se
 * liquida N veces y cae automáticamente al completarse.
 */
class Empleado_PlanCuotaSueldosController extends Controller
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
        $datos['empresa_id'] = $empleado->empresa_id;
        $datos['cuotas_liquidadas'] = 0;
        $datos['estado'] = Empleado_Plan_Cuota_Sueldos::ESTADO_ACTIVA;
        $datos['usuario_id'] = optional(auth()->user())->id;

        Empleado_Plan_Cuota_Sueldos::create($datos);

        return $this->responderPanel($empleado, 'Plan de cuotas registrado con éxito');
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-empleado-sueldos');
        $plan = Empleado_Plan_Cuota_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($plan->empleado_id);

        // Acción rápida de cambio de estado (suspender / reactivar / cancelar).
        if ($request->filled('solo_estado')) {
            $destino = (string) $request->input('solo_estado');
            $mapa = [
                'suspender' => Empleado_Plan_Cuota_Sueldos::ESTADO_SUSPENDIDA,
                'reactivar' => Empleado_Plan_Cuota_Sueldos::ESTADO_ACTIVA,
                'cancelar' => Empleado_Plan_Cuota_Sueldos::ESTADO_CANCELADA,
            ];
            if (! isset($mapa[$destino])) {
                return response()->json(['message' => 'Acción no válida.'], 422);
            }
            $plan->update(['estado' => $mapa[$destino]]);

            return $this->responderPanel($empleado, 'Plan '.$plan->estadoLabel().'.');
        }

        $datos = $this->validar($request);
        if ((int) $datos['cuotas_totales'] < (int) $plan->cuotas_liquidadas) {
            return response()->json([
                'message' => 'Las cuotas totales no pueden ser menores a las ya liquidadas ('.$plan->cuotas_liquidadas.').',
            ], 422);
        }
        // Si vuelve a tener cuotas por liquidar, reactiva un plan finalizado.
        if ($plan->estado === Empleado_Plan_Cuota_Sueldos::ESTADO_FINALIZADA
            && (int) $datos['cuotas_totales'] > (int) $plan->cuotas_liquidadas) {
            $datos['estado'] = Empleado_Plan_Cuota_Sueldos::ESTADO_ACTIVA;
        }

        $plan->update($datos);

        return $this->responderPanel($empleado, 'Plan de cuotas actualizado con éxito');
    }

    public function eliminar($id)
    {
        can('actualizar-empleado-sueldos');
        $plan = Empleado_Plan_Cuota_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($plan->empleado_id);

        if ((int) $plan->cuotas_liquidadas > 0) {
            return response()->json([
                'message' => 'El plan ya liquidó cuotas; no se puede borrar. Use "Cancelar" para detenerlo.',
            ], 422);
        }

        $plan->delete();

        return $this->responderPanel($empleado, 'Plan de cuotas eliminado');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'concepto_id' => ['required', 'integer', 'exists:concepto_sueldos,id'],
            'descripcion' => ['required', 'string', 'max:120'],
            'tipo_valor' => ['required', Rule::in([Empleado_Plan_Cuota_Sueldos::TIPO_FIJO, Empleado_Plan_Cuota_Sueldos::TIPO_FORMULA])],
            'cuota_valor' => ['nullable', 'numeric', 'required_if:tipo_valor,'.Empleado_Plan_Cuota_Sueldos::TIPO_FIJO],
            'cuota_formula' => ['nullable', 'string', 'max:2000', 'required_if:tipo_valor,'.Empleado_Plan_Cuota_Sueldos::TIPO_FORMULA],
            'importe_total' => ['nullable', 'numeric'],
            'cuotas_totales' => ['required', 'integer', 'min:1', 'max:600'],
            'periodo_inicio_mes' => ['required', 'date_format:Y-m'],
            'corridas_afecta' => ['nullable', 'array'],
            'corridas_afecta.*' => [Rule::in(array_keys(Liquidacion_Sueldos::TIPOS))],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        [$anio, $mes] = explode('-', $datos['periodo_inicio_mes']);
        $out = [
            'concepto_id' => (int) $datos['concepto_id'],
            'descripcion' => $datos['descripcion'],
            'tipo_valor' => $datos['tipo_valor'],
            'cuota_valor' => $datos['tipo_valor'] === Empleado_Plan_Cuota_Sueldos::TIPO_FIJO ? (float) $datos['cuota_valor'] : null,
            'cuota_formula' => $datos['tipo_valor'] === Empleado_Plan_Cuota_Sueldos::TIPO_FORMULA ? $datos['cuota_formula'] : null,
            'importe_total' => isset($datos['importe_total']) && $datos['importe_total'] !== null ? (float) $datos['importe_total'] : null,
            'cuotas_totales' => (int) $datos['cuotas_totales'],
            'periodo_inicio' => ((int) $anio) * 100 + (int) $mes,
            'corridas_afecta' => $datos['corridas_afecta'] ?? ['mensual'],
            'observacion' => $datos['observacion'] ?? null,
        ];

        return $out;
    }

    private function responderPanel(Empleado_Sueldos $empleado, ?string $mensaje = null)
    {
        $planes = Empleado_Plan_Cuota_Sueldos::query()
            ->with('concepto:id,codigo,descripcion,tipo')
            ->where('empleado_id', $empleado->id)
            ->orderByRaw(SqlDialectSupport::ordenPorLista('estado', ['activa','suspendida','finalizada','cancelada']))
            ->orderByDesc('id')
            ->get();

        $conceptos = Concepto_Sueldos::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'tipo']);

        $html = view('sueldos.empleado.partials.planes_cuota', [
            'empleado' => $empleado,
            'planes' => $planes,
            'conceptos' => $conceptos,
            'tiposCorrida' => Liquidacion_Sueldos::TIPOS,
            'estados' => Empleado_Plan_Cuota_Sueldos::ESTADOS,
            'puedeEditar' => can('actualizar-empleado-sueldos', false),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje]);
    }
}
