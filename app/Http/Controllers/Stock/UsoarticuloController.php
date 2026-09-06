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
}
