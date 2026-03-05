<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Sala;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSala;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class SalaController extends Controller
{
	private $repository;
    private $empresaRepository;

    public function __construct(SalaRepositoryInterface $repository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->repository = $repository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-sala');
		$datas = $this->repository->all();

        return view('configuracion.sala.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-sala');
        $empresa_query = $this->empresaRepository->all();

        return view('configuracion.sala.crear', compact('empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSala $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/sala')->with('mensaje', 'Sala creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-sala');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->all();

        return view('configuracion.sala.editar', compact('data', 'empresa_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSala $request, $id)
    {
        can('actualizar-sala');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/sala')->with('mensaje', 'Sala actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-sala');

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
