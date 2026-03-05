<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\Configuracion\Padron_MipymeImport;
use App\Http\Requests\ValidacionPadron_Mipyme;
use App\Repositories\Configuracion\Padron_MipymeRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use DB;

class Padron_MipymeController extends Controller
{
	private $repository;
    private $clienteRepository;

    public function __construct(Padron_MipymeRepositoryInterface $repository,
                                ClienteRepositoryInterface $clienteRepository)
    {
        $this->repository = $repository;
        $this->clienteRepository = $clienteRepository;
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

    // Importar clientes congelados desde excel

    public function crearImportacionPadron_Mipyme()
    {
        can('importar-cliente-congelado-uif');
		
        return view('configuracion.padron_mipyme.crearimportacion');
    }

	public function importarPadron_Mipyme(Request $request)
    {
        $this->validate(request(), [
            'file' => 'mimes:csv,txt'
        ]);

        try {
            set_time_limit(0);

            DB::beginTransaction();

            // Actualiza todos los clientes normalizando modo de facturacion
            $this->clienteRepository->actualizaPadronMipyme('C');

            // Borra todo el padron
            DB::table('padron_mipyme')->delete();

            // Importa csv
            Excel::import(new Padron_MipymeImport, request("file"));

            DB::commit();

            return back()
                ->with('mensaje', 'Padrón Mipyme importado correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

}
