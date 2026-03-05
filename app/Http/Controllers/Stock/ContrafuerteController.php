<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Contrafuerte;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionContrafuerte;

class ContrafuerteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-contrafuertes');
        $datas = Contrafuerte::with('articulos')->get();

        return view('stock.contrafuerte.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-contrafuertes');
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.contrafuerte.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionContrafuerte $request)
    {
        $contrafuerte = Contrafuerte::create($request->all());

        return redirect('stock/contrafuerte')->with('mensaje', 'Contrafuerte creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-contrafuertes');
        $data = Contrafuerte::with('articulos')->where('id',$id)->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.contrafuerte.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionContrafuerte $request, $id)
    {
        can('actualizar-contrafuertes');
        Contrafuerte::findOrFail($id)->update($request->all());

        return redirect('stock/contrafuerte')->with('mensaje', 'Contrafuerte actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-contrafuertes');

        if ($request->ajax()) {
            if (Contrafuerte::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
