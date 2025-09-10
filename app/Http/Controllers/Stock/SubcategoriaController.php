<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Subcategoria;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSubcategoria;

class SubcategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-subcategorias');
        $datas = Subcategoria::orderBy('id')->get();

		//if ($datas->isEmpty())
		//{
		//	$Subcategoria = new Subcategoria();
        //	$Subcategoria->sincronizarConAnita();
	
        //	$datas = Subcategoria::orderBy('id')->get();
		//}

        return view('stock.subcategoria.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-subcategorias');
        return view('stock.subcategoria.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSubcategoria $request)
    {
        $subcategoria = Subcategoria::create($request->all());

		// Graba anita
		$Subcategoria = new Subcategoria();
        $Subcategoria->guardarAnita($request, $subcategoria->id);

        return redirect('stock/subcategoria')->with('mensaje', 'Subcategoria creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-subcategorias');
        $data = Subcategoria::findOrFail($id);
        return view('stock.subcategoria.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSubcategoria $request, $id)
    {
        can('actualizar-subcategorias');
        Subcategoria::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Subcategoria = new Subcategoria();
        $Subcategoria->actualizarAnita($request, $id);

        return redirect('stock/subcategoria')->with('mensaje', 'Subcategoria actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-subcategorias');

		// Elimina anita
		$Subcategoria = new Subcategoria();
        $Subcategoria->eliminarAnita($request->codigo);

        if ($request->ajax()) {
            if (Subcategoria::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
