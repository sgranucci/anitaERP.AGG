<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEncuesta;
use App\Repositories\Compras\EncuestaRepositoryInterface;
use App\Repositories\Compras\Encuesta_PreguntaRepositoryInterface;
use App\Models\Compras\Encuesta;
use App\Models\Compras\Encuesta_Pregunta;
use App\Services\Compras\EncuestaService;
use Illuminate\Http\Request;
use DB;
use Exception;

class EncuestaController extends Controller
{
	private $encuestaRepository;
    private $encuesta_preguntaRepository;
    private $encuestaService;

	public function __construct(EncuestaRepositoryInterface $encuestarepository,
                                Encuesta_PreguntaRepositoryInterface $encuesta_preguntarepository,
                                EncuestaService $encuestaservice)
    {
        $this->encuestaRepository = $encuestarepository;
        $this->encuesta_preguntaRepository = $encuesta_preguntarepository;
        $this->encuestaService = $encuestaservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-encuesta');
		
		$datas = $this->encuestaRepository->all();

        return view('compras.encuesta.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-encuesta');
        $estado_enum = Encuesta::$enumEstado;

        return view('compras.encuesta.crear', compact('estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEncuesta $request)
    {
        DB::beginTransaction();
        try
        {
            $encuesta = $this->encuestaRepository->create($request->all());

            if ($encuesta == 'Error')
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($encuesta)
                $encuesta_pregunta = $this->encuesta_preguntaRepository->create($request->all(), $encuesta->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
    	return redirect('compras/encuesta')->with('mensaje', 'Encuesta creada con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-encuesta');

		$data = $this->encuestaRepository->find($id);
        $estado_enum = Encuesta::$enumEstado;

        return view('compras.encuesta.editar', compact('data', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEncuesta $request, $id)
    {
        can('actualizar-encuesta');

        DB::beginTransaction();
        try
        {
            $encuesta = $this->encuestaRepository->update($request->all(), $id);

            if (!$encuesta)
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($encuesta)
                $encuesta_pregunta = $this->encuesta_preguntaRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
		return redirect('compras/encuesta')->with('mensaje', 'Encuesta actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-encuesta');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->encuestaRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
