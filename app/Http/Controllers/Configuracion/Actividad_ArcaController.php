<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionActividad_Arca;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;

class Actividad_ArcaController extends Controller
{
	private $repository;

    public function __construct(Actividad_ArcaRepositoryInterface $repository)
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
        can('listar-actividad-arca');
		$datas = $this->repository->all();

        return view('configuracion.actividad_arca.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-actividad-arca');

        return view('configuracion.actividad_arca.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionActividad_Arca $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/actividad_arca')->with('mensaje', 'Actividad Arca creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-actividad-arca');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.actividad_arca.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionActividad_Arca $request, $id)
    {
        can('actualizar-actividad-arca');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/actividad_arca')->with('mensaje', 'Actividad Arca actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-actividad-arca');

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
