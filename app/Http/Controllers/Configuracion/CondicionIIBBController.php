<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\CondicionIIBB;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCondicionIIBB;
use App\Repositories\Configuracion\CondicionIIBBRepositoryInterface;

class CondicionIIBBController extends Controller
{
	private $repository;

    public function __construct(CondicionIIBBRepositoryInterface $repository)
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
        can('listar-condicion-de-ingreso-bruto');
		$datas = $this->repository->all();

		$estado_enum = CondicionIIBB::$enumEstado;
		$formaCalculo_enum = CondicionIIBB::$enumFormaCalculo;

        return view('configuracion.condicionIIBB.index', compact('datas', 'estado_enum', 'formaCalculo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-condicion-de-ingreso-bruto');

        $estado_enum = CondicionIIBB::$enumEstado;
		$formaCalculo_enum = CondicionIIBB::$enumFormaCalculo;

        return view('configuracion.condicionIIBB.crear', compact('estado_enum', 'formaCalculo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCondicionIIBB $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/condicionIIBB')->with('mensaje', 'Condicion de IIBB creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-condicion-de-ingreso-bruto');
        $data = $this->repository->findOrFail($id);

        $estado_enum = CondicionIIBB::$enumEstado;
		$formaCalculo_enum = CondicionIIBB::$enumFormaCalculo;

        return view('configuracion.condicionIIBB.editar', compact('data', 'estado_enum', 'formaCalculo_enum'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCondicionIIBB $request, $id)
    {
        can('actualizar-condicion-de-ingreso-bruto');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/condicionIIBB')->with('mensaje', 'Condicion de IIBB actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-condicion-de-ingreso-bruto');

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
