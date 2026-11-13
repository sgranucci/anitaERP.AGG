<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLocalidad_Uif;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;

class Localidad_UifController extends Controller
{
	private $repository;
    private $provincia_uifRepository;

    public function __construct(
        Localidad_UifRepositoryInterface $repository,
        Provincia_UifRepositoryInterface $provincia_uifRepository
    ) {
        $this->repository = $repository;
        $this->provincia_uifRepository = $provincia_uifRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-localidad-uif');
		$datas = $this->repository->all();

        if ($datas->isEmpty())
		{
        	$this->repository->sincronizarConAnita();
	
            $datas = $this->repository->all();
		}

        $datas = $datas->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('uif.localidad_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-localidad-uif');

        $provincia_uif_query = $this->provincia_uifRepository->all()->sortBy('nombre')->values();
        $codigoSugerido = $this->repository->proximoCodigo();

        return view('uif.localidad_uif.crear', compact('provincia_uif_query', 'codigoSugerido'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionLocalidad_Uif $request)
    {
        $localidad = $this->repository->create($request->validated());

        return redirect('uif/localidad_uif')->with(
            'mensaje',
            'Localidad creada con éxito (código '.$localidad->codigo.')'
        );
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-localidad-uif');
        $data = $this->repository->findOrFail($id);
        $provincia_uif_query = $this->provincia_uifRepository->all()->sortBy('nombre')->values();

        return view('uif.localidad_uif.editar', compact('data', 'provincia_uif_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionLocalidad_Uif $request, $id)
    {
        can('actualizar-localidad-uif');

        $this->repository->update($request->validated(), $id);

        return redirect('uif/localidad_uif')->with('mensaje', 'Localidad actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-localidad-uif');

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

    public function consultaLocalidad_Uif(Request $request)
    {
        return ($this->repository->leeLocalidad_Uif($request->consulta));
	}

    public function leeLocalidad_Uif($localidad_uif_id)
    {
        return ($this->repository->find($localidad_uif_id));
	}
    
	public function leerLocalidades($id)
    {
        return $this->repository->leerLocalidades($id);
    }

    public function leerCodigoPostal($id)
    {
        $cp = $this->repository->leerCodigoPostal($id);
        
        return $cp;
    }

}
