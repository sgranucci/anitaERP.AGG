<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Exports\Ventas\PercepcioniibbExport;
use PDF;

class RepPercepcionIibbController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('ventas.reppercepcioniibb.create');
    }

    public function crearReporteControlPercepcionesIIBB(Request $request)
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
		return (new PercepcioniibbExport)->rangoFecha($request->desdefecha, $request->hastafecha)->download('percepcioniibb.'.$extension);
    }
}
