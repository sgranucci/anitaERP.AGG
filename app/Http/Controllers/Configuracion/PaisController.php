<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Pais;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPais;

class PaisController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-paises');
        $datas = Pais::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Pais = new Pais();
        	$Pais->sincronizarConAnita();
	
        	$datas = Pais::orderBy('id')->get();
		}

        return view('configuracion.pais.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-paises');
        return view('configuracion.pais.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPais $request)
    {
        $pais = Pais::create($request->all());

		// Graba anita
		$Pais = new Pais();
        $Pais->guardarAnita($request, $pais->id);

        return redirect('configuracion/pais')->with('mensaje', 'Pais creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-paises');
        $data = Pais::findOrFail($id);
        return view('configuracion.pais.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPais $request, $id)
    {
        can('actualizar-paises');
        Pais::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Pais = new Pais();
        $Pais->actualizarAnita($request, $id);

        return redirect('configuracion/pais')->with('mensaje', 'Pais actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-paises');

		// Elimina anita
		$Pais = new Pais();
        $Pais->eliminarAnita($id);

        if ($request->ajax()) {
            if (Pais::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
