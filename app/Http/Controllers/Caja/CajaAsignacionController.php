<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCaja_Asignacion;
use App\Repositories\Caja\Caja_AsignacionRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

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
    public function index(Request $request)
    {
        can('lista-asignacion-caja');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresa_query->count() === 1) {
            $empresaId = (int) $empresa_query->first()->id;
        }

        $datas = $this->repository->all(null, $empresaId > 0 ? $empresaId : null);

        return view('caja.cajaasignacion.index', compact('datas', 'empresa_query', 'empresaId'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crea-asignacion-caja');

        $caja_query = $this->cajaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.cajaasignacion.crear', compact('caja_query', 'empresa_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCaja_Asignacion $request)
    {
        can('crea-asignacion-caja');

		$this->repository->create($request->only(['fecha', 'empresa_id', 'caja_id', 'usuario_id']));

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
        can('edita-asignacion-caja');
        $data = $this->repository->findOrFail($id);
        $data->loadMissing('usuarios');
        $caja_query = $this->cajaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('caja.cajaasignacion.editar', compact('data', 'caja_query', 'empresa_query'));
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
        can('actualiza-asignacion-caja');

        $this->repository->update($request->only(['fecha', 'empresa_id', 'caja_id', 'usuario_id']), $id);

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
        can('borra-asignacion-caja');

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
