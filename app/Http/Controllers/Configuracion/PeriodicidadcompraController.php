<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPeriodicidadcompra;
use App\Repositories\Configuracion\PeriodicidadcompraRepositoryInterface;

class PeriodicidadcompraController extends Controller
{
	private $repository;

    public function __construct(PeriodicidadcompraRepositoryInterface $repository)
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
        can('listar-periodicidad-de-compra');
		$datas = $this->repository->all();

        return view('configuracion.periodicidadcompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-periodicidad-de-compra');

        return view('configuracion.periodicidadcompra.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPeriodicidadcompra $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/periodicidadcompra')->with('mensaje', 'Periodicidad de compras creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-periodicidad-de-compra');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.periodicidadcompra.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPeriodicidadcompra $request, $id)
    {
        can('actualizar-periodicidad-de-compra');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/periodicidadcompra')->with('mensaje', 'Periodicidad de compras actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-periodicidad-de-compra');

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
