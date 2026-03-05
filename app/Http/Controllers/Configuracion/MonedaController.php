<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Moneda;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMoneda;

class MonedaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-monedas');
        $datas = Moneda::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Moneda = new Moneda();
        	$Moneda->sincronizarConAnita();
	
        	$datas = Moneda::orderBy('id')->get();
		}

        return view('configuracion.moneda.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-monedas');
        return view('configuracion.moneda.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMoneda $request)
    {
        $moneda = Moneda::create($request->all());

		// Graba anita
		$Moneda = new Moneda();
        $Moneda->guardarAnita($request, $moneda->id);

        return redirect('configuracion/moneda')->with('mensaje', 'Moneda creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-monedas');
        $data = Moneda::findOrFail($id);
        return view('configuracion.moneda.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMoneda $request, $id)
    {
        can('actualizar-monedas');
        Moneda::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Moneda = new Moneda();
        $Moneda->actualizarAnita($request, $id);

        return redirect('configuracion/moneda')->with('mensaje', 'Moneda actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-monedas');

		// Elimina anita
		$Moneda = new Moneda();
        $Moneda->eliminarAnita($id);

        if ($request->ajax()) {
            if (Moneda::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function leerMoneda()
    {
        return Moneda::orderBy('id')->get();
    }
}
