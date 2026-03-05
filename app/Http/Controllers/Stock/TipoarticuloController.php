<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Tipoarticulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipoarticulo;

class TipoarticuloController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tipo-articulo');
        $datas = Tipoarticulo::orderBy('id')->get();

        return view('stock.tipoarticulo.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-articulo');

        return view('stock.tipoarticulo.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoarticulo $request)
    {
        $tipoarticulo = Tipoarticulo::create($request->all());

        return redirect('stock/tipoarticulo')->with('mensaje', 'Tipo de articulo creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-articulo');
        $data = Tipoarticulo::findOrFail($id);

        return view('stock.tipoarticulo.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoarticulo $request, $id)
    {
        can('actualizar-tipo-articulo');
        Tipoarticulo::findOrFail($id)->update($request->all());

        return redirect('stock/tipoarticulo')->with('mensaje', 'Tipo de articulo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-articulo');

        if ($request->ajax()) {
            if (Tipoarticulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
