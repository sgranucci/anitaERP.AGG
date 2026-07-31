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
use App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use App\Jobs\Padron_Iibb;
use App\Jobs\Configuracion\ImportarPadronIibbArbaJob;
use App\Jobs\Configuracion\ImportarPadronIibbCabaJob;
use App\Support\Configuracion\PadronIibbArchivoRutaSupport;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use DB;
use DateTime;
use InvalidArgumentException;
use Throwable;

class Padron_IibbController extends Controller
{
	private $repository;
    private $clienteRepository;
    private $padron_iibb_tasaRepository;
    private $padron_iibbRepository;
    private $padron_coeficiente_tucumanRepository;
    private $provinciaRepository;

    public function __construct(Padron_IibbRepositoryInterface $repository,
                                Padron_Iibb_TasaRepositoryInterface $padron_iibb_tasaRepository,
                                Padron_IibbRepositoryInterface $padron_iibbRepository,
                                Padron_Coeficiente_TucumanRepositoryInterface $padron_coeficiente_tucumanRepository,
                                ClienteRepositoryInterface $clienteRepository,
                                ProvinciaRepositoryInterface $provinciaRepository)
    {
        $this->repository = $repository;
        $this->clienteRepository = $clienteRepository;
        $this->padron_iibb_tasaRepository = $padron_iibb_tasaRepository;
        $this->padron_coeficiente_tucumanRepository = $padron_coeficiente_tucumanRepository;
        $this->padron_iibbRepository = $padron_iibbRepository;
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
        can('importar-padron-iibb');

        $tipopadron_enum = [
            'T' => 'Tasas',
            'C' => 'Coeficientes'
        ];
		
        return view('configuracion.padron_iibb.crearimportacion', compact('tipopadron_enum'));
    }

