<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Materialcapellada;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMaterialcapellada;
use App\Repositories\Stock\MaterialcapelladaRepositoryInterface;

class MaterialcapelladaController extends Controller
{
	private $repository;

    public function __construct(MaterialcapelladaRepositoryInterface $repository)
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
        can('listar-capelladas');
		$datas = $this->repository->all();

        return view('stock.materialcapellada.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-capelladas');

        $articulo_query = Articulo::orderBy('descripcion')->where('usoarticulo_id','3')->get();

        return view('stock.materialcapellada.crear', compact('articulo_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMaterialcapellada $request)
    {
		$this->repository->create($request->all());

        return redirect('stock/materialcapellada')->with('mensaje', 'Capellada creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-capelladas');
        $data = $this->repository->findOrFail($id);

        $articulo_query = Articulo::orderBy('descripcion')->where('usoarticulo_id','3')->get();

        return view('stock.materialcapellada.editar', compact('data', 'articulo_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMaterialcapellada $request, $id)
    {
        can('actualizar-capelladas');
        $this->repository->update($request->all(), $id);

        return redirect('stock/materialcapellada')->with('mensaje', 'Capellada actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-capelladas');

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
