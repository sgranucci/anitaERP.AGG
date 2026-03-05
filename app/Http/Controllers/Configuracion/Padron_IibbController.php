<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use App\Imports\Configuracion\Padron_IibbImport;
use App\Http\Requests\ValidacionPadron_Iibb;
use App\Repositories\Configuracion\Padron_IibbRepositoryInterface;
use App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use App\Jobs\Padron_Iibb;
use Illuminate\Support\Facades\Bus;
use DB;
use DateTime;

class Padron_IibbController extends Controller
{
	private $repository;
    private $clienteRepository;
    private $padron_iibb_tasaRepository;
    private $provinciaRepository;

    public function __construct(Padron_IibbRepositoryInterface $repository,
                                Padron_Iibb_TasaRepositoryInterface $padron_iibb_tasaRepository,
                                ClienteRepositoryInterface $clienteRepository,
                                ProvinciaRepositoryInterface $provinciaRepository)
    {
        $this->repository = $repository;
        $this->clienteRepository = $clienteRepository;
        $this->padron_iibb_tasaRepository = $padron_iibb_tasaRepository;
        $this->provinciaRepository = $provinciaRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        can('listar-padron-iibb');

        $busqueda = $request->busqueda;

		$padron_iibbs = $this->repository->leePadron_Iibb($busqueda, true);

        $datas = ['padron_iibbs' => $padron_iibbs, 'busqueda' => $busqueda];

        return view('configuracion.padron_iibb.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-padron-iibb'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $padron_iibb = $this->repository->leePadron_Iibb($busqueda, false);

            $view =  \View::make('configuracion.padron_iibb.listado', compact('padron_iibb'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_padron_iibb';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Padron_IibbExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_iibb.xlsx');
            break;

        case 'CSV':
            return (new Padron_IibbExport($this->localidadRepository))
                        ->parametros($busqueda)
                        ->download('padron_iibb.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['padron_iibb' => $padron_iibb, 'busqueda' => $busqueda];

		return view('configuracion.padron_iibb.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-padron-iibb');

        return view('configuracion.padron_iibb.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPadron_Iibb $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/padron_iibb')->with('mensaje', 'Padrón Iibb creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-padron-iibb');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.padron_iibb.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPadron_Iibb $request, $id)
    {
        can('actualizar-padron-iibb');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/padron_iibb')->with('mensaje', 'Padrón Iibb actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-padron-iibb');

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

    public function crearImportacionPadron_Iibb()
    {
        can('importar-cliente-congelado-uif');

        $tipopadron_enum = [
            'T' => 'Tasas',
            'C' => 'Coeficientes'
        ];
		
        return view('configuracion.padron_iibb.crearimportacion', compact('tipopadron_enum'));
    }

	public function importarPadron_Iibb(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $tipoPadron = '';
        if (isset($request->tipopadron))
        {
            $tipoPadron = $request->tipopadron;
            $this->validate(request(), [
                'file' => 'mimes:csv,txt,zip',
                'tipopadron' => ['required', Rule::in(['T', 'C'])]
            ]);
        }
        else
            $this->validate(request(), [
                'file' => 'mimes:csv,txt,zip'
            ]);

        if (isset($request->tipopadron))
            $tipoPadron = $request->tipopadron;

        // Lee provincia y arma archivos en funcion de jurisdiccion
        $provincia = $this->provinciaRepository->find($request->provincia_id);

        if ($provincia)
        {
            switch ($provincia->jurisdiccion)
            {
            case 902:
                // Borra tasas actuales
                $this->padron_iibb_tasaRepository->deletePorProvinciaId($provincia->id);
                
                // Descomprime archivos
                $carpetaComprimida = Self::descomprimirArchivo($request);

                $files = File::files(Storage::path($carpetaComprimida));

                $batch = Bus::batch([])->dispatch();
                $batch->add(new Padron_Iibb($files[0]->getPathname(), $provincia->jurisdiccion, $provincia->id));

                return back()
                    ->with('mensaje', 'Padrón IIBB ARBA importado correctamente');
                break;

            case 904: // Cordoba
            case 908: // Entre Rios
            case 914: // Misiones
            case 924: // Tucuman tasas y coeficientes
                // Borra tasas actuales
                //$this->padron_iibb_tasaRepository->deletePorProvinciaId($provincia->id);
                
                $carpetaArchivo = Self::abreArchivo($request);

                $batch = Bus::batch([])->dispatch();

                if ($provincia->jurisdiccion == 924)
                    $batch->add(new Padron_Iibb($carpetaArchivo, $provincia->jurisdiccion, $provincia->id, $tipoPadron));
                else
                    $batch->add(new Padron_Iibb($carpetaArchivo, $provincia->jurisdiccion, $provincia->id));

                //File::delete($carpetaArchivo);
                return back()
                    ->with('mensaje', 'Padrón IIBB importado correctamente');
                break;
            }
        }
    }

    public function leePadronArba()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => array(
                                $autorization,
                                'Content-Type: application/json',
                                'Accept: application/json'
                                ),
                    ));

        $response = curl_exec($curl);
    }

    private function descomprimirArchivo(Request $request)
    {
        // 1. Validar que se ha subido un archivo
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No se encontró el archivo en la solicitud.'], 400);
        }

        $archivo = $request->file('file');

        // 2. Guardar el archivo subido en un lugar temporal
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaTemporal = 'temp/' . $nombreArchivo;
        Storage::put($rutaTemporal, file_get_contents($archivo));

        // 3. Definir la carpeta de destino para la descompresión
        $carpetaDestino = 'descomprimidos/' . pathinfo($nombreArchivo, PATHINFO_FILENAME);

        // 4. Usar ZipArchive para descomprimir
        $zip = new \ZipArchive;
        $res = $zip->open(Storage::path($rutaTemporal));

        if ($res === TRUE) {
            // Crear la carpeta de destino si no existe
            if (!Storage::exists($carpetaDestino)) {
                Storage::makeDirectory($carpetaDestino);
            }
            
            // Extraer todo en la carpeta de destino
            $zip->extractTo(Storage::path($carpetaDestino));
            $zip->close();
            
            // Eliminar el archivo ZIP temporal
            Storage::delete($rutaTemporal);

            return $carpetaDestino;
        } else {
            Log::error("Error al abrir el archivo ZIP: " . $nombreArchivo);
            return response()->json(['error' => 'Error al descomprimir el archivo ZIP.'], 500);
        }
    }    

    private function abreArchivo($request)
    {
        // 1. Validar que se ha subido un archivo
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No se encontró el archivo en la solicitud.'], 400);
        }

        $archivo = $request->file('file');

        // 2. Guardar el archivo subido en un lugar temporal
        $nombreArchivo = $archivo->getClientOriginalName();
        $rutaTemporal = 'temp/' . $nombreArchivo;
        Storage::put($rutaTemporal, file_get_contents($archivo));

        $rutaAbsolutaDefault = storage_path('app/'.$rutaTemporal); 

        return $rutaAbsolutaDefault;
    }

}
