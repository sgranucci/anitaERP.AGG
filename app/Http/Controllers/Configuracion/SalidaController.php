<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\JsonResponse;
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
use App\Support\Database\SqlDialectSupport;
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
    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta' || $request->query('vista') === 'consulta';
        if ($soloConsulta) {
            if (! can('listar-salidas', false) && ! can('editar-salidas', false)) {
                abort(403, 'No tiene permiso para consultar la impresora.');
            }
        } else {
            can('editar-salidas');
        }

        $data = $this->repository->findOrFail($id);
        $ubicacion_impresora_query = $this->ubicacionImpresoraRepository->all();
        $uso_salida_impresora_query = $this->usoSalidaImpresoraRepository->all();
        $puedeActualizar = can('actualizar-salidas', false);

        return view('configuracion.salida.editar', compact(
            'data',
            'ubicacion_impresora_query',
            'uso_salida_impresora_query',
            'soloConsulta',
            'puedeActualizar'
        ));
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

        if ($request->input('vista') === 'consulta') {
            return redirect()->route('editar_salida', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Salida actualizada con exito');
        }

        return redirect('configuracion/salida')->with('mensaje', 'Salida actualizada con exito');
    }

    public function consultaSalida(Request $request): JsonResponse
    {
        if (! $this->puedeConsultarSalida()) {
            abort(403);
        }

        $consulta = trim((string) $request->input('consulta', ''));
        $query = Salida::query()
            ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
            ->orderBy('nombre');

        if ($consulta !== '') {
            $like = '%'.$consulta.'%';
            $query->where(function ($q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('comando', 'like', $like)
                    ->orWhereHas('ubicacionImpresora', fn ($u) => $u->where('nombre', 'like', $like))
                    ->orWhereHas('usoSalidaImpresoras', fn ($u) => $u->where('nombre', 'like', $like));
            });
        }

        $filas = $query->limit(200)->get();
        $puedeAbrirAbm = can('editar-salidas', false) || can('listar-salidas', false);

        if ($filas->isEmpty()) {
            return response()->json(['data' => '<tr><td colspan="5">Sin resultados</td></tr>']);
        }

        $html = '';
        foreach ($filas as $salida) {
            $descripcion = $this->descripcionSalida($salida);
            $html .= '<tr>';
            $html .= '<td class="id">'.e($salida->id).'</td>';
            $html .= '<td class="nombre">'.e($salida->nombre).'</td>';
            $html .= '<td class="descripcion">'.e($descripcion).'</td>';
            $html .= '<td>'.e($salida->usos_etiqueta).'</td>';
            $html .= '<td class="text-nowrap">';
            $html .= '<a class="btn btn-warning btn-sm eligeconsultasalida">Elegir</a>';
            if ($puedeAbrirAbm) {
                $url = route('editar_salida', [
                    'id' => $salida->id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
                $html .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
            }
            $html .= '</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function resolverSalida(Request $request): JsonResponse
    {
        if (! $this->puedeConsultarSalida()) {
            abort(403);
        }

        $nombre = trim((string) $request->input('nombre', ''));
        if ($nombre === '') {
            return response()->json(['error' => 'Nombre vacío'], 404);
        }

        $query = Salida::query()->with(['ubicacionImpresora', 'usoSalidaImpresoras']);
        if (ctype_digit($nombre)) {
            $salida = (clone $query)->whereKey((int) $nombre)->first();
        } else {
            $coincidencias = (clone $query)
                ->whereRaw(SqlDialectSupport::lower('nombre').' = ?', [mb_strtolower($nombre)])
                ->limit(2)
                ->get();
            $salida = $coincidencias->count() === 1 ? $coincidencias->first() : null;
        }

        if (! $salida) {
            return response()->json(['error' => 'Impresora no encontrada'], 404);
        }

        return response()->json([
            'id' => $salida->id,
            'nombre' => $salida->nombre,
            'descripcion' => $this->descripcionSalida($salida),
        ]);
    }

    private function puedeConsultarSalida(): bool
    {
        return can('listar-salidas', false)
            || can('editar-salidas', false)
            || can('crear-configuracion-puntoventa-gastronomia', false)
            || can('editar-configuracion-puntoventa-gastronomia', false)
            || can('actualizar-configuracion-puntoventa-gastronomia', false);
    }

    private function descripcionSalida(Salida $salida): string
    {
        $ubicacion = trim((string) $salida->ubicacion);
        if ($ubicacion !== '') {
            return $ubicacion;
        }

        return trim((string) $salida->usos_etiqueta);
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

        $seteosalida = SeteoSalidaProgramaSupport::esProgramaEtiquetaOt($programa)
            ? $this->seteosalidaRepository->buscaSeteoEtiquetaOt(
                $usuario_id,
                SeteoSalidaProgramaSupport::tipoDesdeProgramaEtiquetaOt($programa)
            )
            : $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

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

        $programa = $this->seteosalidaRepository->armaNombrePrograma($opcion);
        $seteosalida = SeteoSalidaProgramaSupport::esProgramaEtiquetaOt($programa)
            ? $this->seteosalidaRepository->buscaSeteoEtiquetaOt(
                $usuario_id,
                SeteoSalidaProgramaSupport::tipoDesdeProgramaEtiquetaOt($programa)
            )
            : $this->seteosalidaRepository->buscaSeteo($usuario_id, $opcion);

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
