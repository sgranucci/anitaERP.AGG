<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\BienUsoSubrubro;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BienUsoSubrubroController extends Controller
{
    public function index()
    {
        can('listar-bien-uso-subrubro');
        $datas = BienUsoSubrubro::query()
            ->with('rubro:id,codigo,nombre')
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))
            ->paginate(20);

        return view('contable.bien_uso_subrubro.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-bien-uso-subrubro');

        return view('contable.bien_uso_subrubro.crear', [
            'data' => new BienUsoSubrubro,
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-bien-uso-subrubro');
        $data = $this->validar($request);
        BienUsoSubrubro::query()->create($data);

        return redirect()->route('bien_uso_subrubro')->with('mensaje', 'Subrubro creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-bien-uso-subrubro');
        } else {
            can('editar-bien-uso-subrubro');
        }
        $data = BienUsoSubrubro::query()->findOrFail($id);

        $data->load('rubro');

        return view('contable.bien_uso_subrubro.editar', [
            'data' => $data,
            'soloConsulta' => $soloConsulta,
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }
        can('actualizar-bien-uso-subrubro');
        $data = $this->validar($request, (int) $id);
        BienUsoSubrubro::query()->findOrFail($id)->update($data);

        return redirect()->route('bien_uso_subrubro')->with('mensaje', 'Subrubro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bien-uso-subrubro');
        if ($request->ajax()) {
            $ok = (bool) BienUsoSubrubro::query()->whereKey($id)->delete();

            return response()->json(['mensaje' => $ok ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultar();
        $consulta = trim((string) $request->input('consulta', ''));
        $rubroId = (int) $request->input('bien_uso_rubro_id', 0);
        $query = BienUsoSubrubro::query()
            ->with('rubro:id,codigo,nombre')
            ->select('id', 'codigo', 'nombre', 'bien_uso_rubro_id');
        if ($rubroId > 0) {
            $query->where('bien_uso_rubro_id', $rubroId);
        }
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%');
            });
        }
        $rows = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->limit(200)->get();
        $puedeAbrirAbm = can('editar-bien-uso-subrubro', false) || can('listar-bien-uso-subrubro', false);
        $html = '';
        if ($rows->isEmpty()) {
            $html = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td class="id">'.e($row->id).'</td>';
                $html .= '<td class="codigo">'.e($row->codigo).'</td>';
                $html .= '<td class="nombre">'.e($row->nombre).'</td>';
                $html .= '<td>'.e(optional($row->rubro)->nombre ?? '').'</td>';
                $html .= '<td class="text-nowrap">';
                $html .= '<a class="btn btn-warning btn-sm eligeconsultabienusosubrubro">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_bien_uso_subrubro', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $html .= '</td></tr>';
            }
        }

        return response()->json(['data' => $html]);
    }

    public function resolver(Request $request)
    {
        $this->assertPuedeConsultar();
        $valor = trim((string) $request->query('valor', ''));
        $rubroId = (int) $request->query('bien_uso_rubro_id', 0);
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }
        $query = BienUsoSubrubro::query()->where('codigo', $valor);
        if ($rubroId > 0) {
            $query->where('bien_uso_rubro_id', $rubroId);
        }
        $row = $query->first(['id', 'codigo', 'nombre', 'bien_uso_rubro_id']);
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Subrubro no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'bien_uso_rubro_id' => (int) $row->bien_uso_rubro_id,
        ]);
    }

    /** @return array{codigo: int, nombre: string, bien_uso_rubro_id: int} */
    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'integer', 'min:1', Rule::unique('bien_uso_subrubro', 'codigo')->ignore($id)],
            'nombre' => 'required|string|max:80',
            'bien_uso_rubro_id' => 'required|integer|exists:bien_uso_rubro,id',
        ]);
    }

    private function assertPuedeConsultar(): void
    {
        foreach (['listar-bien-uso-subrubro', 'listar-bien-uso', 'crear-bien-uso', 'editar-bien-uso', 'actualizar-bien-uso'] as $permiso) {
            if (can($permiso, false)) {
                return;
            }
        }
        abort(403);
    }
}
