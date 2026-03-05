<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Fondo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionFondo;

class FondoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-fondos');
        $datas = Fondo::select('fondo.id', 'fondo.nombre', 'fondo.articulo_id', 'fondo.codigo',
							'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
						leftjoin('articulo', 'articulo.id', 'fondo.articulo_id')->get();

		if ($datas->isEmpty())
		{
			$Fondo = new Fondo();
        	$Fondo->sincronizarConAnita();
	
        	$datas = Fondo::select('fondo.id', 'fondo.nombre', 'fondo.articulo_id', 'fondo.codigo',
							'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
						leftjoin('articulo', 'articulo.id', 'fondo.articulo_id')->get();
		}

        return view('stock.fondo.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-fondos');
		$articulos = Articulo::select('id', 'sku', 'descripcion')->where('sku', 'like', '%FON%')->orderby('descripcion')->get();

        return view('stock.fondo.crear', compact('articulos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionFondo $request)
    {
        $fondo = Fondo::create($request->all());
		$articulos = Articulo::where('id', $fondo->articulo_id)->first();

		// Graba anita
		$Fondo = new Fondo();
		if ($articulos)
			$sku = $articulos->sku;
		else
			$sku = '';
        $Fondo->guardarAnita($request, $fondo->id, $sku, $fondo->codigo);

        return redirect('stock/fondo')->with('mensaje', 'Fondo creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-fondos');
        $data = Fondo::select('fondo.id', 'fondo.nombre', 'fondo.articulo_id', 'fondo.codigo',
						'articulo.descripcion as desc_articulo', 'articulo.sku as sku')->
					leftjoin('articulo', 'articulo.id', 'fondo.articulo_id')->findOrFail($id);
		$articulos = Articulo::select('id', 'sku', 'descripcion')->where('sku', 'like', '%FON%')->orderby('descripcion')->get();
        return view('stock.fondo.editar', compact('data', 'articulos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionFondo $request, $id)
    {
        can('actualizar-fondos');
        Fondo::findOrFail($id)->update($request->all());
		$articulos = Articulo::where('id', $request->articulo_id)->first();

		if (!$articulos)
		  $sku = ' ';
		else
		  $sku = $articulos->sku;

		// Actualiza anita
		$Fondo = new Fondo();
        $Fondo->actualizarAnita($request, $request->id, $sku, $request->codigo);

        return redirect('stock/fondo')->with('mensaje', 'Fondo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-fondos');

        $fondo = Fondo::where('id', $id)->first();

		// Elimina anita
		$Fondo = new Fondo();
        $Fondo->eliminarAnita($fondo->codigo);

        if ($request->ajax()) {
            if (Fondo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
