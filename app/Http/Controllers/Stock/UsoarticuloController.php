<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUsoarticulo;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Stock\Usoarticulo;
use Illuminate\Http\Request;

class UsoarticuloController extends Controller
{
    public function index()
    {
        can('listar-uso-de-articulos');
        $datas = Usoarticulo::query()->with('arbolaprobacion')->orderBy('id')->get();

        return view('stock.usoarticulo.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-uso-de-articulos');
        $arbolesArticulo = $this->arbolesArticulo();

        return view('stock.usoarticulo.crear', compact('arbolesArticulo'));
    }

    public function guardar(ValidacionUsoarticulo $request)
    {
        $payload = $this->payloadUso($request);
        Usoarticulo::create($payload);

        return redirect('stock/usoarticulo')->with('mensaje', 'Uso de articulo creada con exito');
    }

    public function editar($id)
    {
        can('editar-uso-de-articulos');
        $data = Usoarticulo::findOrFail($id);
        $arbolesArticulo = $this->arbolesArticulo();

        return view('stock.usoarticulo.editar', compact('data', 'arbolesArticulo'));
    }

    public function actualizar(ValidacionUsoarticulo $request, $id)
    {
        can('actualizar-uso-de-articulos');
        Usoarticulo::findOrFail($id)->update($this->payloadUso($request));

        return redirect('stock/usoarticulo')->with('mensaje', 'Uso de articulo actualizado con exito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-uso-de-articulos');

        if ($request->ajax()) {
            if (Usoarticulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Arbolaprobacion>
     */
    private function arbolesArticulo()
    {
        $nombreTipo = 'Artículos';
        $idx = array_search('AR', array_column(Arbolaprobacion::$enumTipoArbol, 'valor'), true);
        if ($idx !== false) {
            $nombreTipo = Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];
        }

        return Arbolaprobacion::query()
            ->where('tipoarbol', $nombreTipo)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estado', 'tipoarbol']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadUso(ValidacionUsoarticulo $request): array
    {
        $modo = strtolower(trim((string) $request->input('aprobacion_modo', 'default')));
        if (! in_array($modo, ['auto', 'arbol', 'default'], true)) {
            $modo = 'default';
        }
        $arbolId = (int) $request->input('arbolaprobacion_id', 0);

        return [
            'nombre' => $request->input('nombre'),
            'aprobacion_modo' => $modo,
            'arbolaprobacion_id' => $arbolId > 0 ? $arbolId : null,
        ];
    }

    public function consultaUsoarticulo(Request $request)
    {
        if (! $this->puedeConsultarUsoarticulo()) {
            abort(403);
        }

        $consulta = trim((string) ($request->get('consulta') ?? ''));
        $query = Usoarticulo::query()->select('id', 'nombre');
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('nombre', 'LIKE', '%'.$consulta.'%');
                if (ctype_digit($consulta)) {
                    $q->orWhere('id', (int) $consulta);
                }
            });
        }

        $data = $query->orderBy('nombre')->limit(200)->get();
        $puedeAbrirAbm = can('editar-uso-de-articulos', false) || can('listar-uso-de-articulos', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="3">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultausoarticulo">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_usoarticulo', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnUsoarticuloPorCodigo(string $codigo)
    {
        if (! $this->puedeConsultarUsoarticulo()) {
            abort(403);
        }

        $codigo = trim($codigo);
        if ($codigo === '') {
            return response()->json(null);
        }

        $fila = null;
        if (ctype_digit($codigo)) {
            $fila = Usoarticulo::query()->select('id', 'nombre')->find((int) $codigo);
        }
        if ($fila === null) {
            $fila = Usoarticulo::query()
                ->select('id', 'nombre')
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [mb_strtoupper($codigo)])
                ->first();
        }
        if ($fila === null) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->id,
            'nombre' => (string) ($fila->nombre ?? ''),
        ]);
    }

    private function puedeConsultarUsoarticulo(): bool
    {
        return can('listar-uso-de-articulos', false)
            || can('editar-uso-de-articulos', false)
            || can('listar-articulos', false)
            || can('listar-reporte-historial-precios-compra', false);
    }
}
