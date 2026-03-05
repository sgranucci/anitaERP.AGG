<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Forro;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionForro;

class ForroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-forros');
        $datas = Forro::with('articulos')->get();

		if ($datas->isEmpty())
		{
			$Forro = new Forro();
        	$Forro->sincronizarConAnita();
	
        	$datas = Forro::with('articulos')->get();
		}

        return view('stock.forro.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-forros');
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.forro.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionForro $request)
    {
        $forro = Forro::create($request->all());

		// Graba anita
		$Forro = new Forro();
        $Forro->guardarAnita($request, $forro->id);

        return redirect('stock/forro')->with('mensaje', 'Forro creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-forros');
        $data = Forro::with('articulos')->where('id',$id)->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->orderby('descripcion')->get();

        return view('stock.forro.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionForro $request, $id)
    {
        can('actualizar-forros');
        Forro::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Forro = new Forro();
        $Forro->actualizarAnita($request, $id);

        return redirect('stock/forro')->with('mensaje', 'Forro actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-forros');

		// Elimina anita
		$Forro = new Forro();
        $Forro->eliminarAnita($id);

        if ($request->ajax()) {
            if (Forro::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
