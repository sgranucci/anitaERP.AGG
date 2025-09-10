<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionDepmae;

class DepmaeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-depositos');
        $datas = Depmae::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Depmae = new Depmae();
        	$Depmae->sincronizarConAnita();
	
        	$datas = Depmae::orderBy('id')->get();
		}

        return view('stock.depmae.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-depositos');

        $tipodeposito_enum = Depmae::$enumTipoDeposito;

        return view('stock.depmae.crear', compact('tipodeposito_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionDepmae $request)
    {
        $depmae = Depmae::create($request->all());

		// Graba anita
		$Depmae = new Depmae();
        $Depmae->guardarAnita($request, $depmae->id);

        return redirect('stock/depmae')->with('mensaje', 'Deposito creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-depositos');
        $data = Depmae::findOrFail($id);
        $tipodeposito_enum = Depmae::$enumTipoDeposito;

        return view('stock.depmae.editar', compact('data', 'tipodeposito_enum'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionDepmae $request, $id)
    {
        can('actualizar-depositos');
        Depmae::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Depmae = new Depmae();
        $Depmae->actualizarAnita($request, $id);

        return redirect('stock/depmae')->with('mensaje', 'Deposito actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-depositos');

		// Elimina anita
        $Depmae = new Depmae();
        if (config('app.empresa') == 'Calzados Ferli')
            $Depmae->eliminarAnita($id);
        else
            $Depmae->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Depmae::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
