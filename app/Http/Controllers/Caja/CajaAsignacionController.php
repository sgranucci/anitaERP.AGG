<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCaja_Asignacion;
use App\Repositories\Caja\Caja_AsignacionRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Seguridad\Usuario;

class CajaAsignacionController extends Controller
{
	private $repository;
    private $cajaRepository;
    private $empresaRepository;

    public function __construct(Caja_AsignacionRepositoryInterface $repository,
                                CajaRepositoryInterface $cajarepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->repository = $repository;
        $this->cajaRepository = $cajarepository;
        $this->empresaRepository = $empresarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-asignacion-caja');
		$datas = $this->repository->all();

        return view('caja.cajaasignacion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-asignacion-caja');

        $usuario_query = Usuario::orderBy('nombre')->get();
        $caja_query = $this->cajaRepository->all();
        $empresa_query = $this->empresaRepository->all();

        return view('caja.cajaasignacion.crear', compact('usuario_query', 'caja_query', 'empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCaja_Asignacion $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/cajaasignacion')->with('mensaje', 'Asignacion de caja creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-asignacion-caja');
        $data = $this->repository->findOrFail($id);
        $usuario_query = Usuario::orderBy('nombre')->get();
        $caja_query = $this->cajaRepository->all();
        $empresa_query = $this->empresaRepository->all();

        return view('caja.cajaasignacion.editar', compact('data', 'usuario_query', 'caja_query', 'empresa_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCaja_Asignacion $request, $id)
    {
        can('actualizar-asignacion-caja');

        $this->repository->update($request->all(), $id);

        return redirect('caja/cajaasignacion')->with('mensaje', 'Asignacion de caja actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-asignacion-caja');

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
