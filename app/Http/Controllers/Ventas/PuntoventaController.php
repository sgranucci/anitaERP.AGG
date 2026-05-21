<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPuntoventa;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Services\Ventas\PuntoventaAnitaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PuntoventaController extends Controller
{
    private $repository;

    private $actividad_arcaRepository;

    public function __construct(PuntoventaRepositoryInterface $repository,
        Actividad_ArcaRepositoryInterface $actividad_arcaRepository,
        private PuntoventaAnitaSyncService $puntoventaAnitaSyncService,
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

        return view('ventas.puntoventa.index', compact('datas', 'modofacturacionEnum', 'estadoEnum', 'sinPuntosCargados'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-puntos-de-venta');
        $this->armaTablasVista($pais_query, $provincia_query, $modofacturacionEnum,
            $estadoEnum, $empresa_query, $actividad_arca_query, $webserviceEnum);

        return view('ventas.puntoventa.crear', compact('pais_query', 'provincia_query',
            'empresa_query', 'modofacturacionEnum',
            'estadoEnum', 'actividad_arca_query', 'webserviceEnum'));
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
        $this->armaTablasVista($pais_query, $provincia_query, $modofacturacionEnum,
            $estadoEnum, $empresa_query, $actividad_arca_query, $webserviceEnum);

        return view('ventas.puntoventa.editar', compact('data', 'pais_query', 'provincia_query',
            'empresa_query', 'modofacturacionEnum',
            'estadoEnum', 'actividad_arca_query', 'webserviceEnum'));
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

    private function armaTablasVista(&$pais_query, &$provincia_query, &$modofacturacion_enum,
        &$estado_enum, &$empresa_query, &$actividad_arca_query, &$webservice_enum)
    {
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $empresa_query = Empresa::orderBy('nombre')->get();
        $modofacturacion_enum = Puntoventa::$enumModoFacturacion;
        $estado_enum = Puntoventa::$enumEstado;
        $webservice_enum = Puntoventa::$enumWebservice;
        $actividad_arca_query = $this->actividad_arcaRepository->all();
    }
}
