<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Tiposuspensioncliente;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTiposuspensioncliente;
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;

class TiposuspensionclienteController extends Controller
{
	private $repository;

    public function __construct(TiposuspensionclienteRepositoryInterface $repository)
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
        can('listar-tipos-suspension-clientes');
		$datas = $this->repository->all();

        return view('ventas.tiposuspensioncliente.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipos-suspension-clientes');

        return view('ventas.tiposuspensioncliente.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validaciontiposuspensioncliente $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/tiposuspensioncliente')->with('mensaje', 'Tipo de suspensión de cliente creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipos-suspension-clientes');
        $data = $this->repository->findOrFail($id);

        return view('ventas.tiposuspensioncliente.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontiposuspensioncliente $request, $id)
    {
        can('actualizar-tipos-suspension-clientes');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/tiposuspensioncliente')->with('mensaje', 'Tipo de suspensión de cliente actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipos-suspension-clientes');

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
