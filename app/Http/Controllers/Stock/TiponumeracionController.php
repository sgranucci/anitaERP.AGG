<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Tiponumeracion;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTiponumeracion;

class TiponumeracionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tipo-numeraciones');
        $datas = Tiponumeracion::orderBy('id')->get();

        return view('stock.tiponumeracion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-numeraciones');

        return view('stock.tiponumeracion.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTiponumeracion $request)
    {
        $tiponumeracion = Tiponumeracion::create($request->all());

        return redirect('stock/tiponumeracion')->with('mensaje', 'Tipo de numeracion creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-numeraciones');
        $data = Tiponumeracion::findOrFail($id);

        return view('stock.tiponumeracion.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTiponumeracion $request, $id)
    {
        can('actualizar-tipo-numeraciones');
        Tiponumeracion::findOrFail($id)->update($request->all());

        return redirect('stock/tiponumeracion')->with('mensaje', 'Tipo de numeracion actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-numeraciones');

        if ($request->ajax()) {
            if (Tiponumeracion::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
