<?php

namespace App\Http\Controllers\Stock;

use App\Http\Requests\ValidacionCategoria;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Categoria;
use Illuminate\Support\Facades\Storage;
use App\Models\Stock\Tipoarticulo;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-categorias');
        $datas = Categoria::with('tipoarticulo:id,nombre')->get();

		if ($datas->isEmpty())
		{
			$Categoria = new Categoria();
        	$Categoria->sincronizarConAnita();
	
        	$datas = Categoria::with('tipoarticulo:id,nombre')->get();
		}

        return view('stock.categoria.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-categorias');
		$tipoarticulos = Tipoarticulo::all();

        return view('stock.categoria.crear', compact('tipoarticulos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCategoria $request)
    {
        $categoria = Categoria::create($request->all());

		// Graba anita
		$Categoria = new Categoria();
        $Categoria->guardarAnita($request, $categoria->id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-categorias');
		$tipoarticulos = Tipoarticulo::all();

        $data = Categoria::findOrFail($id);

        return view('stock.categoria.editar', compact('data', 'tipoarticulos'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCategoria $request, $id)
    {
        can('actualizar-categorias');
        Categoria::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Categoria = new Categoria();
        $Categoria->actualizarAnita($request, $id);

        return redirect('stock/categoria')->with('mensaje', 'Categoria actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-categorias');

		// Elimina anita
		$Categoria = new Categoria();
        $Categoria->eliminarAnita($id);

        if ($request->ajax()) {
            if (Categoria::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
