<?php

namespace App\Http\Controllers\Graficos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Exports\Graficos\LecturasExport;
use App\Exports\Graficos\IndicadoresExport;
use App\Exports\Graficos\OperacionesExport;
use App\Exports\Graficos\ReporteIndicadoresExport;
use App\Services\Graficos\IndicadoresService;
use App\Jobs\GeneraOrdenes;
use PDF;
use DB;

class GraficosController extends Controller
{
	private $indicadoresService;

	public function __construct(IndicadoresService $indicadoresservice)
	{
		$this->middleware('auth');

		$this->indicadoresService = $indicadoresservice;
	}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */    
	private $calculoBase_enum = [
		'1' => 'HL/2',
		'2' => 'HLC/3',
		'3' => 'OHLC/4',
	];

	public function index()
    {
		$lecturas = '';
        return view('graficos.velas', compact('lecturas'));
    }

	public function leerDatosLecturas($fecha, $dias)
    {
		$dfecha = strptime($fecha, '%d-%m-%Y');
		$timestamp = mktime(5, 0, 0, $dfecha['tm_mon']+1, $dfecha['tm_mday'], $dfecha['tm_year']+1900)*1000;
			
		$data = DB::connection('trade')->table('trade.lecturas')
				->select('fechaChar as fechastr',
						 'openPrice as open',
						 'highPrice as high',
						 'lowPrice as low',
						 'closePrice as close',
						 'volume')
				->where('chartTime', '>=', $timestamp)
				->get();
								
		foreach($data as $key => $value)
		{
			if (substr($value->fechastr, -8) < "23:59:00")
				$array[] = ['hora'=>substr($value->fechastr, -8), 'low'=>$value->low, 'open'=>$value->open, 'close'=>$value->close, 'high'=>$value->high];
		}
		$lecturas = json_encode($array, JSON_NUMERIC_CHECK);
		
		return $lecturas;
    }

	public function indexReporteLecturas()
	{
		$compresion_enum = [
			'1' => '1 minuto',
			'2' => '5 minutos',
			'3' => '15 minutos',
			'4' => '1 hora',
			'5' => '1 día'
			];

		return view('graficos.reporte.create', compact('compresion_enum'));
	}

	public function crearReporteLecturas(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new LecturasExport)
				->parametros($request->desdefecha, $request->hastafecha, $request->desdehora, $request->hastahora, $request->compresion)
				->download('reportelecturas.'.$extension);
    }

	public function indexReporteIndicadores()
	{
		$compresion_enum = [
			'1' => '1 minuto',
			'2' => '5 minutos',
			'3' => '15 minutos',
			'4' => '1 hora',
			'5' => '1 día'
			];

		$filtroSetup_enum = [
			'A' => 'Solo alcistas',
			'B' => 'Solo bajistas',
			'T' => 'Alcistas y Bajistas',
			];

		$gatillo_enum = [
				'A' => 'RRR >= 1.5',
				'B' => 'RRR >= 1.5 y SL < 500',
				];
	
		$administracionPosicion_enum = [
			'A' => 'Administración sin filtro de tiempo',
			'B' => 'Administración filtrando por tiempo',
			];

		$filtrosMatematicos_enum = [
				'S' => 'Con filtros matematicos',
				'B' => 'Sin filtros matematicos',
				];
	
		$calculoBase_enum = $this->calculoBase_enum;
		return view('graficos.reporteindicadores.create', compact('calculoBase_enum', 'compresion_enum',
																'filtroSetup_enum', 'administracionPosicion_enum',
																'filtrosMatematicos_enum',
																'gatillo_enum'));
	}

	public function crearReporteIndicadores(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		$calculoBase_enum = $this->calculoBase_enum;

        $indicadores = $this->indicadoresService->calculaIndicadores($request->desdefecha, 
                        $request->hastafecha, 
                        $request->desdeHhra, 
                        $request->hastahora, 
                        $request->especie,
                        $request->calculobase,
                        $request->mmcorta,
                        $request->mmlarga,
                        $request->compresion,
                        $request->largovma,
                        $request->largocci,
                        $request->largoxtl,
                        $request->umbralxtl,
                        $calculoBase_enum,
                        $request->swingsize,
						$request->filtroSetup,
						$request->cantidadcontratos,
						$request->administracionposicion,
						$request->tiempo,
						$request->filtrosmatematicos,
						$request->gatillo);

		return (new ReporteIndicadoresExport)
				->parametros($request->desdefecha, 
							$request->hastafecha, 
							$request->desdehora, 
							$request->hastahora, 
							$request->especie,
							$request->calculobase,
							$request->mmcorta,
							$request->mmlarga,
							$request->compresion,
							$request->largovma,
							$request->largocci,
							$request->largoxtl,
							$request->umbralxtl,
							$calculoBase_enum,
							$request->swingsize,
							$request->filtroSetup,
							$request->cantidadcontratos,
							$indicadores['indicadores'],
							$indicadores['operaciones'],
							$request->administracionposicion,
							$request->tiempo)
				->download('reporteIndicadores.'.$extension);
    }
	
	public function indexGeneraOrdenes()
	{
		$compresion_enum = [
			'1' => '1 minuto',
			'2' => '5 minutos',
			'3' => '15 minutos',
			'4' => '1 hora',
			'5' => '1 día'
			];

		$filtroSetup_enum = [
			'A' => 'Solo alcistas',
			'B' => 'Solo bajistas',
			'T' => 'Alcistas y Bajistas',
			];
		$calculoBase_enum = $this->calculoBase_enum;

		return view('graficos.generaordenes.create', compact('calculoBase_enum', 'compresion_enum',
																'filtroSetup_enum'));
	}

	// Genera ordenes
	public function generaOrdenes()
	{
		GeneraOrdenes::dispatchNow
		(request()->especie, request()->calculobase, 
								request()->mmcorta, request()->mmlarga, request()->compresion, 
								request()->largovma, request()->largocci, request()->largoxtl,
								request()->umbralxtl, request()->swingsize, request()->filtroSetup);

		return redirect('graficos/ordenes')->with('mensaje', 'Proceso iniciado con éxito');
	}
}
