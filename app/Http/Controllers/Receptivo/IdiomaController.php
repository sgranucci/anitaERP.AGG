<?php

namespace App\Http\Controllers\Receptivo;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Receptivo\Idioma;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionIdioma;
use App\Repositories\Receptivo\IdiomaRepositoryInterface;

class IdiomaController extends Controller
{
	private $repository;

    public function __construct(IdiomaRepositoryInterface $repository)
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
        can('listar-idioma');
		$datas = $this->repository->all();

        return view('receptivo.idioma.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-idioma');

        return view('receptivo.idioma.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionIdioma $request)
    {
		$this->repository->create($request->all());

        return redirect('receptivo/idioma')->with('mensaje', 'Idioma creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-idioma');
        $data = $this->repository->findOrFail($id);

        return view('receptivo.idioma.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validacionidioma $request, $id)
    {
        can('actualizar-idioma');

        $this->repository->update($request->all(), $id);

        return redirect('receptivo/idioma')->with('mensaje', 'Idioma actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-idioma');

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
