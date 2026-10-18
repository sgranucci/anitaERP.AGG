<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\BienUsoRubro;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BienUsoRubroController extends Controller
{
    public function index()
    {
        can('listar-bien-uso-rubro');
        $datas = BienUsoRubro::query()->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->paginate(20);

        return view('contable.bien_uso_rubro.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-bien-uso-rubro');

        return view('contable.bien_uso_rubro.crear', ['data' => new BienUsoRubro]);
    }

    public function guardar(Request $request)
    {
        can('crear-bien-uso-rubro');
        $data = $this->validar($request);
        BienUsoRubro::query()->create($data);

        return redirect()->route('bien_uso_rubro')->with('mensaje', 'Rubro creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-bien-uso-rubro');
        } else {
            can('editar-bien-uso-rubro');
        }
        $data = BienUsoRubro::query()->findOrFail($id);

        return view('contable.bien_uso_rubro.editar', compact('data', 'soloConsulta'));
    }

    public function actualizar(Request $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }
        can('actualizar-bien-uso-rubro');
        $data = $this->validar($request, (int) $id);
        BienUsoRubro::query()->findOrFail($id)->update($data);

        return redirect()->route('bien_uso_rubro')->with('mensaje', 'Rubro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bien-uso-rubro');
        if ($request->ajax()) {
            $ok = (bool) BienUsoRubro::query()->whereKey($id)->delete();

            return response()->json(['mensaje' => $ok ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultar();
        $consulta = trim((string) $request->input('consulta', ''));
        $query = BienUsoRubro::query()->select('id', 'codigo', 'nombre');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%');
            });
        }
        $rows = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->limit(200)->get();
        $puedeAbrirAbm = can('editar-bien-uso-rubro', false) || can('listar-bien-uso-rubro', false);
        $html = '';
        if ($rows->isEmpty()) {
            $html = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td class="id">'.e($row->id).'</td>';
                $html .= '<td class="codigo">'.e($row->codigo).'</td>';
                $html .= '<td class="nombre">'.e($row->nombre).'</td>';
                $html .= '<td class="text-nowrap">';
                $html .= '<a class="btn btn-warning btn-sm eligeconsultabienusorubro">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_bien_uso_rubro', [
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
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }
        $row = BienUsoRubro::query()->where('codigo', $valor)->first(['id', 'codigo', 'nombre']);
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Rubro no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
        ]);
    }

    /** @return array{codigo: int, nombre: string} */
    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'integer', 'min:1', Rule::unique('bien_uso_rubro', 'codigo')->ignore($id)],
            'nombre' => 'required|string|max:80',
        ]);
    }

    private function assertPuedeConsultar(): void
    {
        foreach (['listar-bien-uso-rubro', 'listar-bien-uso', 'crear-bien-uso', 'editar-bien-uso', 'actualizar-bien-uso'] as $permiso) {
            if (can($permiso, false)) {
                return;
            }
        }
        abort(403);
    }
}
