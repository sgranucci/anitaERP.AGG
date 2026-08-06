<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPadron_Iibb;
use App\Repositories\Configuracion\Padron_IibbRepositoryInterface;
use App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface;
use App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use App\Jobs\Configuracion\ImportarPadronIibbArbaJob;
use App\Jobs\Configuracion\ImportarPadronIibbCabaJob;
use App\Jobs\Configuracion\ImportarPadronIibbProvinciaJob;
use App\Jobs\Configuracion\ImportarPadronIibbSantaFeJob;
use App\Support\Configuracion\PadronIibbArchivoRutaSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use App\Support\Configuracion\PadronIibbEstadoPanelSupport;
use App\Support\Configuracion\PadronIibbVigenciaSupport;
use App\Support\Configuracion\PadronIibb\PadronIibbParserFactory;
use Illuminate\Support\Facades\Log;
use DB;
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

        PadronIibbCargaRegistroSupport::cerrarColgadas();

        $ultimas_cargas = PadronIibbEstadoPanelSupport::ultimasCargas();
        $vigencia = PadronIibbVigenciaSupport::estado();
        $hay_carga_en_proceso = $ultimas_cargas->contains(fn ($carga) => $carga->enProceso());

        return view('configuracion.padron_iibb.crearimportacion', compact(
            'tipopadron_enum',
            'ultimas_cargas',
            'vigencia',
            'hay_carga_en_proceso'
        ));
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

        if (! $provincia) {
            return back()->withErrors(['provincia_id' => 'No se encontró la provincia indicada.']);
        }

        // El panel de estado se recalcula con los datos de esta carga.
        PadronIibbVigenciaSupport::olvidar();

        switch ((int) $provincia->jurisdiccion)
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
                    $borrarAlTerminar,
                    $this->registrarCarga($provincia, 'IIBB CABA (AGIP)', $archivo)
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
                    $borrarAlTerminar,
                    $this->registrarCarga($provincia, 'IIBB ARBA', $archivo)
                );

                return back()->with(
                    'mensaje',
                    'Importación ARBA encolada. Se procesa en background (cola padrones); no hace falta dejar esta pantalla abierta.'
                );

            case 921: // Santa Fe API (PARP) → padron_iibb + padron_iibb_tasa (cola padrones)
                try {
                    [$archivo, $borrarAlTerminar] = $this->resolverArchivoPadronMasivo($request, ['csv', 'txt', 'zip']);
                } catch (InvalidArgumentException | Throwable $e) {
                    return back()->withErrors(['file' => $e->getMessage()]);
                }

                ImportarPadronIibbSantaFeJob::dispatch(
                    $archivo,
                    (int) $provincia->id,
                    (int) config('padrones_iibb.batch_santafe', 3000),
                    (int) config('padrones_iibb.pause_ms', 20),
                    false,
                    $borrarAlTerminar,
                    $this->registrarCarga($provincia, 'IIBB Santa Fe (API PARP)', $archivo)
                );

                return back()->with(
                    'mensaje',
                    'Importación Santa Fe (API PARP) encolada. Se procesa en background (cola padrones); no hace falta dejar esta pantalla abierta.'
                );

            default: // Córdoba (904), Entre Ríos (908), Misiones (914) y Tucumán (924)
                return $this->encolarImportacionProvincia($request, $provincia, $tipoPadron);
            }
    }

    /**
     * @param  \App\Models\Configuracion\Provincia  $provincia
     */
    private function registrarCarga($provincia, string $etiqueta, string $archivo): ?int
    {
        return PadronIibbCargaRegistroSupport::iniciar([
            'provincia_id' => (int) $provincia->id,
            'jurisdiccion' => (int) $provincia->jurisdiccion,
            'etiqueta' => $etiqueta,
            'origen' => PadronIibbCargaRegistroSupport::ORIGEN_PANTALLA,
            'archivo' => $archivo,
            'usuario_id' => auth()->id(),
        ]);
    }

    /**
     * Encola las provincias que cargan contra padron_iibb_tasa y el padrón de
     * coeficientes de Tucumán.
     *
     * @param  \App\Models\Configuracion\Provincia  $provincia
     */
    private function encolarImportacionProvincia(Request $request, $provincia, ?string $tipoPadron)
    {
        $jurisdiccion = (int) $provincia->jurisdiccion;

        if (! PadronIibbParserFactory::soporta($jurisdiccion)) {
            return back()->withErrors([
                'provincia_id' => 'Todavía no hay importador de padrón IIBB para ' . $provincia->nombre
                    . ' (jurisdicción ' . $jurisdiccion . ').',
            ]);
        }

        $esTucuman = $jurisdiccion === PadronIibbParserFactory::JURISDICCION_TUCUMAN;
        $tipoPadron = $esTucuman ? strtoupper(trim((string) $tipoPadron)) : null;

        if ($esTucuman && ! in_array($tipoPadron, [
            PadronIibbParserFactory::TUCUMAN_TASAS,
            PadronIibbParserFactory::TUCUMAN_COEFICIENTES,
        ], true)) {
            return back()->withErrors([
                'tipopadron' => 'Para Tucumán elija el tipo de padrón: tasas o coeficientes.',
            ]);
        }

        $esCoeficientesTucuman = PadronIibbParserFactory::esTucumanCoeficientes($jurisdiccion, $tipoPadron);

        try {
            $etiqueta = $esCoeficientesTucuman
                ? 'IIBB Tucumán (coeficientes)'
                : PadronIibbParserFactory::crear($jurisdiccion, $tipoPadron)->etiqueta();

            [$archivo, $borrarAlTerminar] = $this->resolverArchivoPadronMasivo($request, ['csv', 'txt', 'zip']);
        } catch (InvalidArgumentException | Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $cargaId = PadronIibbCargaRegistroSupport::iniciar([
            'provincia_id' => (int) $provincia->id,
            'jurisdiccion' => $jurisdiccion,
            'etiqueta' => $etiqueta,
            'tipopadron' => $tipoPadron,
            'origen' => PadronIibbCargaRegistroSupport::ORIGEN_PANTALLA,
            'archivo' => $archivo,
            'usuario_id' => auth()->id(),
        ]);

        ImportarPadronIibbProvinciaJob::dispatch(
            $archivo,
            (int) $provincia->id,
            $jurisdiccion,
            $tipoPadron,
            $cargaId,
            (int) config('padrones_iibb.batch_provincia', 3000),
            (int) config('padrones_iibb.pause_ms', 20),
            false,
            $borrarAlTerminar
        );

        return back()->with(
            'mensaje',
            'Importación ' . $etiqueta . ' encolada. Se procesa en background (cola padrones); '
            . 'seguí el avance en el panel de estado de esta pantalla.'
        );
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
