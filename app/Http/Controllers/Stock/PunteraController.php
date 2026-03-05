<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Puntera;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPuntera;

class PunteraController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-punteras');
        $datas = Puntera::with('articulos')->get();

        return view('stock.puntera.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-punteras');
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.puntera.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPuntera $request)
    {
        $puntera = Puntera::create($request->all());

        return redirect('stock/puntera')->with('mensaje', 'Puntera creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-punteras');
        $data = Puntera::with('articulos')->where('id',$id)->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.puntera.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPuntera $request, $id)
    {
        can('actualizar-punteras');
        Puntera::findOrFail($id)->update($request->all());

        return redirect('stock/puntera')->with('mensaje', 'Puntera actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-punteras');

        if ($request->ajax()) {
            if (Puntera::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
