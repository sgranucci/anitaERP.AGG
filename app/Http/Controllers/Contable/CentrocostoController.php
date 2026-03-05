<?php

namespace App\Http\Controllers\Contable;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCentrocosto;
use App\Repositories\Contable\CentrocostoRepositoryInterface;

class CentrocostoController extends Controller
{
	private $repository;

    public function __construct(CentrocostoRepositoryInterface $repository)
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
        can('listar-centro-costo');
		$datas = $this->repository->all();

        return view('contable.centrocosto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-centro-costo');
        $tipoiva_enum = Centrocosto::$enumTipoIva;

        return view('contable.centrocosto.crear', compact('tipoiva_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCentrocosto $request)
    {
		$this->repository->create($request->all());

        return redirect('contable/centrocosto')->with('mensaje', 'Centro de costo creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-centro-costo');
        $data = $this->repository->findOrFail($id);
        $tipoiva_enum = Centrocosto::$enumTipoIva;

        return view('contable.centrocosto.editar', compact('data', 'tipoiva_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCentrocosto $request, $id)
    {
        can('actualizar-centro-costo');

        $this->repository->update($request->all(), $id);

        return redirect('contable/centrocosto')->with('mensaje', 'Centro de costo actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-centro-costo');

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