	public function importarPadron_Iibb(Request $request)
    {
        can('importar-padron-iibb');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $tipoPadron = '';
        $rules = [
            'provincia_id' => 'required',
            'file' => 'nullable|file|mimes:csv,txt,zip',
            'ruta_servidor' => 'nullable|string|max:500',
        ];
        if ($request->filled('tipopadron')) {
            $tipoPadron = $request->tipopadron;
            $rules['tipopadron'] = ['required', Rule::in(['T', 'C'])];
        }
        $this->validate($request, $rules);

        if (! $request->hasFile('file') && ! $request->filled('ruta_servidor')) {
            return back()->withErrors(['file' => 'Indique un archivo a subir o una ruta en el servidor.']);
        }

        // Lee provincia y arma archivos en funcion de jurisdiccion
        $provincia = $this->provinciaRepository->find($request->provincia_id);

        if ($provincia)
        {
            switch ($provincia->jurisdiccion)
            {
            case 901: // CABA → padron_iibb_caba (cola padrones)
                try {
                    [$archivo, $borrarAlTerminar] = $this->resolverArchivoPadronMasivo($request, ['txt', 'csv']);
                } catch (InvalidArgumentException | Throwable $e) {
                    return back()->withErrors(['file' => $e->getMessage()]);
                }

                ImportarPadronIibbCabaJob::dispatch(
                    $archivo,
                    (int) config('padrones_iibb.batch_caba', 2000),
                    (int) config('padrones_iibb.pause_ms', 20),
                    false,
                    $borrarAlTerminar
                );

                return back()->with(
                    'mensaje',
                    'Importación CABA (AGIP) encolada. Se procesa en background (cola padrones); no hace falta dejar esta pantalla abierta.'
                );

            case 902: // ARBA → padron_iibb_arba (cola padrones)
                try {
                    [$archivo, $borrarAlTerminar] = $this->resolverArchivoPadronMasivo($request, ['txt', 'csv', 'zip']);
                } catch (InvalidArgumentException | Throwable $e) {
                    return back()->withErrors(['file' => $e->getMessage()]);
                }

                ImportarPadronIibbArbaJob::dispatch(
                    $archivo,
                    (int) config('padrones_iibb.batch_arba', 5000),
                    (int) config('padrones_iibb.pause_ms', 20),
                    $borrarAlTerminar
                );

                return back()->with(
                    'mensaje',
                    'Importación ARBA encolada. Se procesa en background (cola padrones); no hace falta dejar esta pantalla abierta.'
                );

            case 914: // Misiones
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

            case 908: // Entre Rios
                $carpetaArchivo = Self::abreArchivo($request);

                if (($handle = fopen($carpetaArchivo, "r")) !== FALSE) {
                    while (($linea = fgets($handle)) !== FALSE) {

                        $columnas = explode(";", $linea);

                        if (is_numeric(substr($columnas[0], 0, 1)))
                        {
                            $desdeFecha = DateTime::createFromFormat('dmY', $columnas[1]);
                            $hastaFecha = DateTime::createFromFormat('dmY', $columnas[2]);

                            $arrayPadron_Iibb = [
                                'cuit' => $columnas[3]
                            ];

                            $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[3]);

                            if ($padron_iibb)
                                $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                            else
                                $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                            $tasaPercepcion = str_replace(',', '.', $columnas[7]);
                            $tasaRetencion = str_replace(',', '.', $columnas[8]);

                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb->id,
                                'provincia_id' => $provincia->id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasapercepcion' => $tasaPercepcion,
                                'tasaretencion' => $tasaRetencion,
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[4],
                                'excluido' => null
                            ];
                            $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        }     
                    }
                    fclose($handle);
                }        
                break;
                
            case 924: // Tucuman
                $carpetaArchivo = Self::abreArchivo($request);

                if (($handle = fopen($carpetaArchivo, "r")) !== FALSE) {
                    while (($linea = fgets($handle)) !== FALSE) 
                    {
                        $columnas = explode(";", $linea);

                        if (is_numeric(substr($columnas[0], 0, 1)))
                        {
                            if ($tipoPadron == 'T') // Tasas
                            {
                                $cuit = substr($columnas[0],0,11);
                                $excluido = substr($columnas[0],13,1);
                                $coeficiente = (float) substr($columnas[0],191,6);

                                if (substr($columnas[0],16,2) == 'CL')
                                    $tipoContribuyente = 'L';
                                else
                                    $tipoContribuyente = 'C';

                                $nombre = substr($columnas[0],40,60);

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],20,8));
                                $desdeFecha = $fecha;

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],30,8));
                                $hastaFecha = $fecha;

                                $arrayPadron_Iibb = [
                                    'cuit' => $cuit,
                                    'nombre' => $nombre
                                ];

                                $padron_iibb = $this->padron_iibbRepository->findPorCuit($cuit);

                                if ($padron_iibb)
                                    $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                                else
                                    $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                                // Busca registro de tasas
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->findPorIdProvincia($padron_iibb->id, $provincia->id);

                                $arrayPadron_Iibb_Tasa = [
                                        'padron_iibb_id' => $padron_iibb->id,
                                        'provincia_id' => $provincia->id,
                                        'nombre' => $nombre,
                                        'desdefecha' => $desdeFecha->format('Y-m-d'),
                                        'hastafecha' => $hastaFecha->format('Y-m-d'),
                                        'tasapercepcion' => null,
                                        'tasaretencion' => null,
                                        'tasapercepciondiferencial' => null,
                                        'tasaretenciondiferencial' => null,
                                        'coeficiente' => $coeficiente,
                                        'riesgofiscal' => null,
                                        'tipocontribuyente' => $tipoContribuyente,
                                        'excluido' => $excluido
                                    ];

                                if ($padron_iibb_tasa)
                                    $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $padron_iibb_tasa->id);
                                else
                                    $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);                                
                            }
                            else
                            {
                                $cuit = substr($columnas[0],0,11);
                                $excluido = substr($columnas[0],13,1);
                                $coeficiente = (float) substr($columnas[0],16,6);
                                $coeficienteFinal = (float) substr($columnas[0],184,6);

                                $tipoContribuyente = 'C';

                                $nombre = substr($columnas[0],32,60);

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],24,6)."01");
                                $desdeFecha = $fecha;

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],24,6)."01");
                                $hastaFecha = $fecha->modify('last day of this month');

                                // Busca registro de tasas
                                $padron_iibb_tasa = $this->padron_coeficiente_tucumanRepository->findPorCuit($cuit);

                                $arrayPadron_Coeficiente_Tucuman = [
                                        'cuit' => $cuit,
                                        'nombre' => $nombre,
                                        'desdefecha' => $desdeFecha->format('Y-m-d'),
                                        'hastafecha' => $hastaFecha->format('Y-m-d'),
                                        'coeficiente' => $coeficiente,
                                        'coeficientefinal' => $coeficienteFinal,
                                        'tipocontribuyente' => $tipoContribuyente,
                                        'excluido' => $excluido
                                    ];

                                if ($padron_iibb_tasa)
                                    $this->padron_coeficiente_tucumanRepository->update($arrayPadron_Coeficiente_Tucuman, $padron_iibb_tasa->id);
                                else
                                    $this->padron_coeficiente_tucumanRepository->create($arrayPadron_Coeficiente_Tucuman);
                            }
                        }
                    }
                }
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

    /**
     * @param list<string> $extensiones
     * @return array{0:string,1:bool} [ruta absoluta, borrar al terminar]
     */
    private function resolverArchivoPadronMasivo(Request $request, array $extensiones): array
    {
        if ($request->filled('ruta_servidor')) {
            $ruta = PadronIibbArchivoRutaSupport::validarRutaServidor((string) $request->input('ruta_servidor'));
            PadronIibbArchivoRutaSupport::extensionPermitida($ruta, $extensiones);

            return [$ruta, false];
        }

        if (! $request->hasFile('file')) {
            throw new InvalidArgumentException('Indique un archivo a subir o una ruta en el servidor.');
        }

        $upload = $request->file('file');
        $ext = strtolower($upload->getClientOriginalExtension() ?: pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));
        if (! in_array($ext, $extensiones, true)) {
            throw new InvalidArgumentException('Extensión no permitida: .' . $ext);
        }

        $ruta = PadronIibbArchivoRutaSupport::guardarUpload($upload);

        return [$ruta, true];
    }

}
