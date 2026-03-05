<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Tiposuspensionproveedor;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTiposuspensionproveedor;
use App\Repositories\Compras\TiposuspensionproveedorRepositoryInterface;

class TiposuspensionproveedorController extends Controller
{
	private $repository;

    public function __construct(TiposuspensionproveedorRepositoryInterface $repository)
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
        can('listar-tipos-suspension-proveedor');
		$datas = $this->repository->all();

        return view('compras.tiposuspensionproveedor.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipos-suspension-proveedor');

        return view('compras.tiposuspensionproveedor.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validaciontiposuspensionproveedor $request)
    {
		$this->repository->create($request->all());

        return redirect('compras/tiposuspensionproveedor')->with('mensaje', 'Tipo de suspensión de proveedor creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipos-suspension-proveedor');
        $data = $this->repository->findOrFail($id);

        return view('compras.tiposuspensionproveedor.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontiposuspensionproveedor $request, $id)
    {
        can('actualizar-tipos-suspension-proveedor');
        $this->repository->update($request->all(), $id);

        return redirect('compras/tiposuspensionproveedor')->with('mensaje', 'Tipo de suspensión de proveedor actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipos-suspension-proveedor');

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
