<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGrupoproducto;
use App\Models\Stock\Grupoproducto;
use App\Models\Stock\Linea;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class GrupoproductoController extends Controller
{
    public function index()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-grupos-producto');
        $datas = Grupoproducto::with('lineas')->orderBy('codigo')->orderBy('nombre')->get();

        return view('stock.grupoproducto.index', compact('datas'));
    }

    public function crear()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-grupos-producto');
        $lineas = Linea::orderBy('nombre')->get();

        return view('stock.grupoproducto.crear', compact('lineas'));
    }

    public function guardar(ValidacionGrupoproducto $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-grupos-producto');
        Grupoproducto::create($this->payload($request));

        return redirect('stock/grupoproducto')->with('mensaje', 'Grupo producto creado con éxito');
    }

    public function editar($id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-grupos-producto');
        $data = Grupoproducto::findOrFail($id);
        $lineas = Linea::orderBy('nombre')->get();

        return view('stock.grupoproducto.editar', compact('data', 'lineas'));
    }

    public function actualizar(ValidacionGrupoproducto $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-grupos-producto');
        Grupoproducto::findOrFail($id)->update($this->payload($request));

        return redirect('stock/grupoproducto')->with('mensaje', 'Grupo producto actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-grupos-producto');
        Grupoproducto::destroy($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect('stock/grupoproducto')->with('mensaje', 'Grupo producto eliminado con éxito');
    }

    private function payload(ValidacionGrupoproducto $request): array
    {
        return [
            'codigo_interno_sifab' => $request->input('codigo_interno_sifab') !== null && $request->input('codigo_interno_sifab') !== ''
                ? (int) $request->input('codigo_interno_sifab') : null,
            'codigo' => $request->input('codigo'),
            'linea_id' => $request->filled('linea_id') ? (int) $request->input('linea_id') : null,
            'nombre' => $request->input('nombre'),
            'habilitado' => $request->boolean('habilitado', true),
        ];
    }
}
