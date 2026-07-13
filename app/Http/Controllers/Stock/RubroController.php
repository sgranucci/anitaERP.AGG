<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRubro;
use App\Models\Stock\Rubro;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class RubroController extends Controller
{
    public function index()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-rubros');
        $datas = Rubro::orderBy('codigo')->orderBy('nombre')->get();

        return view('stock.rubro.index', compact('datas'));
    }

    public function crear()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-rubros');

        return view('stock.rubro.crear');
    }

    public function guardar(ValidacionRubro $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-rubros');
        Rubro::create($this->payload($request));

        return redirect('stock/rubro')->with('mensaje', 'Rubro creado con éxito');
    }

    public function editar($id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-rubros');
        $data = Rubro::findOrFail($id);

        return view('stock.rubro.editar', compact('data'));
    }

    public function actualizar(ValidacionRubro $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-rubros');
        Rubro::findOrFail($id)->update($this->payload($request));

        return redirect('stock/rubro')->with('mensaje', 'Rubro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-rubros');
        Rubro::destroy($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect('stock/rubro')->with('mensaje', 'Rubro eliminado con éxito');
    }

    private function payload(ValidacionRubro $request): array
    {
        return [
            'codigo_interno_sifab' => $request->input('codigo_interno_sifab') !== null && $request->input('codigo_interno_sifab') !== ''
                ? (int) $request->input('codigo_interno_sifab') : null,
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'codigo_interno_cuenta_compra' => $request->filled('codigo_interno_cuenta_compra')
                ? (int) $request->input('codigo_interno_cuenta_compra') : null,
            'codigo_interno_cuenta_gasto' => $request->filled('codigo_interno_cuenta_gasto')
                ? (int) $request->input('codigo_interno_cuenta_gasto') : null,
            'codigo_interno_cuenta_variacion' => $request->filled('codigo_interno_cuenta_variacion')
                ? (int) $request->input('codigo_interno_cuenta_variacion') : null,
            'subrubro_obligatorio' => $request->boolean('subrubro_obligatorio'),
            'habilitado' => $request->boolean('habilitado', true),
        ];
    }
}
