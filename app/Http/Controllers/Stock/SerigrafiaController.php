<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Serigrafia;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSerigrafia;

class SerigrafiaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-serigrafias');
        $datas = Serigrafia::select('serigrafia.id', 'serigrafia.nombre', 'serigrafia.articulo_id', 
							'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
						leftjoin('articulo', 'articulo.id', 'serigrafia.articulo_id')->get();

		if ($datas->isEmpty())
		{
			$Serigrafia = new Serigrafia();
        	$Serigrafia->sincronizarConAnita();
	
        	$datas = Serigrafia::select('serigrafia.id', 'serigrafia.nombre', 'serigrafia.articulo_id', 
							'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
						leftjoin('articulo', 'articulo.id', 'serigrafia.articulo_id')->get();
		}

        return view('stock.serigrafia.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-serigrafias');
		$articulos = Articulo::select('id', 'sku', 'descripcion')->where('sku', 'like', '%SER%')->orderby('descripcion')->get();

        return view('stock.serigrafia.crear', compact('articulos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSerigrafia $request)
    {
        $serigrafia = Serigrafia::create($request->all());
		$articulos = Articulo::where('id', $serigrafia->articulo_id)->first();

		// Graba anita
		$Serigrafia = new Serigrafia();
        $Serigrafia->guardarAnita($request, $serigrafia->id, $articulos->sku);

        return redirect('stock/serigrafia')->with('mensaje', 'Serigraf&iacute;a creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-serigrafias');
        $data = Serigrafia::select('serigrafia.id', 'serigrafia.nombre', 'serigrafia.articulo_id', 
						'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
					leftjoin('articulo', 'articulo.id', 'serigrafia.articulo_id')->findOrFail($id);
		$articulos = Articulo::select('id', 'sku', 'descripcion')->where('sku', 'like', '%SER%')->orderby('descripcion')->get();
        return view('stock.serigrafia.editar', compact('data', 'articulos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSerigrafia $request, $id)
    {
        can('actualizar-serigrafias');
        Serigrafia::findOrFail($id)->update($request->all());
		$articulos = Articulo::where('id', $request->articulo_id)->first();

		// Actualiza anita
		$Serigrafia = new Serigrafia();
        $Serigrafia->actualizarAnita($request, $request->id, $articulos->sku);

        return redirect('stock/serigrafia')->with('mensaje', 'Serigraf&iacute;a actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-serigrafias');

		// Elimina anita
		$Serigrafia = new Serigrafia();
        $Serigrafia->eliminarAnita($id);

        if ($request->ajax()) {
            if (Serigrafia::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
