<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Salida;
use App\Http\Requests\ValidacionSalida;
use App\Repositories\Configuracion\SalidaRepositoryInterface;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Repositories\Configuracion\UbicacionImpresoraRepositoryInterface;
use App\Repositories\Configuracion\UsoSalidaImpresoraRepositoryInterface;
use App\Support\Configuracion\SalidaParaProgramaSupport;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class SalidaController extends Controller
{
    private $repository;
    private $seteosalidaRepository;
    private $ubicacionImpresoraRepository;
    private $usoSalidaImpresoraRepository;

    public function __construct(SalidaRepositoryInterface $salidarepository,
                                SeteosalidaRepositoryInterface $seteosalidarepository,
                                UbicacionImpresoraRepositoryInterface $ubicacionImpresoraRepository,
                                UsoSalidaImpresoraRepositoryInterface $usoSalidaImpresoraRepository)
    {
        $this->repository = $salidarepository;
        $this->seteosalidaRepository = $seteosalidarepository;
        $this->ubicacionImpresoraRepository = $ubicacionImpresoraRepository;
        $this->usoSalidaImpresoraRepository = $usoSalidaImpresoraRepository;
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
        $data = new Salida();
        $ubicacion_impresora_query = $this->ubicacionImpresoraRepository->all();
        $uso_salida_impresora_query = $this->usoSalidaImpresoraRepository->all();

        return view('configuracion.salida.crear', compact('data', 'ubicacion_impresora_query', 'uso_salida_impresora_query'));
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
        $ubicacion_impresora_query = $this->ubicacionImpresoraRepository->all();
        $uso_salida_impresora_query = $this->usoSalidaImpresoraRepository->all();

        return view('configuracion.salida.editar', compact('data', 'ubicacion_impresora_query', 'uso_salida_impresora_query'));
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

        $urlRetorno = $request->input('vista') === 'consulta' ? '' : $this->urlRetornoLocal($request);
        $programa = $this->seteosalidaRepository->armaNombrePrograma($opcion);
        $programaEtiqueta = SeteoSalidaProgramaSupport::etiqueta($programa);

        $usuario_id = $request->session()->get('usuario_id');

        $seteosalida = $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

        $datas['salida_id'] = $seteosalida?->salida_id ?? '';
        $datas['disparar_al_grabar'] = (bool) ($seteosalida?->disparar_al_grabar ?? false);

        $salidas_query = $this->repository->paraProgramaSeteo(
            $programa,
            $datas['salida_id'] ? (int) $datas['salida_id'] : null
        );

        return view('configuracion.salida.configurar', compact(
            'datas',
            'salidas_query',
            'programa',
            'programaEtiqueta',
            'urlRetorno'
        ));
    }

    public function setearSalida(Request $request, $opcion, $salida_id)
    {
        $usuario_id = $request->session()->get('usuario_id');
        $programa = $this->seteosalidaRepository->armaNombrePrograma($opcion);

        if (! SalidaParaProgramaSupport::salidaPermitidaParaPrograma($programa, (int) $salida_id)) {
            abort(422, 'La impresora seleccionada no está habilitada para este programa.');
        }

        $seteosalida = $this->seteosalidaRepository->leeSeteo($usuario_id, $programa);

        $payload = [
            'usuario_id' => $usuario_id,
            'salida_id' => $salida_id,
            'programa' => $programa,
        ];
        if ($request->has('disparar_al_grabar')) {
            $payload['disparar_al_grabar'] = $request->boolean('disparar_al_grabar');
        }

        if ($seteosalida) {
            $seteosalida = $this->seteosalidaRepository->update($payload, $seteosalida->id);
        } else {
            $seteosalida = $this->seteosalidaRepository->create($payload);
        }

        return ['retorno' => $seteosalida];
    }

    public function buscarSalida(Request $request, $opcion = null)
    {
        $usuario_id = $request->session()->get('usuario_id');

        $seteosalida = $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

        $vendedor = Auth::user()->vendedor_id;
        if ($vendedor && ! $seteosalida) {
            $impresoraDefault = config('pedido.impresora_default');
            if ($impresoraDefault) {
                return ['id' => 999999, 'salidas' => ['nombre' => $impresoraDefault, 'ubicacion' => '']];
            }
        }

        if ($seteosalida) {
            return $seteosalida;
        }

        return ['id' => 999999, 'salidas' => ['nombre' => 'Sin impresora seteada', 'ubicacion' => '']];
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

    private function urlRetornoLocal(Request $request): string
    {
        $candidata = (string) $request->query('retorno', $request->server('HTTP_REFERER', ''));
        if ($candidata === '' || Str::startsWith(strtolower($candidata), ['javascript:', 'data:'])) {
            return '';
        }

        if (Str::startsWith($candidata, '/') && ! Str::startsWith($candidata, '//')) {
            return $candidata;
        }

        $hostApp = parse_url((string) config('app.url'), PHP_URL_HOST);
        $hostRetorno = parse_url($candidata, PHP_URL_HOST);
        if ($hostApp && $hostRetorno && strcasecmp((string) $hostApp, (string) $hostRetorno) === 0) {
            return $candidata;
        }

        return '';
    }

}
