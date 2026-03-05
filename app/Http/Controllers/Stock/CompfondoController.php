<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Compfondo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCompfondo;

class CompfondoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-composicion-fondos');
        $datas = Compfondo::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Compfondo = new Compfondo();
        	$Compfondo->sincronizarConAnita();
	
        	$datas = Compfondo::orderBy('id')->get();
		}

        return view('stock.compfondo.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-composicion-fondos');
        return view('stock.compfondo.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCompfondo $request)
    {
        $compfondo = Compfondo::create($request->all());

		// Graba anita
		$Compfondo = new Compfondo();
        $Compfondo->guardarAnita($request, $fondo->id);

        return redirect('stock/compfondo')->with('mensaje', 'Composicion de fondo creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-composicion-fondos');
        $data = Compfondo::findOrFail($id);
        return view('stock.compfondo.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCompfondo $request, $id)
    {
        can('actualizar-composicion-fondos');
        Compfondo::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Compfondo = new Compfondo();
        $Compfondo->actualizarAnita($request, $id);

        return redirect('stock/compfondo')->with('mensaje', 'Composicion de fondo actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-composicion-fondos');

		// Elimina anita
		$Compfondo = new Compfondo();
        $Compfondo->eliminarAnita($id);

        if ($request->ajax()) {
            if (Compfondo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
