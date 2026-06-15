<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionModeloetiqueta;
use App\Repositories\Configuracion\ModeloetiquetaRepositoryInterface;
use App\Repositories\Configuracion\SeteoModeloetiquetaRepositoryInterface;
use App\Models\Configuracion\Modeloetiqueta;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Auth;

class ModeloetiquetaController extends Controller
{
	private $repository;
    private $seteoModeloetiquetaRepository;

    public function __construct(ModeloetiquetaRepositoryInterface $repository,
                                SeteoModeloetiquetaRepositoryInterface $seteoModeloetiquetaRepository)
    {
        $this->repository = $repository;
        $this->seteoModeloetiquetaRepository = $seteoModeloetiquetaRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-modeloetiqueta');
		$datas = $this->repository->all();

        return view('configuracion.modeloetiqueta.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-modeloetiqueta');
        $estado_enum = Modeloetiqueta::$enumEstado;

        return view('configuracion.modeloetiqueta.crear', compact('estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionModeloetiqueta $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/modeloetiqueta')->with('mensaje', 'Modelo de Etiqueta creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-modeloetiqueta');
        $data = $this->repository->findOrFail($id);
        $estado_enum = Modeloetiqueta::$enumEstado;

        return view('configuracion.modeloetiqueta.editar', compact('data', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionModeloetiqueta $request, $id)
    {
        can('actualizar-modeloetiqueta');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/modeloetiqueta')->with('mensaje', 'Modelo de Etiqueta actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-modeloetiqueta');

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

    public function actualizaEstado($estadomodeloetiqueta, $modeloetiqueta_id)
    {
        return $this->repository->update(['estado' => $estadomodeloetiqueta], $modeloetiqueta_id);
    }

    public function configurarModeloetiqueta(Request $request, $opcion = null)
    {
        $urlRetorno = $request->query('retorno', $request->server('HTTP_REFERER'));
        $programa = $this->seteoModeloetiquetaRepository->armaNombrePrograma($opcion);
        $programaEtiqueta = SeteoSalidaProgramaSupport::etiqueta($programa);
        $modeloetiquetas_query = $this->repository->all();
        $usuario_id = $request->session()->get('usuario_id');
        $seteomodeloetiqueta = $this->seteoModeloetiquetaRepository->buscaSeteoModeloetiqueta($usuario_id, $opcion);

        $datas['modeloetiqueta_id'] = $seteomodeloetiqueta?->modeloetiqueta_id ?? '';

        return view('configuracion.modeloetiqueta.configurar', compact(
            'datas',
            'modeloetiquetas_query',
            'programa',
            'programaEtiqueta',
            'urlRetorno'
        ));
    }

    public function setearModeloetiqueta(Request $request, $opcion, $modeloetiqueta_id)
    {
        $usuario_id = $request->session()->get('usuario_id');
        $programa = $this->seteoModeloetiquetaRepository->armaNombrePrograma($opcion);
        $seteomodeloetiqueta = $this->seteoModeloetiquetaRepository->leeSeteo($usuario_id, $programa);

        if ($seteomodeloetiqueta) {
            $seteomodeloetiqueta = $this->seteoModeloetiquetaRepository->update([
                'usuario_id' => $usuario_id,
                'modeloetiqueta_id' => $modeloetiqueta_id,
                'programa' => $programa,
            ], $seteomodeloetiqueta->id);
        } else {
            $seteomodeloetiqueta = $this->seteoModeloetiquetaRepository->create([
                'usuario_id' => $usuario_id,
                'modeloetiqueta_id' => $modeloetiqueta_id,
                'programa' => $programa,
            ]);
        }

        return ['retorno' => $seteomodeloetiqueta];
    }

    public function buscarModeloetiqueta(Request $request, $opcion = null)
    {
        $usuario_id = Auth()->id();
        $seteomodeloetiqueta = $this->seteoModeloetiquetaRepository->buscaSeteoModeloetiqueta($usuario_id, $opcion);

        if ($seteomodeloetiqueta) {
            return $seteomodeloetiqueta;
        }

        return ['id' => 999999, 'modeloetiquetas' => ['nombre' => 'Sin modelo de etiqueta seteado']];
    }
}
