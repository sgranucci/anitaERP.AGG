<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Subzonavta;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSubzonavta;

class SubzonavtaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-subzonas-de-venta');
        $datas = Subzonavta::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Subzonavta = new Subzonavta();
        	$Subzonavta->sincronizarConAnita();
	
        	$datas = Subzonavta::orderBy('id')->get();
		}

        return view('ventas.subzonavta.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-subzonas-de-venta');
        return view('ventas.subzonavta.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSubzonavta $request)
    {
        $subzonavta = Subzonavta::create($request->all());

		// Graba anita
		$Subzonavta = new Subzonavta();
        $Subzonavta->guardarAnita($request, $subzonavta->id);

        return redirect('ventas/subzonavta')->with('mensaje', 'Subzonavta creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-subzonas-de-venta');
        $data = Subzonavta::findOrFail($id);
        return view('ventas.subzonavta.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSubzonavta $request, $id)
    {
        can('actualizar-subzonas-de-venta');
        Subzonavta::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Subzonavta = new Subzonavta();
        $Subzonavta->actualizarAnita($request, $id);

        return redirect('ventas/subzonavta')->with('mensaje', 'Subzonavta actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-subzonas-de-venta');

		// Elimina anita
		$Subzonavta = new Subzonavta();
        $Subzonavta->eliminarAnita($id);

        if ($request->ajax()) {
            if (Subzonavta::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
