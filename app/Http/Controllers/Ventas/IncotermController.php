<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Incoterm;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionIncoterm;
use App\Repositories\Ventas\IncotermRepositoryInterface;

class IncotermController extends Controller
{
	private $repository;

    public function __construct(IncotermRepositoryInterface $repository)
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
        can('listar-incoterms');
		$datas = $this->repository->all();

        return view('ventas.incoterm.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-incoterms');

        return view('ventas.incoterm.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionIncoterm $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/incoterm')->with('mensaje', 'Incoterm creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-incoterms');
        $data = $this->repository->findOrFail($id);

        return view('ventas.incoterm.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionIncoterm $request, $id)
    {
        can('actualizar-incoterms');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/incoterm')->with('mensaje', 'Incoterm actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-incoterms');

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
