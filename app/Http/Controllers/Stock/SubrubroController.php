<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSubrubro;
use App\Models\Stock\Rubro;
use App\Models\Stock\Subrubro;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class SubrubroController extends Controller
{
    public function index()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-subrubros');
        $datas = Subrubro::with('rubros')->orderBy('codigo')->orderBy('nombre')->get();

        return view('stock.subrubro.index', compact('datas'));
    }

    public function crear()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-subrubros');
        $rubros = Rubro::orderBy('nombre')->get();

        return view('stock.subrubro.crear', compact('rubros'));
    }

    public function guardar(ValidacionSubrubro $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-subrubros');
        Subrubro::create($this->payload($request));

        return redirect('stock/subrubro')->with('mensaje', 'Subrubro creado con éxito');
    }

    public function editar($id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-subrubros');
        $data = Subrubro::findOrFail($id);
        $rubros = Rubro::orderBy('nombre')->get();

        return view('stock.subrubro.editar', compact('data', 'rubros'));
    }

    public function actualizar(ValidacionSubrubro $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-subrubros');
        Subrubro::findOrFail($id)->update($this->payload($request));

        return redirect('stock/subrubro')->with('mensaje', 'Subrubro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-subrubros');
        Subrubro::destroy($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect('stock/subrubro')->with('mensaje', 'Subrubro eliminado con éxito');
    }

    private function payload(ValidacionSubrubro $request): array
    {
        return [
            'codigo_interno_sifab' => $request->input('codigo_interno_sifab') !== null && $request->input('codigo_interno_sifab') !== ''
                ? (int) $request->input('codigo_interno_sifab') : null,
            'rubro_id' => $request->filled('rubro_id') ? (int) $request->input('rubro_id') : null,
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'habilitado' => $request->boolean('habilitado', true),
        ];
    }
}
