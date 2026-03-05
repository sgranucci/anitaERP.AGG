<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Puntoventa;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPuntoventa;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;

class PuntoventaController extends Controller
{
	private $repository;
    private $actividad_arcaRepository;

    public function __construct(PuntoventaRepositoryInterface $repository,
                                Actividad_ArcaRepositoryInterface $actividad_arcaRepository)
    {
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
		$datas = $this->repository->all();
        $estadoEnum = Puntoventa::$enumEstado;
        $modofacturacionEnum = Puntoventa::$enumModoFacturacion;

        return view('ventas.puntoventa.index', compact('datas', 'modofacturacionEnum', 'estadoEnum'));
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
                                $estadoEnum, $empresa_query, $actividad_arca_query);
        
        return view('ventas.puntoventa.crear', compact('pais_query', 'provincia_query',
                                                        'empresa_query', 'modofacturacionEnum',
                                                        'estadoEnum', 'actividad_arca_query'));
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
                                $estadoEnum, $empresa_query, $actividad_arca_query);
        
        return view('ventas.puntoventa.editar', compact('data', 'pais_query', 'provincia_query',
                                                        'empresa_query', 'modofacturacionEnum',
                                                        'estadoEnum', 'actividad_arca_query'));
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

    // Chequea datos del punto de venta
    public function chequeapuntoventa($id)
    {
        $data = $this->repository->findOrFail($id);

        if ($data)
        {
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
                                    &$estado_enum, &$empresa_query, &$actividad_arca_query)
    {
        $pais_query = Pais::orderBy('nombre')->get();
        $provincia_query = Provincia::orderBy('nombre')->get();
        $empresa_query = Empresa::orderBy('nombre')->get();
        $modofacturacion_enum = Puntoventa::$enumModoFacturacion;
        $estado_enum = Puntoventa::$enumEstado;
        $actividad_arca_query = $this->actividad_arcaRepository->all();
    }
}
