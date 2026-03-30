<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTiposervicio_Proveedor;
use App\Repositories\Compras\Tiposervicio_ProveedorRepositoryInterface;

class Tiposervicio_ProveedorController extends Controller
{
	private $repository;

    public function __construct(Tiposervicio_ProveedorRepositoryInterface $repository)
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
        can('listar-tipo-servicio-proveedor');
		$datas = $this->repository->all();

        return view('compras.tiposervicio_proveedor.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-servicio-proveedor');

        return view('compras.tiposervicio_proveedor.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTiposervicio_Proveedor $request)
    {
		$this->repository->create($request->all());

        return redirect('compras/tiposervicio_proveedor')->with('mensaje', 'Tipo de servicio de proveedor creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-servicio-proveedor');
        $data = $this->repository->findOrFail($id);

        return view('compras.tiposervicio_proveedor.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTiposervicio_Proveedor $request, $id)
    {
        can('actualizar-tipo-servicio-proveedor');

        $this->repository->update($request->all(), $id);

        return redirect('compras/tiposervicio_proveedor')->with('mensaje', 'Tipo de servicio de proveedor actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-servicio-proveedor');

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
