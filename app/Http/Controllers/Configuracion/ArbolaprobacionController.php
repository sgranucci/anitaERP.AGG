<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionArbolaprobacion;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_NivelRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Services\Configuracion\ArbolaprobacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class ArbolaprobacionController extends Controller
{
	private $arbolaprobacionRepository;
    private $arbolaprobacion_nivelRepository;
    private $arbolaprobacion_movimientoRepository;
    private $empresaRepository;
    private $centrocostoRepository;
    private $monedaRepository;
    private $arbolaprobacionService;

	public function __construct(ArbolaprobacionRepositoryInterface $arbolaprobacionrepository,
                                Arbolaprobacion_NivelRepositoryInterface $arbolaprobacion_nivelrepository,
                                Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                MonedaRepositoryInterface $monedarepository,
                                ArbolaprobacionService $arbolaprobacionservice)
    {
        $this->arbolaprobacionRepository = $arbolaprobacionrepository;
        $this->arbolaprobacion_nivelRepository = $arbolaprobacion_nivelrepository;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->empresaRepository = $empresarepository;
        $this->monedaRepository = $monedarepository;
        $this->arbolaprobacionService = $arbolaprobacionservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('lista-arbol-de-aprobacion');
		
		$datas = $this->arbolaprobacionRepository->all();

        return view('configuracion.arbolaprobacion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crea-arbol-de-aprobacion');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $tipoarbol_enum = Arbolaprobacion::$enumTipoArbol;
        $recordatorio_enum = Arbolaprobacion::$enumRecordatorio;
        $estado_enum = Arbolaprobacion::$enumEstado;

        return view('configuracion.arbolaprobacion.crear', compact('empresa_query', 'centrocosto_query', 'moneda_query',
                                                         			'tipoarbol_enum', 'recordatorio_enum', 'estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionArbolaprobacion $request)
    {
        DB::beginTransaction();
        try
        {
            $arbolaprobacion = $this->arbolaprobacionRepository->create($request->all());

            if ($arbolaprobacion == 'Error')
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($arbolaprobacion)
                $arbolaprobacion_nivel = $this->arbolaprobacion_nivelRepository->create($request->all(), $arbolaprobacion->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
    	return redirect('configuracion/arbolaprobacion')->with('mensaje', 'Arbol de Aprobación creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('edita-arbol-de-aprobacion');

		$data = $this->arbolaprobacionRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
		$tipoarbol_enum = Arbolaprobacion::$enumTipoArbol;
        $recordatorio_enum = Arbolaprobacion::$enumRecordatorio;
        $estado_enum = Arbolaprobacion::$enumEstado;

        return view('configuracion.arbolaprobacion.editar', compact('data', 'empresa_query', 'centrocosto_query', 'moneda_query',
																	'tipoarbol_enum', 'recordatorio_enum', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionArbolaprobacion $request, $id)
    {
        can('actualiza-arbol-de-aprobacion');

        DB::beginTransaction();
        try
        {
            $arbolaprobacion = $this->arbolaprobacionRepository->update($request->all(), $id);

            if (!$arbolaprobacion)
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($arbolaprobacion)
                $arbolaprobacion_nivel = $this->arbolaprobacion_nivelRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
		return redirect('configuracion/arbolaprobacion')->with('mensaje', 'Arbol de Aprobación actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borra-arbol-de-aprobacion');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->arbolaprobacionRepository->delete($id))
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

    // Aprobar comprobantes

    public function aprobar($tipocomprobante, $comprobante_id, $hash)
    {
        $flEncontro = false;

        // Busca hash de aprobacion en movimientos del arbol
        switch($tipocomprobante)
        {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
        }

        foreach ($arbolaprobacion_movimiento as $movimiento)
        {
            if ($movimiento->estado == Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'])
            {
                // Verifica hash
                if ($hash == $movimiento->hashaprobacion)
                {
                    $flEncontro = true;
                    $aprobacion_id = $movimiento->id;
                    $usuario_id = $movimiento->destinatariousuario_id;
                    break;
                }
            }                        
        }

        if ($flEncontro)
        {
            $this->arbolaprobacionService->aprobar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id);
            return redirect()->route('inicio')->with('mensaje', 'Comprobante aprobado con éxito')->send();
        }
        else
            return redirect()->route('inicio')->with('mensaje', 'No tiene aprobación pendiente')->send();
    }

    // Rechazar comprobantes

    public function buscaRechazo($tipocomprobante, $comprobante_id, $hash)
    {
        $flEncontro = false;

        // Busca hash de aprobacion en movimientos del arbol
        switch($tipocomprobante)
        {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
        }

        foreach ($arbolaprobacion_movimiento as $movimiento)
        {
            if ($movimiento->estado == Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'])
            {
                // Verifica hash
                if ($hash == $movimiento->hashrechazo)
                {
                    $flEncontro = true;
                    $aprobacion_id = $movimiento->id;
                    $usuario_id = $movimiento->destinatariousuario_id;
                    break;
                }
            }                        
        }

        if ($flEncontro)
        {
            return view('configuracion.arbolaprobacion.rechazar', compact('tipocomprobante', 'comprobante_id', 'aprobacion_id', 'usuario_id'));
        }
        else
            return redirect()->route('inicio')->with('mensaje', 'No tiene aprobación pendiente')->send();
    }

    public function rechazar(Request $request)
    {
        $tipocomprobante = $request->tipocomprobante;
        $comprobante_id = $request->comprobante_id;
        $aprobacion_id = $request->aprobacion_id;
        $usuario_id = $request->usuario_id;
        $observacion = $request->observacion;

        $this->arbolaprobacionService->rechazar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, $observacion);

        return redirect()->route('inicio')->with('mensaje', 'Rechazo realizado con éxito')->send();
    }

    public function leerMovimientoAprobacion($tipocomprobante, $comprobante_id)
    {
        // Busca hash de aprobacion en movimientos del arbol
        switch($tipocomprobante)
        {
            case 'OV':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
        }
        return $arbolaprobacion_movimiento;
    }
}
