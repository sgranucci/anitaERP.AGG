<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionEnvasesenasa;
use App\Repositories\Stock\EnvasesenasaRepositoryInterface;

class EnvasesenasaController extends Controller
{
	private $repository;

    public function __construct(EnvasesenasaRepositoryInterface $repository)
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
        can('listar-envase-senasa-stock');
		$datas = $this->repository->all();

        return view('stock.envasesenasa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-envase-senasa-stock');

        return view('stock.envasesenasa.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEnvasesenasa $request)
    {
		$this->repository->create($request->all());

        return redirect('stock/envasesenasa')->with('mensaje', 'Envase Senasa creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-envase-senasa-stock');
        $data = $this->repository->findOrFail($id);

        return view('stock.envasesenasa.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEnvasesenasa $request, $id)
    {
        can('actualizar-envase-senasa-stock');

        $this->repository->update($request->all(), $id);

        return redirect('stock/envasesenasa')->with('mensaje', 'Envase Senasa actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-envase-senasa-stock');

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
