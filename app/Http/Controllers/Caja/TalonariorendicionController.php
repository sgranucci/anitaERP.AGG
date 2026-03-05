<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Talonariorendicion;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTalonariorendicion;
use App\Repositories\Caja\TalonariorendicionRepositoryInterface;

class TalonariorendicionController extends Controller
{
	private $repository;
    private $origenrendicionRepository;

    public function __construct(TalonariorendicionRepositoryInterface $repository)
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
        can('listar-talonario-de-rendicion');
		$datas = $this->repository->all();

        return view('caja.talonariorendicion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-talonario-de-rendicion');

        $estado_enum = Talonariorendicion::$enumEstado;

        return view('caja.talonariorendicion.crear', compact('estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTalonariorendicion $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/talonariorendicion')->with('mensaje', 'Talonario de rendición creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-talonario-de-rendicion');
        $data = $this->repository->findOrFail($id);

        $estado_enum = Talonariorendicion::$enumEstado;

        return view('caja.talonariorendicion.editar', compact('data', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTalonariorendicion $request, $id)
    {
        can('actualizar-talonario-de-rendicion');

        $this->repository->update($request->all(), $id);

        return redirect('caja/talonariorendicion')->with('mensaje', 'Talonario de rendición actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-talonario-de-rendicion');

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
