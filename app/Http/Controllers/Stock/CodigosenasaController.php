<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Stock\Codigosenasa;
use App\Http\Requests\ValidacionCodigosenasa;
use App\Repositories\Stock\CodigosenasaRepositoryInterface;
use App\Repositories\Stock\EnvasesenasaRepositoryInterface;

class CodigosenasaController extends Controller
{
	private $repository;
    private $envasesenasaRepository;

    public function __construct(CodigosenasaRepositoryInterface $repository,
                                EnvasesenasaRepositoryInterface $envasesenasarepository)
    {
        $this->repository = $repository;
        $this->envasesenasaRepository = $envasesenasarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-codigo-senasa-stock');
		$datas = $this->repository->all();

        return view('stock.codigosenasa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-codigo-senasa-stock');
        $envasesenasa_query = $this->envasesenasaRepository->all();
        $llevafrio_enum = Codigosenasa::$enumLlevaFrio;

        return view('stock.codigosenasa.crear', compact('envasesenasa_query', 'llevafrio_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCodigosenasa $request)
    {
		$this->repository->create($request->all());

        return redirect('stock/codigosenasa')->with('mensaje', 'Código Senasa creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-codigo-senasa-stock');
        $data = $this->repository->findOrFail($id);
        $envasesenasa_query = $this->envasesenasaRepository->all();
        $llevafrio_enum = Codigosenasa::$enumLlevaFrio;

        return view('stock.codigosenasa.editar', compact('data', 'envasesenasa_query', 'llevafrio_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCodigosenasa $request, $id)
    {
        can('actualizar-codigo-senasa-stock');

        $this->repository->update($request->all(), $id);

        return redirect('stock/codigosenasa')->with('mensaje', 'Código Senasa actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-codigo-senasa-stock');

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
