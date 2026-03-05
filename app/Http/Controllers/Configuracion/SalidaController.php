<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSalida;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use Illuminate\Support\Str;

class SalidaController extends Controller
{
    private $repository;
    private $seteosalidaRepository;

    public function __construct(SalidaRepositoryInterface $salidarepository,
                                SeteosalidaRepositoryInterface $seteosalidarepository)
    {
        $this->repository = $salidarepository;
        $this->seteosalidaRepository = $seteosalidarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-salidas');
        $datas = $this->repository->all();

		return view('configuracion.salida.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-salidas');
        return view('configuracion.salida.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSalida $request)
    {
        $salida = $this->repository->create($request->all());

        return redirect('configuracion/salida')->with('mensaje', 'Salida creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-salidas');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.salida.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSalida $request, $id)
    {
        can('actualizar-salidas');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/salida')->with('mensaje', 'Salida actualizada con exito');
    }

        /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function configurarSalida(Request $request, $opcion=null)
    {
        //can('configurar-salidas');

        // Agrega programa enviado a la url completa
        $urlRetorno = $request->server('HTTP_REFERER');
        $programa = $this->seteosalidaRepository->armaNombrePrograma($opcion);

        // Extrae programa para retornar desde la URL completa
        $string = explode('/',$urlRetorno);
        $pgmretorno = $string[count($string)-1];
        $salidas_query = $this->repository->all();

        // Lee configuracion de salida
        $usuario_id = $request->session()->get('usuario_id');
        
        // Busca configuracion
        $seteosalida = $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

        if ($seteosalida)
            $datas['salida_id'] = $seteosalida->salida_id;
        else
            $datas['salida_id'] = 1;
        return view('configuracion.salida.configurar', compact('datas', 'salidas_query', 'programa', 'pgmretorno'));
    }

    public function setearSalida(Request $request, $opcion, $salida_id)
    {
        $usuario_id = $request->session()->get('usuario_id');

        // Busca configuracion pre-grabada
        $seteosalida = $this->seteosalidaRepository->leeSeteo($usuario_id, $opcion);

        // Graba configuracion
        if ($seteosalida)
        {
            $programa = $seteosalida->programa;
            $seteosalida = $this->seteosalidaRepository->update(['usuario_id' => $usuario_id, 
                                                                'salida_id' => $salida_id,
                                                                'programa' => $programa], 
                                                                $seteosalida->id);
        }
        else
        {
            $programa = $opcion;

            $seteosalida = $this->seteosalidaRepository->create(['usuario_id' => $usuario_id, 
                                                                'salida_id' => $salida_id,
                                                                'programa' => $programa]);
        }
        return ['retorno' => $seteosalida];
    }

    public function buscarSalida(Request $request, $opcion = null)
    {
        $usuario_id = $request->session()->get('usuario_id');

        // Busca configuracion
        $seteosalida = $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

        if ($seteosalida)        
            return $seteosalida;
        else    
            return ['id' => 999999, 'salidas' => ['nombre' => 'Sin impresora seteada']];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-salidas');

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
