<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Caja;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCajaProducto;

class CajaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-cajas');
        $datas = Caja::with('articulos')->get();

		if ($datas->isEmpty())
		{
			$Caja = new Caja();
        	$Caja->sincronizarConAnita();
	
        	$datas = Caja::with('articulos')->get();
		}

        return view('stock.caja.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cajas');
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.caja.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCajaProducto $request)
    {
        $caja = Caja::create($request->all());

		// Graba anita
		$articulo = Articulo::where('id', $caja->articulo_id)->first();
		$Caja = new Caja();
        $Caja->guardarAnita($request, $caja->id, $articulo->sku);

        return redirect('stock/caja')->with('mensaje', 'Caja creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-cajas');
        $data = Caja::with('articulos')->where('id',$id)->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.caja.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCajaProducto $request, $id)
    {
        can('actualizar-cajas');
        Caja::findOrFail($id)->update($request->all());

		// Actualiza anita
		$articulo = Articulo::where('id', $request->articulo_id)->first();
		$Caja = new Caja();
        $Caja->actualizarAnita($request, $request->id, $articulo->sku);

        return redirect('stock/caja')->with('mensaje', 'Caja actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cajas');

		// Elimina anita
		$Caja = new Caja();
        $Caja->eliminarAnita($id);

        if ($request->ajax()) {
            if (Caja::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
