<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Mventa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMventa;

class MventaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-marcas-de-venta');
        $datas = Mventa::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Mventa = new Mventa();
        	$Mventa->sincronizarConAnita();
	
        	$datas = Mventa::orderBy('id')->get();
		}

        return view('stock.mventa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-marcas-de-venta');
        return view('stock.mventa.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMventa $request)
    {
        $mventa = Mventa::create($request->all());

		// Graba anita
		$Mventa = new Mventa();
        $Mventa->guardarAnita($request, $mventa->id);

        return redirect('stock/mventa')->with('mensaje', 'Marca creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-marcas-de-venta');
        $data = Mventa::findOrFail($id);
        return view('stock.mventa.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMventa $request, $id)
    {
        can('actualizar-marcas-de-venta');
        Mventa::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Mventa = new Mventa();
        $Mventa->actualizarAnita($request, $id);

        return redirect('stock/mventa')->with('mensaje', 'Marca actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-marcas-de-venta');

		// Elimina anita
		$Mventa = new Mventa();
        $Mventa->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Mventa::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
