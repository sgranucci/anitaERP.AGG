<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Juego_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionJuego_Uif;
use App\Repositories\Uif\Juego_UifRepositoryInterface;

class Juego_UifController extends Controller
{
	private $repository;

    public function __construct(Juego_UifRepositoryInterface $repository)
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
        can('listar-juego-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Juego_Uif::$enumRiesgo;

        return view('uif.juego_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-juego-uif');

        $riesgo_enum = Juego_Uif::$enumRiesgo;

        return view('uif.juego_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionJuego_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/juego_uif')->with('mensaje', 'Juego creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-juego-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Juego_Uif::$enumRiesgo;

        return view('uif.juego_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionJuego_Uif $request, $id)
    {
        can('actualizar-juego-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/juego_uif')->with('mensaje', 'Juego actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-juego-uif');

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
