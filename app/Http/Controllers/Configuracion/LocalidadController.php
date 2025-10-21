<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Localidad;
use App\Repositories\Configuracion\LocalidadRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionLocalidad;
use App\Models\Configuracion\Provincia;
use App\Exports\Configuracion\LocalidadExport;

class LocalidadController extends Controller
{
    private $localidadRepository;

    public function __construct(
            LocalidadRepositoryInterface $localidadRepository
            )
    {
        $this->localidadRepository = $localidadRepository;    
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        can('listar-localidades');

        $busqueda = $request->busqueda;

		$localidades = $this->localidadRepository->leeLocalidad($busqueda, true);

        if ($localidades->isEmpty())
		{
			$Localidad = new Localidad();
        	$Localidad->sincronizarConAnita();
	
            $localidades = $this->localidadRepository->leeLocalidad($busqueda, true);
		}

        $datas = ['localidades' => $localidades, 'busqueda' => $busqueda];

        return view('configuracion.localidad.index', $datas);
    }
    

	public function leerLocalidades($id)
    {
        return Localidad::select('id','nombre')->where('provincia_id',$id)->orderBy('nombre','asc')->get()->toArray();
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-localidades'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $localidades = $this->localidadRepository->leeLocalidad($busqueda, false);

            $view =  \View::make('configuracion.localidad.listado', compact('localidades'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_localidad';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new LocalidadExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('localidad.xlsx');
            break;

        case 'CSV':
            return (new LocalidadExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('localidad.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['localidades' => $localidades, 'busqueda' => $busqueda];

		return view('configuracion.localidad.index', $datas);       
    }

    public function leerCodigoPostal($id)
    {
        $cp = Localidad::select('codigopostal')->where('id',$id)->get();
        return $cp[0]->codigopostal;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-localidades');

		$provincia_query = Provincia::all();

        return view('configuracion.localidad.crear', compact('provincia_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionLocalidad $request)
    {
        $localidad = Localidad::create($request->all());

		// Graba anita
		$Localidad = new Localidad();
        $Localidad->guardarAnita($request, $localidad->id);

        return redirect('configuracion/localidad')->with('mensaje', 'Localidad creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-localidades');
		$provincia_query = Provincia::all();
		$data = Localidad::where('id', $id)->with('provincias:id,nombre')->first();
        return view('configuracion.localidad.editar', compact('data', 'provincia_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionLocalidad $request, $id)
    {
        can('actualizar-localidades');
        Localidad::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Localidad = new Localidad();
        $Localidad->actualizarAnita($request, $id);

        return redirect('configuracion/localidad')->with('mensaje', 'Localidad actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-localidades');

		// Elimina anita
		$Localidad = new Localidad();
        $Localidad->eliminarAnita($id);

        if ($request->ajax()) {
            if (Localidad::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
