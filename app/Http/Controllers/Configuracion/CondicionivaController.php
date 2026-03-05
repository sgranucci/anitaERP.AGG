<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCondicioniva;
use App\Repositories\Configuracion\RepositoryInterface;

class CondicionivaController extends Controller
{
	private $repository;

    public function __construct(RepositoryInterface $repository)
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
        can('listar-condiciones-de-iva');
		$datas = $this->repository->all();

		$letras = Condicioniva::$enumLetra;
		$conivas = Condicioniva::$enumIva;
		$coniibbs = Condicioniva::$enumIibb;

        return view('configuracion.condicioniva.index', compact('datas', 'letras', 'conivas', 'coniibbs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-condiciones-de-iva');

		$letras = Condicioniva::$enumLetra;
		$conivas = Condicioniva::$enumIva;
		$coniibbs = Condicioniva::$enumIibb;

        return view('configuracion.condicioniva.crear', compact('letras', 'conivas', 'coniibbs'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCondicioniva $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/condicioniva')->with('mensaje', 'Condicion de iva creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-condiciones-de-iva');
        $data = $this->repository->findOrFail($id);

		$letras = Condicioniva::$enumLetra;
		$conivas = Condicioniva::$enumIva;
		$coniibbs = Condicioniva::$enumIibb;

        return view('configuracion.condicioniva.editar', compact('data', 'letras', 'conivas', 'coniibbs'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCondicioniva $request, $id)
    {
        can('actualizar-condiciones-de-iva');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/condicioniva')->with('mensaje', 'Condicion de iva actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-condiciones-de-iva');

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
