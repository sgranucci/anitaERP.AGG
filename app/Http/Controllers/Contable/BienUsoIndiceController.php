<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\BienUsoIndice;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BienUsoIndiceController extends Controller
{
    public function index()
    {
        can('listar-bien-uso-indice');
        $datas = BienUsoIndice::query()->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->paginate(20);

        return view('contable.bien_uso_indice.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-bien-uso-indice');

        return view('contable.bien_uso_indice.crear', [
            'data' => new BienUsoIndice,
            'tipos' => $this->tiposValor(),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-bien-uso-indice');
        $data = $this->validar($request);
        BienUsoIndice::query()->create($data);

        return redirect()->route('bien_uso_indice')->with('mensaje', 'Índice creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-bien-uso-indice');
        } else {
            can('editar-bien-uso-indice');
        }
        $data = BienUsoIndice::query()->findOrFail($id);

        return view('contable.bien_uso_indice.editar', [
            'data' => $data,
            'soloConsulta' => $soloConsulta,
            'tipos' => $this->tiposValor(),
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }
        can('actualizar-bien-uso-indice');
        $data = $this->validar($request, (int) $id);
        BienUsoIndice::query()->findOrFail($id)->update($data);

        return redirect()->route('bien_uso_indice')->with('mensaje', 'Índice actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bien-uso-indice');
        if ($request->ajax()) {
            $ok = (bool) BienUsoIndice::query()->whereKey($id)->delete();

            return response()->json(['mensaje' => $ok ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultar();
        $consulta = trim((string) $request->input('consulta', ''));
        $query = BienUsoIndice::query()->select('id', 'codigo', 'nombre', 'tipo_valor');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%');
            });
        }
        $rows = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->limit(200)->get();
        $puedeAbrirAbm = can('editar-bien-uso-indice', false) || can('listar-bien-uso-indice', false);
        $html = '';
        if ($rows->isEmpty()) {
            $html = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td class="id">'.e($row->id).'</td>';
                $html .= '<td class="codigo">'.e($row->codigo).'</td>';
                $html .= '<td class="nombre">'.e($row->nombre).'</td>';
                $html .= '<td>'.e($row->tipo_valor).'</td>';
                $html .= '<td class="text-nowrap">';
                $html .= '<a class="btn btn-warning btn-sm eligeconsultabienusoindice">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_bien_uso_indice', [
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
        $row = BienUsoIndice::query()->where('codigo', $valor)->first(['id', 'codigo', 'nombre', 'tipo_valor']);
        if (! $row) {
            return response()->json(['ok' => false, 'mensaje' => 'Índice no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $row->id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'tipo_valor' => (string) $row->tipo_valor,
        ]);
    }

    /** @return array{codigo: int, nombre: string, tipo_valor: string} */
    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'integer', 'min:1', Rule::unique('bien_uso_indice', 'codigo')->ignore($id)],
            'nombre' => 'required|string|max:80',
            'tipo_valor' => ['required', 'string', 'size:1', Rule::in([
                BienUsoIndice::TIPO_COEFICIENTE,
                BienUsoIndice::TIPO_INDICES,
                BienUsoIndice::TIPO_DIRECTO,
            ])],
        ]);
    }

    /** @return list<array{valor: string, nombre: string}> */
    private function tiposValor(): array
    {
        return [
            ['valor' => BienUsoIndice::TIPO_COEFICIENTE, 'nombre' => 'Coeficiente (C)'],
            ['valor' => BienUsoIndice::TIPO_INDICES, 'nombre' => 'Índices (I)'],
            ['valor' => BienUsoIndice::TIPO_DIRECTO, 'nombre' => 'Directo (D)'],
        ];
    }

    private function assertPuedeConsultar(): void
    {
        foreach (['listar-bien-uso-indice', 'listar-bien-uso', 'crear-bien-uso', 'editar-bien-uso', 'actualizar-bien-uso'] as $permiso) {
            if (can($permiso, false)) {
                return;
            }
        }
        abort(403);
    }
}
