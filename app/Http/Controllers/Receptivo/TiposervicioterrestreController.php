<?php

namespace App\Http\Controllers\Receptivo;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Receptivo\Tiposervicioterrestre;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTiposervicioterrestre;
use App\Repositories\Receptivo\TiposervicioterrestreRepositoryInterface;

class TiposervicioterrestreController extends Controller
{
	private $repository;

    public function __construct(TiposervicioterrestreRepositoryInterface $repository)
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
        can('listar-tipo-servicio-terrestre');
		$datas = $this->repository->all();

        return view('receptivo.tiposervicioterrestre.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-servicio-terrestre');

        return view('receptivo.tiposervicioterrestre.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTiposervicioterrestre $request)
    {
		$this->repository->create($request->all());

        return redirect('receptivo/tiposervicioterrestre')->with('mensaje', 'Tipo de servicio terrestre creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-servicio-terrestre');
        $data = $this->repository->findOrFail($id);

        return view('receptivo.tiposervicioterrestre.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontiposervicioterrestre $request, $id)
    {
        can('actualizar-tipo-servicio-terrestre');

        $this->repository->update($request->all(), $id);

        return redirect('receptivo/tiposervicioterrestre')->with('mensaje', 'Tipo de servicio terrestre actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-servicio-terrestre');

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
