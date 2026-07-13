<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCentroemisor;
use App\Models\Configuracion\Oficinacompra;
use App\Models\Stock\Centroemisor;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class CentroemisorController extends Controller
{
    public function index()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-centros-emisores');
        $datas = Centroemisor::with('oficinacompras')->orderBy('codigo')->orderBy('nombre')->get();

        return view('stock.centroemisor.index', compact('datas'));
    }

    public function crear()
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-centros-emisores');
        $oficinacompras = Oficinacompra::orderBy('nombre')->get();

        return view('stock.centroemisor.crear', compact('oficinacompras'));
    }

    public function guardar(ValidacionCentroemisor $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-centros-emisores');
        Centroemisor::create($this->payload($request));

        return redirect('stock/centroemisor')->with('mensaje', 'Centro emisor creado con éxito');
    }

    public function editar($id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-centros-emisores');
        $data = Centroemisor::findOrFail($id);
        $oficinacompras = Oficinacompra::orderBy('nombre')->get();

        return view('stock.centroemisor.editar', compact('data', 'oficinacompras'));
    }

    public function actualizar(ValidacionCentroemisor $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-centros-emisores');
        Centroemisor::findOrFail($id)->update($this->payload($request));

        return redirect('stock/centroemisor')->with('mensaje', 'Centro emisor actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-centros-emisores');
        Centroemisor::destroy($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect('stock/centroemisor')->with('mensaje', 'Centro emisor eliminado con éxito');
    }

    private function payload(ValidacionCentroemisor $request): array
    {
        return [
            'codigo_interno_sifab' => $request->input('codigo_interno_sifab') !== null && $request->input('codigo_interno_sifab') !== ''
                ? (int) $request->input('codigo_interno_sifab') : null,
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'calle' => $request->input('calle'),
            'numero' => $request->input('numero'),
            'piso' => $request->input('piso'),
            'departamento' => $request->input('departamento'),
            'codigo_postal' => $request->input('codigo_postal'),
            'barrio' => $request->input('barrio'),
            'oficinacompra_id' => $request->filled('oficinacompra_id') ? (int) $request->input('oficinacompra_id') : null,
            'habilitado' => $request->boolean('habilitado', true),
        ];
    }
}
