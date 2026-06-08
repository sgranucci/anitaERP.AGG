<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPuntoventa;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Services\Arca\ArcaPuntosVentaCatalogoService;
use App\Services\Arca\ArcaTiposComprobanteCatalogoService;
use App\Services\Ventas\PuntoventaAnitaSyncService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PuntoventaController extends Controller
{
    private $repository;

    private $actividad_arcaRepository;

    public function __construct(
        PuntoventaRepositoryInterface $repository,
        Actividad_ArcaRepositoryInterface $actividad_arcaRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private PuntoventaAnitaSyncService $puntoventaAnitaSyncService,
        private ArcaPuntosVentaCatalogoService $arcaPuntosVentaCatalogo,
    ) {
        $this->repository = $repository;
        $this->actividad_arcaRepository = $actividad_arcaRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-puntos-de-venta');

        $datas = Puntoventa::orderBy('id')->get();
        $sinPuntosCargados = $datas->isEmpty();

        if ($sinPuntosCargados && config('app.anita_sync_puntoventa_index')) {
            try {
                $this->puntoventaAnitaSyncService->sincronizarConAnita();
            } catch (\Throwable $e) {
                Log::warning('Puntoventa index sync Anita: '.$e->getMessage(), ['exception' => $e]);
            }
        }

        $datas = $this->repository->all();

        $estadoEnum = Puntoventa::$enumEstado;
        $modofacturacionEnum = Puntoventa::$enumModoFacturacion;
        $empresasArca = $this->empresasArcaQuery();

        return view('ventas.puntoventa.index', compact(
            'datas',
            'modofacturacionEnum',
            'estadoEnum',
            'sinPuntosCargados',
            'empresasArca'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-puntos-de-venta');

        return view('ventas.puntoventa.crear', $this->datosFormulario());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPuntoventa $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/puntoventa')->with('mensaje', 'Punto de venta creado con exito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipos-transaccion');
        $data = $this->repository->findOrFail($id);

        return view('ventas.puntoventa.editar', array_merge(
            ['data' => $data],
            $this->datosFormulario($data)
        ));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPuntoventa $request, $id)
    {
        can('actualizar-puntos-de-venta');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/puntoventa')->with('mensaje', 'Punto de venta actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-puntos-de-venta]');

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

    /**
     * Importación masiva desde Anita (ApiAnita). Si hay timeout (504), usar:
     * php artisan puntoventa:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-puntos-de-venta');

        if (! config('app.anita_sync_puntoventa_index')) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $ret = $this->puntoventaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizados'].' actualizados.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('puntoventa')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('Puntoventa sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('puntoventa')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan puntoventa:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Puntos de venta habilitados en AFIP/ARCA para el ABM (precarga index y formulario).
     */
    public function puntosVentaArca(Request $request): JsonResponse
    {
        if (! can('listar-puntos-de-venta', false)
            && ! can('crear-puntos-de-venta', false)
            && ! can('editar-puntos-de-venta', false)) {
            abort(403, 'No tiene permiso');
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['sometimes', 'boolean'],
            'webservice' => ['sometimes', 'nullable', 'string'],
            'modofacturacion' => ['sometimes', 'nullable', 'string', 'max:1'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $webservice = trim((string) $request->input('webservice', ''));
        if ($webservice === '') {
            $webservice = $this->arcaPuntosVentaCatalogo->webserviceParaEmpresa($empresaId);
        }
        $modofacturacion = trim((string) $request->input('modofacturacion', ''));
        $modofacturacion = $modofacturacion !== '' ? $modofacturacion : null;
        $diagnostico = $this->arcaPuntosVentaCatalogo->diagnosticoCertificado($empresaId, $webservice);

        try {
            $this->arcaPuntosVentaCatalogo->assertEmpresaConfigurada($empresaId, $webservice);
        } catch (Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'webservice' => $webservice,
                'diagnostico' => $diagnostico,
            ], 422);
        }

        $refresh = $request->boolean('refresh');

        try {
            $resultado = $this->arcaPuntosVentaCatalogo->obtenerPuntosVenta(
                $empresaId,
                $refresh,
                $webservice,
                $modofacturacion
            );

            return response()->json([
                'ok' => true,
                'empresa_id' => $empresaId,
                'webservice' => $resultado['webservice'],
                'webservice_etiqueta' => $this->arcaPuntosVentaCatalogo->etiquetaWebservice($resultado['webservice']),
                'diagnostico' => $diagnostico,
                'origen' => $resultado['origen'],
                'puntos' => $resultado['puntos'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $this->mensajeErrorArcaPuntosVenta($e, $webservice, $diagnostico),
                'webservice' => $webservice,
                'diagnostico' => $diagnostico,
            ], 500);
        }
    }

    // Chequea datos del punto de venta
    public function chequeapuntoventa($id)
    {
        $data = $this->repository->findOrFail($id);

        if ($data) {
            return ['modofacturacion' => $data->modofacturacion];
        }

        return -1;
    }

    // Chequea datos del punto de venta
    public function leeUnPuntoventa($id)
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(?Puntoventa $data = null): array
    {
        $data = $data ?? new Puntoventa();
        $this->armaTablasVista($pais_query, $provincia_query, $modofacturacionEnum,
            $estadoEnum, $empresa_query, $actividad_arca_query, $webserviceEnum);

        $empresasArca = $this->empresasArcaQuery();
        $empresaArcaId = (int) old('empresa_id', $data->empresa_id ?? $this->empresaArcaDefaultId($empresasArca));
        $webserviceArca = $empresaArcaId > 0
            ? $this->arcaPuntosVentaCatalogo->webserviceParaEmpresa($empresaArcaId)
            : '';
        $webserviceArcaEtiqueta = $webserviceArca !== ''
            ? $this->arcaPuntosVentaCatalogo->etiquetaWebservice($webserviceArca)
            : '';
        // Catálogo ARCA: se carga en background vía form.js (sessionStorage + endpoint JSON).
        $puntosArca = [];

        return compact(
            'data',
            'pais_query',
            'provincia_query',
            'empresa_query',
            'modofacturacionEnum',
            'estadoEnum',
            'actividad_arca_query',
            'webserviceEnum',
            'empresasArca',
            'empresaArcaId',
            'webserviceArca',
            'webserviceArcaEtiqueta',
            'puntosArca'
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    private function empresasArcaQuery()
    {
        $ids = $this->arcaPuntosVentaCatalogo->empresasConCertificadoArca();
        if ($ids === []) {
            return collect();
        }

        $query = Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'id');

        return $query->get(['id', 'nombre']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Empresa>  $empresasArca
     */
    private function empresaArcaDefaultId($empresasArca): int
    {
        if ($empresasArca->isEmpty()) {
            return 0;
        }

        $preferido = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        if ($empresasArca->contains('id', $preferido)) {
            return $preferido;
        }

        return (int) $empresasArca->first()->id;
    }

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    private function mensajeErrorArcaPuntosVenta(Exception $e, string $webservice, array $diagnostico = []): string
    {
        $msg = $e->getMessage();
        $wsaa = (string) ($diagnostico['wsaa_service'] ?? '');
        $certPath = (string) ($diagnostico['cert_path'] ?? '');

        if (stripos($msg, 'Computador no autorizado') !== false) {
            return $msg.' — Verifique el servicio WSAA «'.$wsaa.'» y los certificados en '.$certPath.'.';
        }

        if (
            stripos($msg, 'Parsing WSDL') !== false
            || stripos($msg, 'failed to load external entity') !== false
        ) {
            $env = (string) config('arca.env', 'homo');
            $subdir = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA ? 'mtxca' : 'wsfe';
            $archivo = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA
                ? 'MTXCAService.wsdl'
                : 'service.wsdl';
            $local = storage_path("app/arca/{$subdir}/wsdl/{$env}/{$archivo}");

            return $msg.' — Copie el WSDL en '.$local.' o defina ARCA_'.strtoupper($subdir).'_WSDL_LOCAL en .env.';
        }

        return $msg;
    }

    private function armaTablasVista(&$pais_query, &$provincia_query, &$modofacturacion_enum,
        &$estado_enum, &$empresa_query, &$actividad_arca_query, &$webservice_enum)
    {
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $modofacturacion_enum = Puntoventa::$enumModoFacturacion;
        $estado_enum = Puntoventa::$enumEstado;
        $webservice_enum = Puntoventa::$enumWebservice;
        $actividad_arca_query = $this->actividad_arcaRepository->all();
    }
}
