<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Material;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMaterial;

class MaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-materiales');
        $datas = Material::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Material = new Material();
        	$Material->sincronizarConAnita();
	
        	$datas = Material::orderBy('id')->get();
		}

        return view('stock.material.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-materiales');
        return view('stock.material.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMaterial $request)
    {
        $material = Material::create($request->all());

		// Graba anita
		$Material = new Material();
        $Material->guardarAnita($request);

        return redirect('stock/material')->with('mensaje', 'Material creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-materiales');
        $data = Material::findOrFail($id);
        return view('stock.material.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMaterial $request, $id)
    {
        can('actualizar-materiales');
        Material::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Material = new Material();
        $Material->actualizarAnita($request);

        return redirect('stock/material')->with('mensaje', 'Material actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-materiales');

        $material = Material::findOrFail($id);

		// Elimina anita
		$Material = new Material();
        $Material->eliminarAnita($material->codigo);

        if ($request->ajax()) {
            if (Material::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
