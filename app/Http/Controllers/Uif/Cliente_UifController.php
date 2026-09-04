<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCliente_Uif;
use App\Services\Uif\Cliente_UifService;
use App\Services\Uif\ClienteUifMatrizRiesgoExplicacionService;
use App\Services\Uif\ClienteUifSexoAprendizajeService;
use App\Exports\Uif\Cliente_UifExport;
use App\Exports\Uif\ClienteUifMatrizRiesgoExplicacionExport;
use App\Exports\Uif\ClienteUifPremiosExport;
use App\Exports\Uif\ClienteUifReportablesExport;
use App\Models\Uif\Cliente_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_UifRepository;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use App\Repositories\Uif\Actividad_UifRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Repositories\Uif\Estadocivil_UifRepositoryInterface;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;
use App\Repositories\Uif\Juego_UifRepositoryInterface;
use App\Repositories\Uif\Nivelsocioeconomico_UifRepositoryInterface;
use App\Repositories\Uif\Pais_UifRepositoryInterface;
use App\Repositories\Uif\Pep_UifRepositoryInterface;
use App\Repositories\Uif\So_UifRepositoryInterface;
use App\Services\Uif\ClienteUifFotoDocumento;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Support\Uif\ClienteUifInformeReportablesSupport;
use App\Support\Uif\ClienteUifListadoFiltros;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class Cliente_UifController extends Controller
{
    private $cliente_uifService;
    private $cliente_uifRepository;
    private $clientePremioUifRepository;
    private $localidad_uifRepository;
    private $provincia_uifRepository;
    private $actividad_uifRepository;
    private $empresaRepository;
    private $salaRepository;
    private $estadocivil_uifRepository;
    private $factorriesgo_uifRepository;
    private $inusualidad_uifRepository;
    private $juego_uifRepository;
    private $nivelsocioeconomico_uifRepository;
    private $pais_uifRepository;
    private $pep_uifRepository;
    private $so_uifRepository;
    private $tipodocumentoRepository;
    private $clienteUifSexoAprendizajeService;
    private ClienteUifMatrizRiesgoExplicacionService $matrizRiesgoExplicacionService;

	public function __construct(Cliente_UifService $cliente_uifservice,
                                ClienteUifSexoAprendizajeService $clienteUifSexoAprendizajeService,
                                ClienteUifMatrizRiesgoExplicacionService $matrizRiesgoExplicacionService,
                                Cliente_UifRepositoryInterface $cliente_uifrepository,
                                Cliente_Premio_UifRepository $clientePremioUifRepository,
                                Localidad_UifRepositoryInterface $localidad_uifrepository,
                                Provincia_UifRepositoryInterface $provincia_uifrepository,
                                Actividad_UifRepositoryInterface $actividad_uifRepository,
                                EmpresaRepositoryInterface $empresarepository,
                                SalaRepositoryInterface $salarepository,
                                Estadocivil_UifRepositoryInterface $estadocivil_uifrepository,
                                Factorriesgo_UifRepositoryInterface $factorriesgo_uifrepository,
                                Inusualidad_UifRepositoryInterface $inusualidad_uifrepository,
                                Juego_UifRepositoryInterface $juego_uifrepository,
                                Nivelsocioeconomico_UifRepositoryInterface $nivelsocioeconomico_uifrepository,
                                Pais_UifRepositoryInterface $pais_uifrepository,
                                Pep_UifRepositoryInterface $pep_uifrepository,
                                So_UifRepositoryInterface $so_uifrepository,
                                TIpodocumentoRepositoryInterface $tipodocumentorepository)
    {
        $this->cliente_uifService = $cliente_uifservice;
        $this->cliente_uifRepository = $cliente_uifrepository;
        $this->clientePremioUifRepository = $clientePremioUifRepository;
        $this->localidad_uifRepository = $localidad_uifrepository;
        $this->provincia_uifRepository = $provincia_uifrepository;
        $this->actividad_uifRepository = $actividad_uifRepository;
        $this->empresaRepository = $empresarepository;
        $this->salaRepository = $salarepository;
        $this->estadocivil_uifRepository = $estadocivil_uifrepository;
        $this->factorriesgo_uifRepository = $factorriesgo_uifrepository;
        $this->inusualidad_uifRepository = $inusualidad_uifrepository;
        $this->juego_uifRepository = $juego_uifrepository;
        $this->nivelsocioeconomico_uifRepository = $nivelsocioeconomico_uifrepository;
        $this->pais_uifRepository = $pais_uifrepository;
        $this->pep_uifRepository = $pep_uifrepository;
        $this->so_uifRepository = $so_uifrepository;
        $this->tipodocumentoRepository = $tipodocumentorepository;
        $this->clienteUifSexoAprendizajeService = $clienteUifSexoAprendizajeService;
        $this->matrizRiesgoExplicacionService = $matrizRiesgoExplicacionService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cliente-uif');

        $filtros = ClienteUifListadoFiltros::resolverDesdeRequest($request);

		$cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($filtros, true);

        if (! $this->cliente_uifRepository->hayRegistrosClienteUifLocales()) {
            $this->cliente_uifRepository->sincronizarConAnita();

            $cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($filtros, true);
        }

        return view('uif.cliente_uif.index', [
            'cliente_uifs' => $cliente_uifs,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => ClienteUifListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClienteUifListadoFiltros::CAMPOS,
            'empresa_query' => ClienteUifOrigenPcSupport::empresasUifAsignadas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-uif'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClienteUifListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($filtros, false);
            $subtituloFiltros = ClienteUifListadoFiltros::subtituloFiltros($filtros);

            $view = \View::make('uif.cliente_uif.listado', compact('cliente_uifs', 'subtituloFiltros'))
                ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cliente_uif';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');

        case 'EXCEL':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($filtros)
                        ->download('cliente_uif.xlsx');
            break;

        case 'CSV':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($filtros)
                        ->download('cliente_uif.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cliente_uifs' => $cliente_uifs ?? collect(), 'busqueda' => $filtros['busqueda']];

		return view('uif.cliente_uif.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request, $uif_id = null)
    {
        can('crear-cliente-uif');

        $localidad_uif_query = $this->localidad_uifRepository->all();
        $provincia_uif_query = $this->provincia_uifRepository->all();
        $actividad_uif_query = $this->actividad_uifRepository->all();
        $uifContexto = ClienteUifOrigenPcSupport::contexto($request);
        $empresa_query = $uifContexto['empresas_uif'];
        $sala_query = $this->salaRepository->allFiltrado();
        $estadocivil_uif_query = $this->estadocivil_uifRepository->all();
        $factorriesgo_uif_query = $this->factorriesgo_uifRepository->all();
        $inusualidad_uif_query = $this->inusualidad_uifRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $nivelsocioeconomico_uif_query = $this->nivelsocioeconomico_uifRepository->all();
        $pais_uif_query = $this->pais_uifRepository->all();
        $pep_uif_query = $this->pep_uifRepository->all();
        $so_uif_query = $this->so_uifRepository->all();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
        $sexo_enum = Cliente_Uif::$enumSexo;
        $resideparaisofiscal_enum = Cliente_Uif::$enumResideParaisoFiscal;
	    $resideexterior_enum = Cliente_Uif::$enumResideExterior;
	    $cumplenormativaso_enum = Cliente_Uif::$enumCumpleNormativaSo;
	    $firmodeclaracionjurada_enum = Cliente_Uif::$enumFirmoDeclaracionJurada;
        $riesgopep_enum = Cliente_Uif::$enumRiesgoPep;

        $essupervisor = esSupervisorUif() ? 'S' : 'N';
        $uifPerfil = perfilClienteUif();
        $sexo_aprendizaje_map = $this->clienteUifSexoAprendizajeService->mapaParaFrontend();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteUifListadoFiltros::class);

        return view('uif.cliente_uif.crear', compact('localidad_uif_query', 'provincia_uif_query', 'actividad_uif_query',
                                                            'empresa_query', 'estadocivil_uif_query', 'sala_query',
                                                            'factorriesgo_uif_query', 'inusualidad_uif_query',
                                                            'juego_uif_query', 'nivelsocioeconomico_uif_query',
                                                            'pais_uif_query', 'pep_uif_query', 'so_uif_query', 'tipodocumento_query',
                                                            'sexo_enum', 'resideparaisofiscal_enum', 'resideexterior_enum',
                                                            'cumplenormativaso_enum', 'firmodeclaracionjurada_enum',
                                                            'riesgopep_enum', 'essupervisor', 'uifPerfil', 'sexo_aprendizaje_map',
                                                            'filtrosQuery', 'uifContexto'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente_Uif $request)
    {
        session(['empresa_id' => $request->empresa_id]);

        $result = $this->cliente_uifService->guardaCliente_Uif($request);

        if (isset($result['errores'])) {
            return redirect()->back()->withInput()->withErrors(['errores' => $result['errores']]);
        }

        $clienteId = (int) ($result['cliente_uif_id'] ?? 0);
        if ($request->input('ir_a_agregar_premio') == '1' && $clienteId > 0 && can('crear-cliente-premio-uif', false)) {
            return redirect()->route('crea_cliente_premio_uif', [
                'id' => $clienteId,
                'return_cliente_tab' => 3,
            ])->with('mensaje', 'Cliente creado con éxito. Complete el premio.');
        }

        return redirect()->route('consulta_cliente_uif', QueryRetornoListado::desdeRequest($request, ClienteUifListadoFiltros::class))
            ->with('mensaje', 'Cliente creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id, $origen = null)
    {
        if (! can('editar-cliente-uif', false) && ! can('listar-cliente-uif', false)) {
            abort(403);
        }

        if (!isset($origen))
            $origen = 'cliente_uif';

        $ocultarVolver = $request->query('origen') === 'modal_consulta';
        $soloSolapaPremios = $ocultarVolver && $request->query('uif_tab') === '3';

		$data = $this->cliente_uifRepository->find($id);
        $this->cliente_uifRepository->sincronizarArchivosAnitaSiCorresponde($data);
        $data->load('cliente_archivos_uif');
        try {
            ClienteUifOrigenPcSupport::assertClienteOperableEnPc($data, $request);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('consulta_cliente_uif')
                ->with('mensaje-error', $e->getMessage());
        }

        $localidad_uif_query = $this->localidad_uifRepository->all();
        $provincia_uif_query = $this->provincia_uifRepository->all();
        $actividad_uif_query = $this->actividad_uifRepository->all();
        $uifContexto = ClienteUifOrigenPcSupport::contexto($request);
        $empresa_query = $uifContexto['empresas_uif'];
        $sala_query = $this->salaRepository->allFiltrado();
        $estadocivil_uif_query = $this->estadocivil_uifRepository->all();
        $factorriesgo_uif_query = $this->factorriesgo_uifRepository->all();
        $inusualidad_uif_query = $this->inusualidad_uifRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $nivelsocioeconomico_uif_query = $this->nivelsocioeconomico_uifRepository->all();
        $pais_uif_query = $this->pais_uifRepository->all();
        $pep_uif_query = $this->pep_uifRepository->all();
        $so_uif_query = $this->so_uifRepository->all();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
        $sexo_enum = Cliente_Uif::$enumSexo;
        $resideparaisofiscal_enum = Cliente_Uif::$enumResideParaisoFiscal;
	    $resideexterior_enum = Cliente_Uif::$enumResideExterior;
	    $cumplenormativaso_enum = Cliente_Uif::$enumCumpleNormativaSo;
	    $firmodeclaracionjurada_enum = Cliente_Uif::$enumFirmoDeclaracionJurada;
        $riesgopep_enum = Cliente_Uif::$enumRiesgoPep;

        $essupervisor = esSupervisorUif() ? 'S' : 'N';
        $uifPerfil = perfilClienteUif();
        $sexo_aprendizaje_map = $this->clienteUifSexoAprendizajeService->mapaParaFrontend();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteUifListadoFiltros::class);

        return view('uif.cliente_uif.editar', compact('data', 
                                                    'localidad_uif_query', 'provincia_uif_query', 'actividad_uif_query',
                                                    'empresa_query', 'estadocivil_uif_query', 'sala_query',
                                                    'factorriesgo_uif_query', 'inusualidad_uif_query',
                                                    'juego_uif_query', 'nivelsocioeconomico_uif_query',
                                                    'pais_uif_query', 'pep_uif_query', 'so_uif_query', 'tipodocumento_query',
                                                    'sexo_enum', 'resideparaisofiscal_enum', 'resideexterior_enum',
                                                    'cumplenormativaso_enum', 'firmodeclaracionjurada_enum', 'riesgopep_enum',
                                                    'essupervisor', 'uifPerfil', 'sexo_aprendizaje_map',
                                                    'ocultarVolver', 'soloSolapaPremios', 'filtrosQuery', 'uifContexto'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente_Uif $request, $id)
    {
        can('actualizar-cliente-uif');

        session(['empresa_id' => $request->empresa_id]);

        $result = $this->cliente_uifService->actualizaCliente_Uif($request, $id);

        if (isset($result['errores'])) {
            return redirect()->back()->withInput()->withErrors(['errores' => $result['errores']]);
        }

        return redirect()->route('consulta_cliente_uif', QueryRetornoListado::desdeRequest($request, ClienteUifListadoFiltros::class))
            ->with('mensaje', 'Cliente actualizado con éxito');
    }

    /**
     * Sirve la foto DNI (storage público o rutas legacy / montaje) para vista previa o descarga.
     */
    public function mostrarFotodocumento($id)
    {
        if (! can('editar-cliente-uif', false) && ! can('listar-cliente-uif', false)) {
            abort(403);
        }

        $cliente_uif = $this->cliente_uifRepository->find($id);
        if ($cliente_uif === null || ($cliente_uif->fotodocumento ?? '') === '') {
            abort(404);
        }

        $path = ClienteUifFotoDocumento::absolutePathForCliente(
            $cliente_uif->fotodocumento,
            (string) $cliente_uif->numerodocumento,
            $cliente_uif->inroclienteid !== null ? (int) $cliente_uif->inroclienteid : null
        );
        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        if (request()->query('disposition') === 'attachment') {
            return ClienteUifFotoDocumento::aplicarAntiCacheNavegador(
                response()->download($path, basename($path))
            );
        }

        return ClienteUifFotoDocumento::aplicarAntiCacheNavegador(response()->file($path));
    }

    /**
     * Sirve un adjunto del cliente desde /scan (o legacy local).
     */
    public function mostrarArchivo($id, $archivo)
    {
        if (! can('editar-cliente-uif', false) && ! can('listar-cliente-uif', false)) {
            abort(403);
        }

        $cliente_uif = $this->cliente_uifRepository->find($id);
        if ($cliente_uif === null) {
            abort(404);
        }

        $path = ClienteUifArchivoStorage::absoluteClienteAdjunto((int) $id, (string) $archivo);
        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        if (request()->query('disposition') === 'attachment') {
            return ClienteUifArchivoStorage::aplicarAntiCacheNavegador(
                response()->download($path, basename($path))
            );
        }

        return ClienteUifArchivoStorage::aplicarAntiCacheNavegador(response()->file($path));
    }

    /**
     * Quita la foto DNI del cliente y del disco.
     */
    public function eliminarFotodocumento(Request $request, $id)
    {
        can('actualizar-cliente-uif');

        $cliente_uif = $this->cliente_uifRepository->find($id);
        if ($cliente_uif === null || ($cliente_uif->fotodocumento ?? '') === '') {
            return redirect()->back()->with('mensaje', 'No hay foto del documento para eliminar');
        }

        ClienteUifFotoDocumento::deleteStoredFile($cliente_uif->fotodocumento);

        $cliente_uif->update(['fotodocumento' => null]);

        return redirect()->back()->with('mensaje', 'Foto del documento eliminada');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-cliente-uif');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_uif = $this->cliente_uifRepository->find($id);

			if ($this->cliente_uifRepository->delete($id))
            {
                ClienteUifFotoDocumento::deleteStoredFile($cliente_uif->fotodocumento);
				$fl_borro = true;
            }

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            $cliente_uifRow = $this->cliente_uifRepository->find($id);
            if ($this->cliente_uifRepository->delete($id)) {
                ClienteUifFotoDocumento::deleteStoredFile($cliente_uifRow->fotodocumento);
                $mensaje = 'Cliente UIF borrado con éxito';
            } else {
                $mensaje = 'error';
            }

            return redirect('uif/cliente_uif')->with('mensaje', $mensaje);
        }
    }

    public function consultaCliente_Uif(Request $request)
    {
        can('listar-cliente-uif');

        $html = $this->cliente_uifRepository->consultaCliente_UifHtml(
            $request->input('consulta'),
            $request->input('anita_origen')
        );

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function leeCliente_Uif($cliente_uif_id)
    {
        can('listar-cliente-uif');

        $resumen = $this->cliente_uifRepository->findResumenParaConsulta((int) $cliente_uif_id);
        if ($resumen === null) {
            return response()->json(null, 404);
        }

        return response()->json($resumen);
    }

    // Calcula riesgo del cliente UIF

    public function calculaRiesgo($cliente_uif_id, $periodo, $inusualidad_uif_id)
    {
        return $this->cliente_uifService->calculaRiesgo($cliente_uif_id, $periodo, $inusualidad_uif_id);
    }

    /**
     * Export PDF/Excel/CSV de premios del cliente (solapa Premios). Solo supervisor UIF.
     */
    public function listarPremiosCliente(Request $request, $id, $formato = null)
    {
        if (! esSupervisorUif()) {
            abort(403);
        }

        $cliente_uif = $this->cliente_uifRepository->find($id);
        if ($cliente_uif === null) {
            abort(404);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $premios = $this->clientePremioUifRepository->leePremiosPorClienteUif((int) $id);
        $nombreBase = 'premios_cliente_uif_'.$id;

        switch ($formato) {
            case 'PDF':
                $view = \View::make('uif.cliente_uif.premios_listado', compact('premios', 'cliente_uif'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombrePdf = $nombreBase.'.pdf';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf);

                return response()->download($path.'/'.$nombrePdf);
            case 'EXCEL':
                return (new ClienteUifPremiosExport($this->clientePremioUifRepository))
                    ->parametros((int) $id, $cliente_uif)
                    ->download($nombreBase.'.xlsx');
            case 'CSV':
                return (new ClienteUifPremiosExport($this->clientePremioUifRepository))
                    ->parametros((int) $id, $cliente_uif, true)
                    ->download($nombreBase.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('edita_cliente_uif', ['id' => $id, 'uif_tab' => 3]);
    }

    public function exportarMatrizRiesgoExplicacion(Request $request, $id, $formato = null)
    {
        if (! esSupervisorUif()) {
            abort(403);
        }

        $cliente_uif = $this->cliente_uifRepository->find($id);
        if ($cliente_uif === null) {
            abort(404);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $reporte = $this->matrizRiesgoExplicacionService->explicarCliente((int) $id);
        $nombreBase = 'matriz_riesgo_uif_'.$id;

        switch ($formato) {
            case 'PDF':
                $view = \View::make('uif.cliente_uif.matriz_riesgo_explicacion_listado', compact('reporte'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombrePdf = $nombreBase.'.pdf';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'portrait');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf);

                return response()->download($path.'/'.$nombrePdf);
            case 'EXCEL':
                return (new ClienteUifMatrizRiesgoExplicacionExport())
                    ->parametros($reporte)
                    ->download($nombreBase.'.xlsx');
        }

        return redirect()->route('edita_cliente_uif', ['id' => $id, 'uif_tab' => 4]);
    }

    public function crearExportaOperacion()
    {
        can('exportar-operacion-uif');

        // Encargadas/operadores: BSA/KSA/RSA; cajeros: empresas asignadas / PC.
        $empresa_query = ClienteUifOrigenPcSupport::empresasUifAsignadas();

        return view('uif.exportaoperacion.crear', compact('empresa_query'));
    }

    public function generaExportaOperacion(Request $request)
    {
        can('exportar-operacion-uif');

        $parametros = $this->resolverParametrosExportacionUif(
            $request->periodo,
            $request->limiteinformeuif,
            $request->empresa_id
        );

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        return redirect()->route('listado_exporta_operacion_uif', [
            'periodo' => $parametros['periodo'],
            'limiteinformeuif' => $parametros['limiteinformeuif'],
            'empresa_id' => $parametros['empresa_id'],
        ]);
    }

    public function listadoExportaOperacion($periodo, $limiteinformeuif, $empresaId)
    {
        can('exportar-operacion-uif');

        $parametros = $this->resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId);

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        return $this->vistaExportaOperacionIndex($parametros);
    }

    public function exportaOperacion($periodo, $limiteinformeuif, $empresaId)
    {
        can('exportar-operacion-uif');

        $parametros = $this->resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId);

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
        ] = $parametros;

        $cliente_premio_uifs = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif, $empresaId);

        if ($cliente_premio_uifs->isEmpty()) {
            return redirect()
                ->route('crear_exporta_operacion')
                ->with('mensaje_error', 'No hay premios reportables para la empresa, periodo y monto indicados.');
        }

        try {
            $xmlExportacion = $this->cliente_uifService->exportaOperacion($periodo, $limiteinformeuif, $empresaId);
        } catch (\Throwable $e) {
            return redirect()
                ->route('listado_exporta_operacion_uif', [
                    'periodo' => $periodo,
                    'limiteinformeuif' => $limiteinformeuif,
                    'empresa_id' => $empresaId,
                ])
                ->with('mensaje_error', 'Error al generar XML UIF: '.$e->getMessage());
        }

        return redirect()->route('listado_exporta_operacion_uif', [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
        ])
            ->with('mensaje', 'Se generaron '.$xmlExportacion['cantidad'].' archivos XML. Se iniciara la descarga del ZIP a su PC.')
            ->with('uif_xml_cantidad', $xmlExportacion['cantidad'])
            ->with('uif_xml_directorio', $xmlExportacion['directorio'])
            ->with('uif_auto_descargar_xml', 1);
    }

    public function exportaOperacionExcel($periodo, $limiteinformeuif, $empresaId)
    {
        can('exportar-operacion-uif');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $parametros = $this->resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId);

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
            'empresaInforme' => $empresaInforme,
        ] = $parametros;

        $premios = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif, $empresaId);

        if ($premios->isEmpty()) {
            return redirect()
                ->route('listado_exporta_operacion_uif', [
                    'periodo' => $periodo,
                    'limiteinformeuif' => $limiteinformeuif,
                    'empresa_id' => $empresaId,
                ])
                ->with('mensaje_error', 'No hay premios reportables para exportar a Excel.');
        }

        $nombreBase = ClienteUifInformeReportablesSupport::nombreArchivoReportables($periodo, $empresaInforme);

        return (new ClienteUifReportablesExport)
            ->parametros($periodo, $premios, $empresaInforme)
            ->download($nombreBase.'.xlsx');
    }

    public function exportaOperacionPdf($periodo, $limiteinformeuif, $empresaId)
    {
        can('exportar-operacion-uif');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $parametros = $this->resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId);

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
            'empresaInforme' => $empresaInforme,
        ] = $parametros;

        $premios = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif, $empresaId);

        if ($premios->isEmpty()) {
            return redirect()
                ->route('listado_exporta_operacion_uif', [
                    'periodo' => $periodo,
                    'limiteinformeuif' => $limiteinformeuif,
                    'empresa_id' => $empresaId,
                ])
                ->with('mensaje_error', 'No hay premios reportables para exportar a PDF.');
        }

        $titulo = ClienteUifInformeReportablesSupport::tituloInformeExcel($periodo, $empresaInforme);
        $subtituloFiltros = sprintf(
            'Empresa: %s | Periodo: %s | Importe mayor a: %s',
            $empresaInforme,
            $periodo,
            number_format((float) $limiteinformeuif, 2, ',', '.')
        );

        $view = \View::make('uif.exportaoperacion.listado', [
            'premios' => $premios,
            'periodo' => $periodo,
            'empresaInforme' => $empresaInforme,
            'titulo' => $titulo,
            'subtituloFiltros' => $subtituloFiltros,
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        $nombreBase = ClienteUifInformeReportablesSupport::nombreArchivoReportables($periodo, $empresaInforme);
        $nombrePdf = preg_replace('/[^\w\- ]+/u', '', $nombreBase).'.pdf';
        $nombrePdf = trim(str_replace(' ', '_', $nombrePdf), '_');
        if ($nombrePdf === '' || $nombrePdf === '.pdf') {
            $nombrePdf = 'informe_datos_clientes_uif.pdf';
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf);

        return response()->download($path.'/'.$nombrePdf);
    }

    public function descargarXmlZip($periodo, $limiteinformeuif, $empresaId)
    {
        can('exportar-operacion-uif');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $parametros = $this->resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId);

        if ($parametros instanceof \Illuminate\Http\RedirectResponse) {
            return $parametros;
        }

        [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
        ] = $parametros;

        $directorioRelativo = ClienteUifInformeReportablesSupport::directorioExportacionXml($periodo, $empresaId);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (! $disk->exists($directorioRelativo) || count($disk->files($directorioRelativo)) === 0) {
            $premios = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif, $empresaId);
            if ($premios->isEmpty()) {
                return redirect()
                    ->route('listado_exporta_operacion_uif', [
                        'periodo' => $periodo,
                        'limiteinformeuif' => $limiteinformeuif,
                        'empresa_id' => $empresaId,
                    ])
                    ->with('mensaje_error', 'No hay premios reportables para generar XML.');
            }

            try {
                $this->cliente_uifService->exportaOperacion($periodo, $limiteinformeuif, $empresaId);
            } catch (\Throwable $e) {
                return redirect()
                    ->route('listado_exporta_operacion_uif', [
                        'periodo' => $periodo,
                        'limiteinformeuif' => $limiteinformeuif,
                        'empresa_id' => $empresaId,
                    ])
                    ->with('mensaje_error', 'Error al generar XML UIF: '.$e->getMessage());
            }
        }

        return $this->respuestaZipExportacionUif($periodo, $empresaId);
    }

    private function respuestaZipExportacionUif(string $periodo, int $empresaId)
    {
        $directorioRelativo = ClienteUifInformeReportablesSupport::directorioExportacionXml($periodo, $empresaId);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $slug = ClienteUifInformeReportablesSupport::slugPeriodoExportacion($periodo);
        $zipNombre = 'uif_operaciones_'.$empresaId.'_'.$slug.'.zip';
        $zipPath = storage_path('app/tmp/'.$zipNombre);
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()
                ->route('listado_exporta_operacion_uif', [
                    'periodo' => $periodo,
                    'limiteinformeuif' => request()->route('limiteinformeuif'),
                    'empresa_id' => $empresaId,
                ])
                ->with('mensaje_error', 'No se pudo crear el archivo ZIP de exportacion UIF.');
        }

        foreach ($disk->files($directorioRelativo) as $archivoRelativo) {
            $zip->addFromString(basename($archivoRelativo), $disk->get($archivoRelativo));
        }
        $zip->close();

        return response()->download($zipPath, $zipNombre)->deleteFileAfterSend(true);
    }

    /**
     * @return array{periodo: string, limiteinformeuif: mixed, empresa_id: int, empresaInforme: string}|\Illuminate\Http\RedirectResponse
     */
    private function resolverParametrosExportacionUif($periodo, $limiteinformeuif, $empresaId)
    {
        $periodo = normalizarPeriodoParaUrl((string) $periodo);
        $empresaId = (int) $empresaId;

        if ($empresaId <= 0) {
            return redirect()
                ->route('crear_exporta_operacion')
                ->with('mensaje_error', 'Debe seleccionar una empresa para exportar.');
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return redirect()
                ->route('crear_exporta_operacion')
                ->with('mensaje_error', 'La empresa seleccionada no esta autorizada para su usuario.');
        }

        $empresa = $this->empresaRepository->find($empresaId);
        $empresaInforme = ClienteUifInformeReportablesSupport::nombreEmpresaInforme($empresa->nombre ?? null);

        return [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
            'empresaInforme' => $empresaInforme,
        ];
    }

    /**
     * @param  array{periodo: string, limiteinformeuif: mixed, empresa_id: int, empresaInforme: string}  $parametros
     */
    private function vistaExportaOperacionIndex(array $parametros)
    {
        $periodo = $parametros['periodo'];
        $limiteinformeuif = $parametros['limiteinformeuif'];
        $empresaId = $parametros['empresa_id'];
        $empresaInforme = $parametros['empresaInforme'];

        $cliente_premio_uifs = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif, $empresaId);
        $resumen = $this->cliente_uifService->resumenExportaOperacion($cliente_premio_uifs);

        $directorioXml = ClienteUifInformeReportablesSupport::directorioExportacionXml($periodo, $empresaId);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $archivosXml = $disk->exists($directorioXml) ? $disk->files($directorioXml) : [];
        $xmlDisponible = count($archivosXml) > 0;
        $xmlCantidad = count($archivosXml);

        $xmlRecienGenerado = (bool) session('uif_xml_cantidad') || (bool) session('uif_auto_descargar_xml');
        $autoDescargarXml = (bool) session('uif_auto_descargar_xml');
        $urlDescargaXmlZip = route('descargar_cliente_uif_xml_zip', [
            'periodo' => $periodo,
            'limiteinformeuif' => $limiteinformeuif,
            'empresa_id' => $empresaId,
        ]);

        return view('uif.exportaoperacion.index', compact(
            'cliente_premio_uifs',
            'periodo',
            'limiteinformeuif',
            'empresaId',
            'resumen',
            'empresaInforme',
            'directorioXml',
            'xmlDisponible',
            'xmlCantidad',
            'xmlRecienGenerado',
            'autoDescargarXml',
            'urlDescargaXmlZip'
        ));
    }


}
