<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Horma;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionHorma;

class HormaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-hormas');
        $datas = Horma::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Horma = new Horma();
        	$Horma->sincronizarConAnita();
	
        	$datas = Horma::orderBy('id')->get();
		}

        return view('stock.horma.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-hormas');
        return view('stock.horma.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionHorma $request)
    {
        $horma = Horma::create($request->all());

		// Graba anita
		$Horma = new Horma();
        $Horma->guardarAnita($request, $horma->id);

        return redirect('stock/horma')->with('mensaje', 'Horma creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-hormas');
        $data = Horma::findOrFail($id);
        return view('stock.horma.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionHorma $request, $id)
    {
        can('actualizar-hormas');
        Horma::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Horma = new Horma();
        $Horma->actualizarAnita($request, $id);

        return redirect('stock/horma')->with('mensaje', 'Horma actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-hormas');

		// Elimina anita
		$Horma = new Horma();
        $Horma->eliminarAnita($id);

        if ($request->ajax()) {
            if (Horma::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
