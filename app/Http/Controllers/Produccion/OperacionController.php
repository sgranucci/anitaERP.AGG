<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Produccion\Operacion;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOperacion;
use App\Repositories\Produccion\OperacionRepositoryInterface;

class OperacionController extends Controller
{
	private $repository;

    public function __construct(OperacionRepositoryInterface $repository)
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
        can('listar-operaciones');
		$datas = $this->repository->all();
		$tipooperacion_enum = Operacion::$enumTipoOperacion;

        return view('produccion.operacion.index', compact('datas', 'tipooperacion_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-operaciones');

		$tipooperacion_enum = Operacion::$enumTipoOperacion;

        return view('produccion.operacion.crear', compact('tipooperacion_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOperacion $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/operacion')->with('mensaje', 'Operacion creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-operaciones');
        $data = $this->repository->findOrFail($id);
        $tipooperacion_enum = Operacion::$enumTipoOperacion;

        return view('produccion.operacion.editar', compact('data', 'tipooperacion_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOperacion $request, $id)
    {
        can('actualizar-operaciones');
        $this->repository->update($request->all(), $id);

        return redirect('produccion/operacion')->with('mensaje', 'Operacion actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-operaciones');

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
