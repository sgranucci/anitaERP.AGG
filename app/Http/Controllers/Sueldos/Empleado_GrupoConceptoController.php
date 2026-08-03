<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Empleado_Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Grupo_Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Sueldos;
use App\Services\Sueldos\ConceptoSetEfectivoService;
use App\Support\Sueldos\ConceptoElegibilidadCatalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solapa Liquidación: N grupos, set efectivo y +/- explícitos.
 */
class Empleado_GrupoConceptoController extends Controller
{
    public function __construct(private ConceptoSetEfectivoService $setEfectivo)
    {
    }

    public function panel($empleadoId)
    {
        can('editar-empleado-sueldos');
        $empleado = Empleado_Sueldos::with('gruposConcepto')->findOrFail($empleadoId);

        return $this->responder($empleado);
    }

    /** Agrega un grupo más (N sin límite). */
    public function agregarGrupo(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $datos = $request->validate([
            'grupo_concepto_id' => ['required', 'integer', 'exists:grupo_concepto_sueldos,id'],
        ]);

        $grupoId = (int) $datos['grupo_concepto_id'];
        $existe = Empleado_Grupo_Concepto_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->where('grupo_concepto_id', $grupoId)
            ->exists();
        if ($existe) {
            return $this->responder($empleado->fresh('gruposConcepto'), 'El grupo ya está asignado', 422);
        }

        $maxOrden = (int) Empleado_Grupo_Concepto_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->max('orden');

        Empleado_Grupo_Concepto_Sueldos::create([
            'empleado_id' => $empleado->id,
            'grupo_concepto_id' => $grupoId,
            'orden' => $maxOrden + 1,
            'origen' => 'manual',
        ]);

        $this->refrescarCodigosAnitaEspejo($empleado);

        return $this->responder($empleado->fresh('gruposConcepto'), 'Grupo agregado');
    }

    public function quitarGrupo($pivotId)
    {
        can('actualizar-empleado-sueldos');
        $row = Empleado_Grupo_Concepto_Sueldos::findOrFail($pivotId);
        $empleado = Empleado_Sueldos::findOrFail($row->empleado_id);
        $row->delete();
        $this->reordenarGrupos($empleado->id);
        $this->refrescarCodigosAnitaEspejo($empleado);

        return $this->responder($empleado->fresh('gruposConcepto'), 'Grupo quitado');
    }

    public function guardarExplicito(Request $request, $empleadoId)
    {
        can('actualizar-empleado-sueldos');
        $empleado = Empleado_Sueldos::findOrFail($empleadoId);

        $datos = $request->validate([
            'concepto_id' => ['required', 'integer', 'exists:concepto_sueldos,id'],
            'accion' => ['required', Rule::in(array_keys(ConceptoElegibilidadCatalogo::ACCIONES))],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        Empleado_Concepto_Sueldos::updateOrCreate(
            [
                'empleado_id' => $empleado->id,
                'concepto_id' => (int) $datos['concepto_id'],
                'accion' => $datos['accion'],
            ],
            [
                'fecha_desde' => $datos['fecha_desde'] ?? null,
                'fecha_hasta' => $datos['fecha_hasta'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'origen' => 'manual',
                'usuario_id' => optional(auth()->user())->id,
            ]
        );

        return $this->responder($empleado->fresh('gruposConcepto'), 'Asignación explícita guardada');
    }

    public function eliminarExplicito($id)
    {
        can('actualizar-empleado-sueldos');
        $row = Empleado_Concepto_Sueldos::findOrFail($id);
        $empleado = Empleado_Sueldos::findOrFail($row->empleado_id);
        $row->delete();

        return $this->responder($empleado->fresh('gruposConcepto'), 'Asignación eliminada');
    }

    private function reordenarGrupos(int $empleadoId): void
    {
        $rows = Empleado_Grupo_Concepto_Sueldos::query()
            ->where('empleado_id', $empleadoId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        $n = 0;
        foreach ($rows as $r) {
            $n++;
            if ((int) $r->orden !== $n) {
                $r->orden = $n;
                $r->save();
            }
        }
    }

    /** Espejo emp_grp1/2/3 para fórmulas Anita (primeros 3 del pivot). */
    private function refrescarCodigosAnitaEspejo(Empleado_Sueldos $empleado): void
    {
        $codigos = Empleado_Grupo_Concepto_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->with('grupo:id,codigo')
            ->limit(3)
            ->get()
            ->map(fn ($r) => (int) optional($r->grupo)->codigo)
            ->filter(fn ($c) => $c > 0)
            ->values()
            ->all();

        Empleado_Sueldos::query()->where('id', $empleado->id)->update([
            'grupo_concepto_1_codigo' => $codigos[0] ?? null,
            'grupo_concepto_2_codigo' => $codigos[1] ?? null,
            'grupo_concepto_3_codigo' => $codigos[2] ?? null,
        ]);
    }

    private function responder(Empleado_Sueldos $empleado, ?string $mensaje = null, int $status = 200)
    {
        $set = $this->setEfectivo->resolver($empleado);
        $gruposDisponibles = Grupo_Concepto_Sueldos::query()
            ->where('activo', true)
            ->where(function ($q) use ($empleado) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empleado->empresa_id);
            })
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'empresa_id']);

        $explicatos = Empleado_Concepto_Sueldos::query()
            ->with('concepto:id,codigo,descripcion')
            ->where('empleado_id', $empleado->id)
            ->orderByDesc('id')
            ->get();

        $asignadosIds = collect($set['grupos'])->pluck('id')->all();

        $html = view('sueldos.empleado.partials.set_conceptos', [
            'empleado' => $empleado,
            'set' => $set,
            'gruposDisponibles' => $gruposDisponibles,
            'asignadosIds' => $asignadosIds,
            'explicatos' => $explicatos,
            'acciones' => ConceptoElegibilidadCatalogo::ACCIONES,
            'puedeEditar' => can('actualizar-empleado-sueldos', false),
            'puedeConsultarGrupo' => can('listar-grupo-concepto-sueldos', false)
                || can('editar-grupo-concepto-sueldos', false)
                || can('editar-empleado-sueldos', false)
                || can('actualizar-empleado-sueldos', false),
        ])->render();

        return response()->json([
            'html' => $html,
            'mensaje' => $mensaje,
            'set_efectivo' => [
                'modo' => $set['modo'],
                'cantidad' => $set['conceptos']->count(),
                'grupos' => $set['grupos'],
            ],
        ], $status);
    }
}
