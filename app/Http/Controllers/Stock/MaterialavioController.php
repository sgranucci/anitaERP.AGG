<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Materialavio;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMaterialavio;
use App\Repositories\Stock\MaterialavioRepositoryInterface;

class MaterialavioController extends Controller
{
	private $repository;

    public function __construct(MaterialavioRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-avios');
		$datas = $this->repository->all();

        return view('stock.materialavio.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-avios');

        $articulo_query = Articulo::orderBy('descripcion')->where('usoarticulo_id','3')->get();

        return view('stock.materialavio.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMaterialavio $request)
    {
		$this->repository->create($request->all());

        return redirect('stock/materialavio')->with('mensaje', 'Avio creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-avios');
        $data = $this->repository->findOrFail($id);

        $articulo_query = Articulo::orderBy('descripcion')->where('usoarticulo_id','3')->get();

        return view('stock.materialavio.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMaterialavio $request, $id)
    {
        can('actualizar-avios');
        $this->repository->update($request->all(), $id);

        return redirect('stock/materialavio')->with('mensaje', 'Avio actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-avios');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
