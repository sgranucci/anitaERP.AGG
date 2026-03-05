<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exports\Produccion\TotalParesExport;
use App\Services\Ventas\OrdentrabajoService;
use PDF;

class RepTotalParesController extends Controller
{
    private $ordentrabajoService;

    public function __construct(OrdentrabajoService $ordentrabajoservice)
    {
        $this->middleware('auth');
        $this->ordentrabajoService = $ordentrabajoservice;
    }

    public function index()
    {
        $apertura_enum = [
			'DIARIA' => 'Por dia',
			'SEMANAL' => 'Por semana',
			'MENSUAL' => 'Por mes',
		];

        return view('produccion.reptotalpares.create', compact('apertura_enum'));
    }

    public function crearReporteTotalPares(Request $request)
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
		return (new TotalParesExport($this->ordentrabajoService))
                                    ->parametros($request->desdefecha, 
                                                $request->hastafecha, 
                                                $request->ordenestrabajo,
                                                $request->apertura)
                                    ->download('totalpares.'.$extension);
    }
}
