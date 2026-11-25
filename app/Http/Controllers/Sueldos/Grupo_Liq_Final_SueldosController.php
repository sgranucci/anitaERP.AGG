<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGrupo_Liq_Final_Sueldos;
use App\Models\Configuracion\Empresa;
use App\Models\Sueldos\Grupo_Concepto_Sueldos;
use App\Models\Sueldos\Grupo_Liq_Final_Sueldos;
use App\Models\Sueldos\Motivoegreso_Sueldos;
use App\Models\Sueldos\Sindicato_Sueldos;
use Illuminate\Http\Request;

class Grupo_Liq_Final_SueldosController extends Controller
{
    public function index(Request $request)
    {
        can('listar-grupo-liq-final-sueldos');

        $empresaId = (int) $request->input('empresa_id', 0);
        $sindicatoId = (int) $request->input('sindicato_id', 0);

        $q = Grupo_Liq_Final_Sueldos::query()
            ->with([
                'empresa:id,nombre',
                'sindicato:id,codigo,descripcion',
                'motivoegreso:id,codigo,descripcion',
                'grupoConcepto:id,codigo,descripcion,empresa_id',
            ])
            ->orderBy('empresa_id')
            ->orderBy('sindicato_id')
            ->orderByRaw('motivoegreso_id IS NOT NULL')
            ->orderBy('motivoegreso_id');

        if ($empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }
        if ($sindicatoId > 0) {
            $q->where('sindicato_id', $sindicatoId);
        }

        return view('sueldos.grupo_liq_final.index', [
            'datas' => $q->paginate(50)->withQueryString(),
            'empresas' => Empresa::query()->orderBy('nombre')->get(['id', 'nombre']),
            'sindicatos' => Sindicato_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'empresaId' => $empresaId,
            'sindicatoId' => $sindicatoId,
        ]);
    }

    public function crear()
    {
        can('crear-grupo-liq-final-sueldos');

        return view('sueldos.grupo_liq_final.crear', $this->catalogos());
    }

    public function guardar(ValidacionGrupo_Liq_Final_Sueldos $request)
    {
        can('crear-grupo-liq-final-sueldos');

        Grupo_Liq_Final_Sueldos::create($request->safe()->only([
            'empresa_id', 'sindicato_id', 'motivoegreso_id', 'grupo_concepto_id', 'activo',
        ]));

        return redirect()->route('consultar_grupo_liq_final_sueldos', [
            'empresa_id' => $request->input('empresa_id'),
            'sindicato_id' => $request->input('sindicato_id'),
        ])->with('mensaje', 'Regla de grupo para liquidación final creada.');
    }

    public function editar($id)
    {
        can('editar-grupo-liq-final-sueldos');
        $data = Grupo_Liq_Final_Sueldos::query()
            ->with('motivoegreso:id,codigo,descripcion')
            ->findOrFail($id);

        return view('sueldos.grupo_liq_final.editar', array_merge($this->catalogos(), compact('data')));
    }

    public function actualizar(ValidacionGrupo_Liq_Final_Sueldos $request, $id)
    {
        can('actualizar-grupo-liq-final-sueldos');
        $data = Grupo_Liq_Final_Sueldos::query()->findOrFail($id);
        $data->update($request->safe()->only([
            'empresa_id', 'sindicato_id', 'motivoegreso_id', 'grupo_concepto_id', 'activo',
        ]));

        return redirect()->route('consultar_grupo_liq_final_sueldos', [
            'empresa_id' => $request->input('empresa_id'),
            'sindicato_id' => $request->input('sindicato_id'),
        ])->with('mensaje', 'Regla actualizada.');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-grupo-liq-final-sueldos');
        $ok = Grupo_Liq_Final_Sueldos::query()->where('id', $id)->delete() > 0;

        if ($request->ajax()) {
            return response()->json(['mensaje' => $ok ? 'ok' : 'ng']);
        }

        return redirect()->route('consultar_grupo_liq_final_sueldos')
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Regla eliminada.' : 'No se pudo eliminar.');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'empresas' => Empresa::query()->orderBy('nombre')->get(['id', 'nombre']),
            'sindicatos' => Sindicato_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'motivos' => Motivoegreso_Sueldos::query()->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'grupos' => Grupo_Concepto_Sueldos::query()
                ->where('activo', true)
                ->orderBy('empresa_id')
                ->orderBy('codigo')
                ->get(['id', 'empresa_id', 'codigo', 'descripcion']),
        ];
    }
}
