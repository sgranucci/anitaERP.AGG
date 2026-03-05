<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Plarmado;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPlarmado;

class PlarmadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-plantilla-de-armado');
        $datas = Plarmado::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Plarmado = new Plarmado();
        	$Plarmado->sincronizarConAnita();
	
        	$datas = Plarmado::orderBy('id')->get();
		}

        return view('stock.plarmado.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-plantilla-de-armado');
        return view('stock.plarmado.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPlarmado $request)
    {
        $plarmado = Plarmado::create($request->all());

		// Graba anita
		$Plarmado = new Plarmado();
        $Plarmado->guardarAnita($request, $plarmado->id);

        return redirect('stock/plarmado')->with('mensaje', 'Plarmado creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-plantilla-de-armado');
        $data = Plarmado::findOrFail($id);
        return view('stock.plarmado.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPlarmado $request, $id)
    {
        can('actualizar-plantilla-de-armado');
        Plarmado::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Plarmado = new Plarmado();
        $Plarmado->actualizarAnita($request, $id);

        return redirect('stock/plarmado')->with('mensaje', 'Plarmado actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-plantilla-de-armado');

		// Elimina anita
		$Plarmado = new Plarmado();
        $Plarmado->eliminarAnita($id);

        if ($request->ajax()) {
            if (Plarmado::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
