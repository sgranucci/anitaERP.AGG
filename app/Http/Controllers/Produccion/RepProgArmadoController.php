<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Produccion\ProgArmadoExport;
use App\Services\Ventas\OrdentrabajoService;
use PDF;

class RepProgArmadoController extends Controller
{
    private $ordentrabajoService;

    public function __construct(OrdentrabajoService $ordentrabajoservice)
    {
        $this->middleware('auth');
        $this->ordentrabajoService = $ordentrabajoservice;
    }

    public function index()
    {
        $tipoProgramacion_enum = [
			'PROVISORIA' => 'Programacion provisoria',
			'DEFINITIVA' => 'Programacion definitiva con ticket de caja',
		];
        return view('produccion.repprogarmado.create', compact('tipoProgramacion_enum'));
    }

    public function crearReporteProgArmado(Request $request)
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
		return (new ProgArmadoExport($this->ordentrabajoService))
                                    ->parametros($request->ordenestrabajo, $request->tipoprogramacion)
                                    ->download('progarmado.'.$extension);
    }
}
