<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Unidadmedida;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionUnidadmedida;

class UnidadmedidaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-unidades-de-medida');
        $datas = Unidadmedida::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Unidadmedida = new Unidadmedida();
        	$Unidadmedida->sincronizarConAnita();
	
        	$datas = Unidadmedida::orderBy('id')->get();
		}

        return view('stock.unidadmedida.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-unidades-de-medida');
        return view('stock.unidadmedida.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionUnidadmedida $request)
    {
        $unidadmedida = Unidadmedida::create($request->all());

		// Graba anita
		$Unidadmedida = new Unidadmedida();
        $Unidadmedida->guardarAnita($request, $unidadmedida->id);

        return redirect('stock/unidadmedida')->with('mensaje', 'Unidadmedida creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-unidades-de-medida');
        $data = Unidadmedida::findOrFail($id);
        return view('stock.unidadmedida.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionUnidadmedida $request, $id)
    {
        can('actualizar-unidades-de-medida');
        Unidadmedida::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Unidadmedida = new Unidadmedida();
        $Unidadmedida->actualizarAnita($request, $id);

        return redirect('stock/unidadmedida')->with('mensaje', 'Unidadmedida actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-unidades-de-medida');

		// Elimina anita
		$Unidadmedida = new Unidadmedida();
        $Unidadmedida->eliminarAnita($id);

        if ($request->ajax()) {
            if (Unidadmedida::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
