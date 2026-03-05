<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoModuloRequest;
use App\Http\Requests\GuardarModuloRequest;
use App\Http\Requests\ActualizarModuloRequest;
use App\Models\Stock\Modulo;
use App\Models\Stock\Talle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ModuloController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-modulos');
        $modulos = Modulo::with('talles')->get();

		if ($modulos->isEmpty())
		{
			$Modulo = new Modulo();
        	$Modulo->sincronizarConAnita();
	
        	$modulos = Modulo::with('talles')->get();
		}

        return view('stock.modulo.index', compact('modulos'));
    }

	public function leerTalles($modulo_id)
    {
        $modulos = Modulo::with('talles')->where('id',$modulo_id)->get();

		return $modulos;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-modulos');
		$talles = Talle::all();

        return view('stock.modulo.crear', compact('talles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarModuloRequest $request)
    {
        $modulo = Modulo::create($request->all());

    	$talles = $request->input('talles', []);
    	$cantidades = $request->input('cantidades', []);
    	for ($i_talle=0; $i_talle < count($talles); $i_talle++) {
        	if ($talles[$i_talle] != '') 
			{
				/* A anita envia descripcion del talle */
				$talle = Talle::find($talles[$i_talle]);
				$desc_talle[$i_talle] = $talle->nombre;
            	$modulo->talles()->attach($talles[$i_talle], ['cantidad' => $cantidades[$i_talle]]);
        	}
    	}

		// Graba anita
		$Modulo = new Modulo();
        $Modulo->guardarAnita($request, $modulo->id, $desc_talle, $cantidades);

    	return redirect('stock/modulo')->with('mensaje', 'Modulo creado con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-modulos');
		$talles = Talle::all();
		$modulo = Modulo::where('id', $id)->first();

        return view('stock.modulo.editar', compact('talles', 'modulo'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarModuloRequest $request, $id)
    {
        can('actualizar-modulos');

        Modulo::findOrFail($id)->update($request->all());

		$modulo = Modulo::findOrFail($id);
		$modulo->talles()->detach();
        $talles = $request->input('talles', []);
        $cantidades = $request->input('cantidades', []);
        for ($i_talle=0; $i_talle < count($talles); $i_talle++) 
		{
            if ($talles[$i_talle] != '') 
			{
				/* A anita envia descripcion del talle */
				$talle = Talle::find($talles[$i_talle]);
				$desc_talle[$i_talle] = $talle->nombre;
                $modulo->talles()->attach($talles[$i_talle], ['cantidad' => $cantidades[$i_talle]]);
            }
        }

		// Actualiza anita
		$Modulo = new Modulo();
        $Modulo->actualizarAnita($request, $id, $desc_talle, $cantidades);

        return redirect('stock/modulo')->with('mensaje', 'Modulo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-modulos');

        if ($request->ajax()) 
		{
			// Elimina anita
			$Modulo = new Modulo();
        	$Modulo->eliminarAnita($id);

			$fl_borro = false;
			$modulo = Modulo::findOrFail($id);
			$modulo->talles()->detach();
            if (($modulo = Modulo::destroy($id)))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function eliminarcompleto(EliminarMasivoModuloRequest $request)
    {
        can('borrar-modulos');

        if ($request->ajax()) {
            if (Modulo::whereIn('id', request('ids'))->delete()) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
