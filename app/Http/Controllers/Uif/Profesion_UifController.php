<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Profesion_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionProfesion_Uif;
use App\Repositories\Uif\Profesion_UifRepositoryInterface;

class Profesion_UifController extends Controller
{
	private $repository;

    public function __construct(Profesion_UifRepositoryInterface $repository)
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
        can('listar-profesion-uif');
		$datas = $this->repository->all();

        if ($datas->isEmpty())
		{
        	$this->repository->sincronizarConAnita();
	
            $datas = $this->repository->all();
		}

        return view('uif.profesion_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-profesion-uif');

        return view('uif.profesion_uif.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProfesion_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/profesion_uif')->with('mensaje', 'Profesion creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-profesion-uif');
        $data = $this->repository->findOrFail($id);

        return view('uif.profesion_uif.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProfesion_Uif $request, $id)
    {
        can('actualizar-profesion-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/profesion_uif')->with('mensaje', 'Profesion actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-profesion-uif');

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

    public function consultaProfesion_Uif(Request $request)
    {
        return ($this->repository->leeProfesion_Uif($request->consulta));
	}

    public function leeProfesion_Uif($profesion_uif_id)
    {
        return ($this->repository->find($profesion_uif_id));
	}
    
}
