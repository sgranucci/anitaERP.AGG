<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCliente_Premio_Uif;
use App\Services\Uif\Cliente_UifService;
use App\Services\Uif\ClientePremioUifFotoTesoreria;
use App\Services\Uif\ClienteUifFotoDocumento;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Exports\Uif\Cliente_Premio_UifExport;
use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Cliente_Congelado_Uif;
use App\Models\Uif\Cliente_Premio_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Uif\Juego_UifRepositoryInterface;
use App\Support\Uif\ClientePremioUifListadoFiltros;
use App\Support\Uif\ClienteUifCumplimientoSupport;
use App\Support\Uif\ClienteUifOrigenPcSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Jurosh\PDFMerge\PDFMerger;
use Carbon\Carbon;
use DB;

class Cliente_Premio_UifController extends Controller
{
    private $cliente_uifRepository;
    private $cliente_premio_uifRepository;
    private $empresaRepository;
    private $salaRepository;
    private $monedaRepository;
    private $formapagoRepository;
    private $juego_uifRepository;
    private $cliente_uifService;

	public function __construct(Cliente_UifRepositoryInterface $cliente_uifrepository,
                                Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                SalaRepositoryInterface $salarepository,
                                MonedaRepositoryInterface $monedarepository,
                                FormapagoRepositoryInterface $formapagorepository,
                                Juego_UifRepositoryInterface $juego_uifrepository,
                                Cliente_UifService $cliente_uifservice)
    {
        $this->cliente_uifRepository = $cliente_uifrepository;
        $this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
        $this->empresaRepository = $empresarepository;
        $this->salaRepository = $salarepository;
        $this->monedaRepository = $monedarepository;
        $this->formapagoRepository = $formapagorepository;
        $this->juego_uifRepository = $juego_uifrepository;
        $this->cliente_uifService = $cliente_uifservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cliente-premio-uif');

        $filtros = ClientePremioUifListadoFiltros::resolverDesdeRequest($request);
        $cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($filtros, true);

        return view('uif.cliente_premio_uif.index', [
            'cliente_premio_uifs' => $cliente_premio_uifs,
            'busqueda' => $filtros['busqueda'] ?? '',
            'filtros' => $filtros,
            'filtrosQuery' => ClientePremioUifListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClientePremioUifListadoFiltros::CAMPOS,
            'empresa_query' => ClienteUifOrigenPcSupport::empresasUifAsignadas(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-premio-uif');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClientePremioUifListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($filtros, false);
                $subtituloFiltros = ClientePremioUifListadoFiltros::subtituloFiltros($filtros);

                $view = \View::make('uif.cliente_premio_uif.listado', compact('cliente_premio_uifs', 'subtituloFiltros'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_cliente_premio_uif';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new Cliente_Premio_UifExport($this->cliente_premio_uifRepository))
                    ->parametros($filtros)
                    ->download('cliente_premio_uif.xlsx');

            case 'CSV':
                return (new Cliente_Premio_UifExport($this->cliente_premio_uifRepository))
                    ->parametros($filtros, true)
                    ->download('cliente_premio_uif.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consulta_cliente_premio_uif', ClientePremioUifListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request, $cliente_uif_id = null)
    {
        can('crear-cliente-premio-uif');

        $cliente_uif = $this->cliente_uifRepository->find($cliente_uif_id);
        try {
            \App\Support\Uif\ClienteUifOrigenPcSupport::assertClienteOperableEnPc($cliente_uif, $request);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('consulta_cliente_uif')
                ->with('mensaje-error', $e->getMessage());
        }

        $referer = $this->refererPremioDesdeClienteUif($request, $cliente_uif_id ? (int) $cliente_uif_id : null);
        $volverAClienteUif = $this->premioInvocadoDesdeClienteUif($request, $cliente_uif_id ? (int) $cliente_uif_id : null);
        $nombrecliente = '';
        $numerodocumento = '';
        if ($cliente_uif)
        {
            $nombrecliente = $cliente_uif->nombre;
            $numerodocumento = $cliente_uif->numerodocumento;
        }
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sala_query = $this->salaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $piderecibopago_enum = Cliente_Premio_Uif::$enumPideReciboPago;

        $essupervisor = esSupervisorUif() ? 'S' : 'N';
        $cumplimientoUif = $this->cumplimientoClienteUif($cliente_uif);

        return view('uif.cliente_premio_uif.crear', compact('nombrecliente', 'numerodocumento', 'moneda_query', 'juego_uif_query', 'sala_query',
                                                            'empresa_query', 'formapago_query', 'essupervisor',
                                                            'piderecibopago_enum', 'cliente_uif_id', 'referer', 'volverAClienteUif',
                                                            'cumplimientoUif'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente_Premio_Uif $request)
    {
        can('crear-cliente-premio-uif');

        if ($request->filled('empresa_id')) {
            session(['empresa_id' => $request->empresa_id]);
        }

        $result = $this->cliente_uifService->guardaCliente_Premio_Uif($request);

        if (isset($result['errores'])) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['errores' => $result['errores']])
                ->with('mensaje-error', 'No se pudo grabar el premio: '.$result['errores']);
        }

        if (str_contains((string) $request->referer, 'cliente_uif')) {
            return redirect($request->referer)->with('mensaje', 'Premio creado con éxito');
        }

        return redirect('uif/premio_uif')->with('mensaje', 'Premio creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id, $origen = null)
    {
        can('editar-cliente-premio-uif');

        if (!isset($origen))
            $origen = 'cliente_uif';

		$data = $this->cliente_premio_uifRepository->find($id);

        $referer = $this->refererPremioDesdeClienteUif($request, $data ? (int) $data->cliente_uif_id : null);
        $volverAClienteUif = $this->premioInvocadoDesdeClienteUif($request, $data ? (int) $data->cliente_uif_id : null);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $sala_query = $this->salaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $piderecibopago_enum = Cliente_Premio_Uif::$enumPideReciboPago;

        $essupervisor = esSupervisorUif() ? 'S' : 'N';
        $clientePremio = $data ? $data->clientes_uif : null;
        $cumplimientoUif = $this->cumplimientoClienteUif($clientePremio);
//dd($data);
        return view('uif.cliente_premio_uif.editar', compact('data', 
                                                    'moneda_query', 'juego_uif_query', 'sala_query',
                                                    'empresa_query', 'formapago_query',
                                                    'essupervisor', 'piderecibopago_enum', 'referer', 'volverAClienteUif',
                                                    'cumplimientoUif'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente_Premio_Uif $request, $id)
    {
        can('actualizar-cliente-premio-uif');

        if ($request->filled('empresa_id')) {
            session(['empresa_id' => $request->empresa_id]);
        }

        $result = $this->cliente_uifService->actualizaCliente_Premio_Uif($request, $id);

        if (isset($result['errores'])) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['errores' => $result['errores']])
                ->with('mensaje-error', 'No se pudo actualizar el premio: '.$result['errores']);
        }

        if (str_contains((string) $request->referer, 'cliente_uif')) {
            return redirect($request->referer)->with('mensaje', 'Premio actualizado con éxito');
        }

        if (str_contains((string) $request->referer, 'exportaoperacion')) {
            $fechaEntrega = (string) $request->fechaentrega;
            $periodo = normalizarPeriodoParaUrl(
                substr($fechaEntrega, 5, 2).'-'.substr($fechaEntrega, 0, 4)
            );
            $limiteinformeuif = config('uif.LIMITE_INFORME_UIF');
            $premioModel = $this->cliente_premio_uifRepository->find($id);
            $empresaId = (int) optional($premioModel->salas)->empresa_id;

            if ($empresaId <= 0) {
                return redirect()
                    ->route('crear_exporta_operacion')
                    ->with('mensaje-error', 'No se pudo determinar la empresa del premio para volver al listado UIF.');
            }

            return redirect()->route('listado_exporta_operacion_uif', [
                'periodo' => $periodo,
                'limiteinformeuif' => $limiteinformeuif,
                'empresa_id' => $empresaId,
            ])->with('mensaje', 'Premio actualizado con éxito');
        }

        return redirect('uif/premio_uif')->with('mensaje', 'Premio actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-cliente-premio-uif');

        $referer = $request->header('referer');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

            ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused((string) ($cliente_premio_uif->foto ?? ''));

			if ($this->cliente_premio_uifRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cliente_premio_uifRepository->delete($id))
                $mensaje = 'Premio borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect($referer)->with('mensaje', $mensaje);
        }
    }

    public function eliminarExterno(Request $request)
    {
        can('borrar-cliente-premio-uif');

        $id = $request->id;

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

            ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused((string) ($cliente_premio_uif->foto ?? ''));

			if ($this->cliente_premio_uifRepository->delete($id))
            {
				$fl_borro = true;
            }

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cliente_premio_uifRepository->delete($id))
                $mensaje = 'Premio borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect($referer)->with('mensaje', $mensaje);
        }
    }

    public function mostrarFoto(Request $request, $id)
    {
        can('editar-cliente-premio-uif');

		$data = $this->cliente_premio_uifRepository->find($id);

        $referer = $request->header('referer');

        return view('uif.cliente_premio_uif.mostrar_foto', compact('data', 'referer'));
    }

    /** Sirve adjunto de premio desde /scan (o legacy local). */
    public function mostrarArchivo($id, $archivo)
    {
        if (! can('editar-cliente-premio-uif', false) && ! can('listar-cliente-premio-uif', false) && ! can('editar-cliente-uif', false)) {
            abort(403);
        }

        $premio = $this->cliente_premio_uifRepository->find($id);
        if ($premio === null) {
            abort(404);
        }

        $path = ClienteUifArchivoStorage::absolutePremioAdjunto((int) $id, (string) $archivo);
        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        if (request()->query('disposition') === 'attachment') {
            return response()->download($path, basename($path));
        }

        return response()->file($path);
    }

    /** Sirve foto pago_* / foto ERP desde /scan (o legacy). */
    public function mostrarFotoArchivo($archivo)
    {
        if (! can('editar-cliente-premio-uif', false) && ! can('listar-cliente-premio-uif', false) && ! can('editar-cliente-uif', false) && ! can('listar-cliente-uif', false)) {
            abort(403);
        }

        $path = ClienteUifArchivoStorage::absoluteFotoPremio((string) $archivo);
        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function listarUnPremio(Request $request, $id)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $cliente_premio_uif = Cliente_Premio_Uif::with([
                                    'clientes_uif.tipodocumentos',
                                    'clientes_uif.localidades_uif',
                                    'clientes_uif.provincias_uif',
                                    'clientes_uif.paises_uif',
                                    'clientes_uif.localidad_nacimientos',
                                    'clientes_uif.provincia_nacimientos',
                                    'clientes_uif.pais_nacimientos',
                                    'clientes_uif.actividades_uif',
                                    'clientes_uif.estadociviles_uif',
                                    'clientes_uif.peps_uif',
                                    'clientes_uif.sos_uif',
                                    'cliente_premio_archivos_uif',
                                    'salas.empresas',
                                    'juegos_uif',
                                    'monedas',
                                    'formapagos',
                                    'usuarios',
                                ])->findOrFail($id);

        $cliente = $cliente_premio_uif->clientes_uif;

        $congelado = false;
        if ($cliente && ($cliente->numerodocumento ?? '') !== '') {
            $congelado = Cliente_Congelado_Uif::where('numerodocumento', $cliente->numerodocumento)->exists();
        }

        $fotodocumentoPath = ($cliente && ($cliente->fotodocumento ?? '') !== '')
            ? ClienteUifFotoDocumento::absolutePathForCliente(
                $cliente->fotodocumento,
                (string) $cliente->numerodocumento,
                $cliente->inroclienteid !== null ? (int) $cliente->inroclienteid : null
            )
            : null;

        $path = storage_path('pdf/cliente_premio_uif');
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        $view = \View::make('exports.uif.cliente_premio_uif', compact(
                            'cliente_premio_uif', 'congelado', 'fotodocumentoPath'
                        ))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $nombre_pdf = 'cliente_premio_uif-'.$id.'.pdf';
        $rutaPdfPremio = $path.'/'.$nombre_pdf;
        $pdf->loadHTML($view)->save($rutaPdfPremio);

        $rutaInformeUif = storage_path('app/public/imagenes/informe_uif.pdf');
        if (is_file($rutaInformeUif)) {
            try {
                $merger = new PDFMerger;
                $merger->addPDF($rutaPdfPremio, 'all', 'vertical')
                    ->addPDF($rutaInformeUif, 'all', 'vertical');
                $pdfFusionado = $merger->merge('string', $nombre_pdf);
                file_put_contents($rutaPdfPremio, $pdfFusionado);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->download($rutaPdfPremio);
    }

    /**
     * Avisos de documentación faltante del cliente, mismos textos que el ABM.
     *
     * @return array{items: list<array{texto: string, tab: string, selector: string}>, titulo: string, subtitulo: string, claseBanner: string, urlsTab?: array<string, string>}
     */
    private function cumplimientoClienteUif(?Cliente_Uif $cliente): array
    {
        if (! $cliente || (int) ($cliente->id ?? 0) <= 0) {
            return [
                'items' => [],
                'titulo' => 'Faltan documentos o firmas UIF',
                'subtitulo' => '',
                'claseBanner' => 'is-danger',
            ];
        }

        $this->cliente_uifRepository->sincronizarArchivosAnitaSiCorresponde($cliente);
        $cliente->load('cliente_archivos_uif');

        $eval = ClienteUifCumplimientoSupport::evaluar($cliente, esSupervisorUif());
        if ($eval['items'] !== []) {
            $eval['urlsTab'] = ClienteUifCumplimientoSupport::urlsFichaCliente((int) $cliente->id);
        }

        return $eval;
    }

    /**
     * Alta/edición de premio abierta desde el CRUD cliente con ?return_cliente_tab= (botón form3 u otros).
     */
    private function premioInvocadoDesdeClienteUif(Request $request, ?int $cliente_uif_id): bool
    {
        $tabCliente = $request->query('return_cliente_tab');
        if ($tabCliente === null || ! $cliente_uif_id || ! is_numeric($tabCliente)) {
            return false;
        }
        $t = (int) $tabCliente;

        return $t >= 1 && $t <= 5;
    }

    /**
     * URL de retorno al formulario oculto "referer": edición del cliente UIF en solapa concreta si viene return_cliente_tab.
     */
    private function refererPremioDesdeClienteUif(Request $request, ?int $cliente_uif_id): ?string
    {
        $referer = $request->header('referer');
        $tabCliente = $request->query('return_cliente_tab');
        if ($tabCliente !== null && $cliente_uif_id && is_numeric($tabCliente)) {
            $t = (int) $tabCliente;
            if ($t >= 1 && $t <= 5) {
                $params = ['uif_tab' => $t];
                if ($request->query('origen') === 'modal_consulta') {
                    $params['origen'] = 'modal_consulta';
                }
                if ($request->input('vista') === 'consulta') {
                    $params['vista'] = 'consulta';
                }

                return route('edita_cliente_uif', ['id' => $cliente_uif_id]).'?'.http_build_query($params);
            }
        }

        return $referer;
    }
}
