<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\BienUsoIndice;
use App\Models\Contable\BienUsoIndiceCoeficiente;
use App\Services\Contable\AjusteInflacionIndiceService;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BienUsoIndiceController extends Controller
{
    public function index()
    {
        can('listar-bien-uso-indice');
        $datas = BienUsoIndice::query()->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->paginate(20);
        $ultimos = BienUsoIndiceCoeficiente::query()
            ->whereIn('bien_uso_indice_id', $datas->pluck('id'))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get()
            ->unique('bien_uso_indice_id')
            ->keyBy('bien_uso_indice_id');

        return view('contable.bien_uso_indice.index', compact('datas', 'ultimos'));
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
        $anio = (int) $request->query('anio', (int) date('Y'));
        if ($anio < 1900 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        $coeficientes = BienUsoIndiceCoeficiente::query()
            ->where('bien_uso_indice_id', $data->id)
            ->where('anio', $anio)
            ->get()
            ->keyBy(fn ($fila) => (int) $fila->mes);
        $ultimo = BienUsoIndiceCoeficiente::query()
            ->where('bien_uso_indice_id', $data->id)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        return view('contable.bien_uso_indice.editar', [
            'data' => $data,
            'soloConsulta' => $soloConsulta,
            'tipos' => $this->tiposValor(),
            'anio' => $anio,
            'coeficientes' => $coeficientes,
            'ultimo_coeficiente' => $ultimo,
            'meses' => $this->meses(),
            'puede_cargar' => ! $soloConsulta && can('actualizar-bien-uso-indice', false),
            'es_serie_ajuste' => $data->tipo_valor === BienUsoIndice::TIPO_INDICES
                && app(AjusteInflacionIndiceService::class)->sincronizaIndiceRevaluo(),
        ]);
    }

    public function guardarCoeficientes(Request $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }
        can('actualizar-bien-uso-indice');
        $indice = BienUsoIndice::query()->findOrFail($id);
        $datos = $request->validate([
            'anio' => 'required|integer|min:1900|max:2100',
            'coeficiente' => 'nullable|array',
            'coeficiente.*' => 'nullable|numeric|min:0.000001',
        ]);
        $anio = (int) $datos['anio'];
        $indiceService = app(AjusteInflacionIndiceService::class);
        $esAjuste = $indice->tipo_valor === BienUsoIndice::TIPO_INDICES
            && $indiceService->sincronizaIndiceRevaluo();
        $guardados = 0;

        foreach (range(1, 12) as $mes) {
            $crudo = $datos['coeficiente'][$mes] ?? $datos['coeficiente'][(string) $mes] ?? null;
            if ($crudo === null || $crudo === '') {
                continue;
            }
            $valor = round((float) $crudo, 6);
            if ($valor <= 0) {
                continue;
            }
            $existente = BienUsoIndiceCoeficiente::query()
                ->where('bien_uso_indice_id', $indice->id)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->first();
            if ($existente && abs((float) $existente->coeficiente - $valor) < 0.0000005) {
                continue;
            }
            if ($esAjuste) {
                $indiceService->guardar(
                    sprintf('%04d-%02d', $anio, $mes),
                    $valor,
                    (int) auth()->id(),
                    'Carga de índice de actualización',
                    false
                );
            } else {
                BienUsoIndiceCoeficiente::query()->updateOrCreate(
                    [
                        'bien_uso_indice_id' => $indice->id,
                        'anio' => $anio,
                        'mes' => $mes,
                    ],
                    ['coeficiente' => $valor]
                );
            }
            $guardados++;
        }

        $mensaje = $guardados > 0
            ? 'Se guardaron '.$guardados.' valores de '.$anio.'.'
            : 'No había valores nuevos para guardar.';

        return redirect()
            ->route('editar_bien_uso_indice', ['id' => $indice->id, 'anio' => $anio])
            ->with('mensaje', $mensaje);
    }

    public function quitarCoeficiente(Request $request, $id, $mes)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }
        can('actualizar-bien-uso-indice');
        $anio = (int) $request->input('anio');
        $mes = (int) $mes;
        $fila = BienUsoIndiceCoeficiente::query()
            ->where('bien_uso_indice_id', (int) $id)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();
        if ($fila) {
            $fila->delete();
        }

        return redirect()
            ->route('editar_bien_uso_indice', ['id' => $id, 'anio' => $anio])
            ->with('mensaje', 'Valor del mes quitado.');
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

    /** @return array<int, string> */
    private function meses(): array
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
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
