<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Monto_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMonto_Uif;
use App\Repositories\Uif\Monto_UifRepositoryInterface;

class Monto_UifController extends Controller
{
	private $repository;

    public function __construct(Monto_UifRepositoryInterface $repository)
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
        can('listar-monto-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Monto_Uif::$enumRiesgo;

        return view('uif.monto_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-monto-uif');

        $riesgo_enum = Monto_Uif::$enumRiesgo;

        return view('uif.monto_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMonto_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/monto_uif')->with('mensaje', 'Monto creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-monto-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Monto_Uif::$enumRiesgo;

        return view('uif.monto_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMonto_Uif $request, $id)
    {
        can('actualizar-monto-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/monto_uif')->with('mensaje', 'Monto actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-monto-uif');

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
