<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Color;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionColor;
use DataTables;

class ColorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-colores');

        $datas = Color::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Color = new Color();
        	$Color->sincronizarConAnita();
	
        	$datas = Color::orderBy('id')->paginate(50);
		}  

        return view('stock.color.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-colores');
        return view('stock.color.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionColor $request)
    {
        $color = Color::create($request->all());

		// Graba anita
		$Color = new Color();
        $Color->guardarAnita($request);

        return redirect('stock/color')->with('mensaje', 'Color creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-colores');
        $data = Color::findOrFail($id);
        return view('stock.color.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionColor $request, $id)
    {
        can('actualizar-colores');
        Color::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Color = new Color();
        $Color->actualizarAnita($request);

        return redirect('stock/color')->with('mensaje', 'Color actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-colores');

		// Elimina anita
		$Color = new Color();
        $Color->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Color::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
