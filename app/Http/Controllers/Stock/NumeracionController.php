<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Numeracion;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionNumeracion;

class NumeracionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-numeraciones');
        $datas = Numeracion::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Numeracion = new Numeracion();
        	$Numeracion->sincronizarConAnita();
	
        	$datas = Numeracion::orderBy('id')->get();
		}

        return view('stock.numeracion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-numeraciones');
        return view('stock.numeracion.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionNumeracion $request)
    {
        $numeracion = Numeracion::create($request->all());

		// Graba anita
		$Numeracion = new Numeracion();
        $Numeracion->guardarAnita($request, $numeracion->id);

        return redirect('stock/numeracion')->with('mensaje', 'Numeracion creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-numeraciones');
        $data = Numeracion::findOrFail($id);
        return view('stock.numeracion.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionNumeracion $request, $id)
    {
        can('actualizar-numeraciones');
        Numeracion::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Numeracion = new Numeracion();
        $Numeracion->actualizarAnita($request, $id);

        return redirect('stock/numeracion')->with('mensaje', 'Numeracion actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-numeraciones');

		// Elimina anita
		$Numeracion = new Numeracion();
        $Numeracion->eliminarAnita($id);

        if ($request->ajax()) {
            if (Numeracion::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
