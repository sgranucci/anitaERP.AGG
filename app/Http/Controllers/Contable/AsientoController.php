<?php

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAsiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\Asiento_ArchivoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Queries\Contable\AsientoQueryInterface;
use App\Exports\Contable\AsientoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class AsientoController extends Controller
{
    private $asientoRepository;
    private $asiento_movimientoRepository;
    private $asiento_archivoRepository;
    private $cuentacontableRepository;
    private $tipoasientoRepository;
    private $centrocostoRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $asientoQuery;

	public function __construct(AsientoRepositoryInterface $asientorepository,
                                Asiento_MovimientoRepositoryInterface $asiento_movimientorepository,
                                Asiento_ArchivoRepositoryInterface $asiento_archivorepository,
                                MonedaRepositoryInterface $monedarepository,
                                TipoasientoRepositoryInterface $tipoasientorepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                AsientoQueryInterface $asientoquery
                                )
    {
        $this->asientoRepository = $asientorepository;
        $this->asiento_movimientoRepository = $asiento_movimientorepository;
        $this->asiento_archivoRepository = $asiento_archivorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->tipoasientoRepository = $tipoasientorepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->asientoQuery = $asientoquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-asiento');
		
        $hayAsientos = $this->asientoQuery->first();

		if (!$hayAsientos)
			$this->asientoRepository->sincronizarConAnita();

        $busqueda = $request->busqueda;

		$asientos = $this->asientoQuery->leeAsiento($busqueda, true);
        $datas = ['asientos' => $asientos, 'busqueda' => $busqueda];

        return view('contable.asiento.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-asiento'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $asientos = $this->asientoQuery->leeAsiento($busqueda, false);

            $view =  \View::make('contable.asiento.listado', compact('asientos'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_asiento';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new AsientoExport($this->asientoQuery))
                        ->parametros($busqueda)
                        ->download('asiento.xlsx');
            break;

        case 'CSV':
            return (new AsientoExport($this->asientoQuery))
                        ->parametros($busqueda)
                        ->download('asiento.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['asientos' => $asientos, 'busqueda' => $busqueda];

		return view('contable.asiento.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-asiento');

        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        
        return view('contable.asiento.crear', compact('tipoasiento_query', 'moneda_query', 
                                                'empresa_query', 'cuentacontable_query',
                                                'centrocosto_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionAsiento $request)
    {
        session(['empresa_id' => $request->empresa_id]);
        session(['tipoasiento_id' => $request->tipoasiento_id]);

        DB::beginTransaction();
        try
        {
            $asiento = $this->asientoRepository->create($request->all());

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

                // Guarda tablas asociadas
            if ($asiento)
            {
                $asiento_movimiento = $this->asiento_movimientoRepository->create($request->all(), $asiento->id);
                $asiento_archivo = $this->asiento_archivoRepository->create($request, $asiento->id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-asiento');

		$data = $this->asientoRepository->find($id);

        $tipoasiento_query = $this->tipoasientoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();

        return view('contable.asiento.editar', compact('data', 
                                                    'tipoasiento_query', 'moneda_query', 
                                                    'empresa_query', 'cuentacontable_query',
                                                    'centrocosto_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionAsiento $request, $id)
    {
        can('actualizar-asiento');

        session(['empresa_id' => $request->empresa_id]);
        session(['tipoasiento_id' => $request->tipoasiento_id]);
        
        DB::beginTransaction();
        try
        {
            // Graba asiento
            $asiento = $this->asientoRepository->update($request->all(), $id);

            if ($asiento === 'Error')
                throw new Exception('Error en grabacion anita.');

                // Graba movimientos del asiento
            $this->asiento_movimientoRepository->update($request->all(), $id);

            // Graba archivos del asiento
            $this->asiento_archivoRepository->update($request, $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
    }

    // Copiar o copia revirtiendo asiento

    public function copiarAsiento(Request $request)
    {
        $id = $request->id;
        $fechacopia = $request->fechacopia;
        $flRevierte = false;

        if (isset($request->revierte))
            $flRevierte = true;

        $data = $this->asientoRepository->find($id)->toArray();

        $centrocosto_ids = [];
        $debes = [];
        $haberes = [];
        $cuentacontable_ids = [];
        $observaciones = [];
        $moneda_ids = [];
        $cotizaciones = [];
        foreach ($data['asiento_movimientos'] as $movimiento)
        {
            $centrocosto_ids[] = $movimiento['centrocosto_id'];

            if ($flRevierte)
            {
                if ($movimiento['monto'] >= 0)
                {
                    $haberes[] = $movimiento['monto'];
                    $debes[] = 0;
                }
                else
                {
                    $debes[] = abs($movimiento['monto']);
                    $haberes[] = 0;
                }
            }
            else
            {
                if ($movimiento['monto'] >= 0)
                {
                    $debes[] = $movimiento['monto'];
                    $haberes[] = 0;
                }
                else
                {
                    $haberes[] = abs($movimiento['monto']);
                    $debes[] = 0;
                }
            }

            $cuentacontable_ids[] = $movimiento['cuentacontable_id'];
            $observaciones[] = $movimiento['observacion'];
            $moneda_ids[] = $movimiento['moneda_id'];
            $cotizaciones[] = $movimiento['cotizacion'];
        }
        $nombrearchivos = [];
        foreach ($data['asiento_archivos'] as $archivo) 
            $nombrearchivos[] = $archivo['nombrearchivo'];

        $datas = ['centrocosto_ids' => $centrocosto_ids,
                    'cuentacontable_ids' => $cuentacontable_ids,
                    'moneda_ids' => $moneda_ids,
                    'observaciones' => $observaciones,
                    'cotizaciones' => $cotizaciones,
                    'debes' => $debes,
                    'haberes' => $haberes
                    ];

        // Modifica la observacion
        $data['observacion'] = ($flRevierte ? 'Revierte asiento ' : 'Copiado de ').$data['numeroasiento'].' '.$data['observacion'];

        // Graba el asiento
        DB::beginTransaction();
        try
        {
            $asiento = $this->asientoRepository->create($data);

            if ($asiento == 'Error')
                throw new Exception('Error en grabacion anita.');

            // Guarda tablas asociadas
            if ($asiento)
            {
                $asiento_movimiento = $this->asiento_movimientoRepository->create($datas, $asiento->id);
                
                foreach($nombrearchivos as $archivo)
                    $asiento_archivo = $this->asiento_archivoRepository->copiaArchivo($id, $archivo, $asiento->id);
            }

            DB::commit();

            return ['asiento_id' => $asiento->id, 'numeroasiento' => $asiento->numeroasiento];

        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-asiento');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->asientoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->asientoRepository->delete($id))
                $mensaje = 'Asiento borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect('contable/asiento')->with('mensaje', $mensaje);
        }
    }
}
