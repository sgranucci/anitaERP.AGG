<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Produccion\ConsumoCajaExport;
use App\Services\Ventas\OrdentrabajoService;
use PDF;

class RepConsumoCajaController extends Controller
{
    private $ordentrabajoService;

    public function __construct(OrdentrabajoService $ordentrabajoservice)
    {
        $this->middleware('auth');
        $this->ordentrabajoService = $ordentrabajoservice;
    }

    public function index()
    {
        return view('produccion.repconsumocaja.create');
    }

    public function crearReporteConsumoCaja(Request $request)
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
		return (new ConsumoCajaExport($this->ordentrabajoService))
                                    ->parametros($request->desdefecha, 
                                                $request->hastafecha, 
                                                $request->ordenestrabajo)
                                    ->download('consumocaja.'.$extension);
    }
}
