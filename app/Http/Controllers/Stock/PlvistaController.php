<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Plvista;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPlvista;

class PlvistaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-plantillas-vista');
        $datas = Plvista::with('articulos')->get();

        return view('stock.plvista.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-plantillas-vista');
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.plvista.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPlvista $request)
    {
        $plvista = Plvista::create($request->all());

        return redirect('stock/plvista')->with('mensaje', 'Plantilla a la vista creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-plantillas-vista');
        $data = Plvista::with('articulos')->where('id',$id)->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.plvista.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPlvista $request, $id)
    {
        can('actualizar-plantillas-vista');
        Plvista::findOrFail($id)->update($request->all());

        return redirect('stock/plvista')->with('mensaje', 'Plantilla a la vista actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-plantillas-vista');

        if ($request->ajax()) {
            if (Plvista::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
