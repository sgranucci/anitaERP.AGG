<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Tiponumeracion;
use Illuminate\Support\Facades\Storage;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Tipoarticulo;
use App\Http\Requests\ValidacionListaprecio;
use Auth;

class ListaprecioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-listaprecio');
        $datas = Listaprecio::with('usuario:id,nombre')->with('tiposnumeracion')->get();
        
		if ($datas->isEmpty())
		{
			$Listaprecio = new Listaprecio();
        	$Listaprecio->sincronizarConAnita();
	
        	$datas = Listaprecio::with('usuario:id,nombre')->get();
		}

        return view('stock.listaprecio.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-listaprecio');
        $tiponumeracion_query = Tiponumeracion::all();

        return view('stock.listaprecio.crear', compact('tiponumeracion_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionListaprecio $request)
    {
		$listaprecio = Listaprecio::create([
			"nombre" => $request->nombre,
			"formula" => $request->formula,
			"incluyeimpuesto" => $request->incluyeimpuesto,
			"codigo" => $request->codigo,
            "desdetalle" => $request->desdetalle,
            "hastatalle" => $request->hastatalle,
            "tiponumeracion_id" => $request->tiponumeracion_id,
			"usuarioultcambio_id" => Auth::user()->id,
				]);

		// Graba anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->guardarAnita($request, $listaprecio->id);

        return redirect('stock/listaprecio')->with('mensaje', 'Lista de precio creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-listaprecio');

        $data = Listaprecio::findOrFail($id);
        $tiponumeracion_query = Tiponumeracion::all();

        return view('stock.listaprecio.editar', compact('data', 'tiponumeracion_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionListaprecio $request, $id)
    {
        can('actualizar-listaprecio');

        $listaprecio = Listaprecio::findOrFail($id);

		$listaprecio->nombre = $request->nombre;
		$listaprecio->formula = $request->formula;
		$listaprecio->incluyeimpuesto = $request->incluyeimpuesto;
		$listaprecio->codigo = $request->codigo;
        $listaprecio->desdetalle = $request->desdetalle;
        $listaprecio->hastatalle = $request->hastatalle;
        $listaprecio->tiponumeracion_id = $request->tiponumeracion_id;
		$listaprecio->usuarioultcambio_id = Auth::user()->id;

		$listaprecio->save();

		// Actualiza anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->actualizarAnita($request, $id);

        return redirect('stock/listaprecio')->with('mensaje', 'Lista de precio actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-listaprecio');

        $listaprecio = Listaprecio::findOrFail($id);

		// Elimina anita
		$Listaprecio = new Listaprecio();
        $Listaprecio->eliminarAnita($listaprecio->codigo);

        if ($request->ajax()) {
            if (Listaprecio::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
