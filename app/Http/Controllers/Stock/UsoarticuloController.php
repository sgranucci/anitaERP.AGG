<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Usoarticulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionUsoarticulo;

class UsoarticuloController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-uso-de-articulos');
        $datas = Usoarticulo::orderBy('id')->get();

        return view('stock.usoarticulo.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-uso-de-articulos');
        return view('stock.usoarticulo.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionUsoarticulo $request)
    {
        $usoarticulo = Usoarticulo::create($request->all());

        return redirect('stock/usoarticulo')->with('mensaje', 'Uso de articulo creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-uso-de-articulos');
        $data = Usoarticulo::findOrFail($id);
        return view('stock.usoarticulo.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionUsoarticulo $request, $id)
    {
        can('actualizar-uso-de-articulos');
        Usoarticulo::findOrFail($id)->update($request->all());

        return redirect('stock/usoarticulo')->with('mensaje', 'Uso de articulo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-uso-de-articulos');

        if ($request->ajax()) {
            if (Usoarticulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
