<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPadron_Mipyme;
use App\Repositories\Configuracion\Padron_MipymeRepositoryInterface;
use App\Services\Configuracion\PadronMipymeImportadorService;

class Padron_MipymeController extends Controller
{
	private $repository;

    public function __construct(Padron_MipymeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        can('listar-padron-mipyme');

        $busqueda = $request->busqueda;

		$padron_mipymes = $this->repository->leePadron_Mipyme($busqueda, true);

        $datas = ['padron_mipymes' => $padron_mipymes, 'busqueda' => $busqueda];

        return view('configuracion.padron_mipyme.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-padron-mipyme'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $padron_mipymes = $this->repository->leePadron_Mipyme($busqueda, false);

            $view =  \View::make('configuracion.padron_mipyme.listado', compact('padron_mipymes'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_padron_mipyme';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Padron_MipymeExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_mipyme.xlsx');
            break;

        case 'CSV':
            return (new Padron_MipymeExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_mipyme.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['padron_mipymes' => $padron_mipymes, 'busqueda' => $busqueda];

		return view('configuracion.padron_mipyme.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-padron-mipyme');

        return view('configuracion.padron_mipyme.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPadron_Mipyme $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/padron_mipyme')->with('mensaje', 'Padrón Mipyme creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-padron-mipyme');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.padron_mipyme.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPadron_Mipyme $request, $id)
    {
        can('actualizar-padron-mipyme');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/padron_mipyme')->with('mensaje', 'Padrón Mipyme actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-padron-mipyme');

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

    public function crearImportacionPadron_Mipyme()
    {
        can('importar-padron-mipyme');

        return view('configuracion.padron_mipyme.crearimportacion');
    }

    public function preanalizarPadron_Mipyme(Request $request, PadronMipymeImportadorService $importador)
    {
        can('importar-padron-mipyme');

        $request->validate([
            'file' => 'required|file',
        ]);

        ini_set('memory_limit', '-1');
        set_time_limit(120);

        return response()->json($importador->analizar($request->file('file')));
    }

    public function importarPadron_Mipyme(Request $request, PadronMipymeImportadorService $importador)
    {
        can('importar-padron-mipyme');

        $request->validate([
            'file' => 'required|file',
        ]);

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $resultado = $importador->importarDesdeArchivo($request->file('file'));

        return back()->with('mensaje', $resultado['mensaje']);
    }

}
