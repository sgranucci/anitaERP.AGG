<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrecarga_Comprobante_Proveedor;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\OrdencompraLegajoFacturaPdfService;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use App\Services\Compras\PrecargaComprobanteMarcarCargadaAnitaService;
use App\Services\Compras\ComprobanteProveedorPdfIaService;
use App\Support\Compras\PrecargaRecepcionErrorRegistrar;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraLegajoFacturaArcaSupport;
use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteProveedorListadoFiltros;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Precarga_Comprobante_ProveedorController extends Controller
{
	private $precarga_comprobante_proveedorRepository;
    private $precarga_comprobante_proveedor_conceptoRepository;
    private $tipotransaccion_compraRepository;
    protected $concepto_ivacompraRepository;
    private $empresaRepository;
    private MonedaRepositoryInterface $monedaRepository;
    private PrecargaComprobanteAnitaSyncService $precargaAnitaSync;

    private PrecargaFacturaScanPathResolver $facturaScanPathResolver;

    private ComprobanteProveedorPdfIaService $pdfIaService;

    private PrecargaComprobanteMarcarCargadaAnitaService $marcarCargadaAnitaService;

    private OrdencompraLegajoFacturaPdfService $legajoFacturaPdfService;

	public function __construct(Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                MonedaRepositoryInterface $monedaRepository,
                                Tipotransaccion_CompraRepositoryInterface $tipotransaccion_comprarepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
                                PrecargaComprobanteAnitaSyncService $precargaAnitaSync,
                                PrecargaFacturaScanPathResolver $facturaScanPathResolver,
                                ComprobanteProveedorPdfIaService $pdfIaService,
                                PrecargaComprobanteMarcarCargadaAnitaService $marcarCargadaAnitaService,
                                OrdencompraLegajoFacturaPdfService $legajoFacturaPdfService,
                                )
    {
        $this->precarga_comprobante_proveedorRepository = $precarga_comprobante_proveedorRepository;
        $this->precarga_comprobante_proveedor_conceptoRepository = $precarga_comprobante_proveedor_conceptoRepository;
        $this->empresaRepository = $empresaRepository;
        $this->monedaRepository = $monedaRepository;
        $this->tipotransaccion_compraRepository = $tipotransaccion_comprarepository;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
        $this->precargaAnitaSync = $precargaAnitaSync;
        $this->facturaScanPathResolver = $facturaScanPathResolver;
        $this->pdfIaService = $pdfIaService;
        $this->marcarCargadaAnitaService = $marcarCargadaAnitaService;
        $this->legajoFacturaPdfService = $legajoFacturaPdfService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-precarga-proveedores');

        $filtros = PrecargaComprobanteProveedorListadoFiltros::resolverDesdeRequest($request);

        $precarga = $this->precarga_comprobante_proveedorRepository->leePrecargaComprobanteProveedor($filtros, true);

        return view('compras.precarga_comprobante_proveedor.index', [
            'datas' => $precarga,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => PrecargaComprobanteProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PrecargaComprobanteProveedorListadoFiltros::CAMPOS,
            'pdfIaHabilitado' => (bool) config('comprobante_proveedor_pdf_ia.habilitado'),
        ]);
    }

    public function previewPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'numero_oc' => 'nullable|string|max:12',
            'tipo_abreviatura' => 'nullable|string|max:10',
        ]);

        try {
            $preview = $this->pdfIaService->preview(
                $request->file('pdf'),
                $request->input('numero_oc'),
                false,
                true,
                $request->input('tipo_abreviatura'),
            );

            if (! ($preview['ok'] ?? false)) {
                PrecargaRecepcionErrorRegistrar::desdePdfIa(
                    'preview',
                    (string) ($preview['message'] ?? 'Error en preview PDF+IA'),
                    422,
                    [
                        'numero_oc' => $request->input('numero_oc'),
                        'oc_requerida' => $preview['oc_requerida'] ?? false,
                    ],
                    $request->file('pdf')?->getClientOriginalName()
                );
            }

            return response()->json($preview, ($preview['ok'] ?? false) ? 200 : 422);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'preview',
                $e->getMessage(),
                422,
                ['numero_oc' => $request->input('numero_oc')],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'preview',
                'Error al procesar PDF con IA: '.$e->getMessage(),
                500,
                ['numero_oc' => $request->input('numero_oc')],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => 'Error al procesar PDF con IA.'], 500);
        }
    }

    public function resolverOcPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'extraccion' => 'required|json',
            'numero_oc' => 'required|string|max:12',
        ]);

        $extraccion = json_decode((string) $request->input('extraccion'), true);
        if (! is_array($extraccion)) {
            return response()->json(['ok' => false, 'message' => 'Extracción inválida.'], 422);
        }

        try {
            $preview = $this->pdfIaService->resolverConOcManual(
                $extraccion,
                (string) $request->input('numero_oc')
            );

            return response()->json($preview);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'resolver_oc',
                $e->getMessage(),
                422,
                [
                    'numero_oc' => $request->input('numero_oc'),
                    'cuit_proveedor' => $extraccion['cuit_proveedor'] ?? null,
                    'cuit_empresa' => $extraccion['cuit_empresa'] ?? null,
                ]
            );

            return response()->json([
                'ok' => false,
                'oc_requerida' => true,
                'message' => $e->getMessage(),
                'extraccion' => $extraccion,
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'resolver_oc',
                'Error al resolver con OC: '.$e->getMessage(),
                500,
                ['numero_oc' => $request->input('numero_oc')]
            );

            return response()->json(['ok' => false, 'message' => 'Error al resolver con OC.'], 500);
        }
    }

    public function resolverSinOcPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'extraccion' => 'required|json',
            'tipo_abreviatura' => 'nullable|string|max:10',
        ]);

        $extraccion = json_decode((string) $request->input('extraccion'), true);
        if (! is_array($extraccion)) {
            return response()->json(['ok' => false, 'message' => 'Extracción inválida.'], 422);
        }

        try {
            $preview = $this->pdfIaService->resolverSinOc(
                $extraccion,
                true,
                $request->input('tipo_abreviatura'),
            );

            return response()->json($preview);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'resolver_sin_oc',
                $e->getMessage(),
                422,
                [
                    'cuit_proveedor' => $extraccion['cuit_proveedor'] ?? null,
                    'cuit_empresa' => $extraccion['cuit_destinatario'] ?? $extraccion['cuit_empresa'] ?? null,
                ]
            );

            return response()->json([
                'ok' => false,
                'oc_requerida' => true,
                'permite_continuar_sin_oc' => ComprobanteProveedorPdfIaService::errorPermiteReintentarTipo($e->getMessage()),
                'message' => $e->getMessage(),
                'extraccion' => $extraccion,
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'resolver_sin_oc',
                'Error al resolver sin OC: '.$e->getMessage(),
                500,
                []
            );

            return response()->json(['ok' => false, 'message' => 'Error al resolver sin OC.'], 500);
        }
    }

    public function confirmarPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'payload' => 'required|json',
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $payload = json_decode((string) $request->input('payload'), true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'message' => 'Payload inválido.'], 422);
        }

        try {
            $resultado = $this->pdfIaService->confirmar($payload, $request->file('pdf'));
            $precargaId = (int) $resultado['precarga_id'];
            $mensaje = (string) ($resultado['message'] ?? 'Precarga registrada desde PDF+IA.');
            // La precarga queda registrada. El alta de la factura se abre precargada:
            // sin OC falta la cuenta de gasto; Contabilizar termina la grabación.
            $paramsAlta = ['precarga_id' => $precargaId];
            $retorno = trim((string) $request->input('retorno', ''));
            if (in_array($retorno, [
                ComprobanteProveedorRetornoLegajoSupport::ORIGEN_PRECARGA,
                ComprobanteProveedorRetornoLegajoSupport::ORIGEN_COMPROBANTE,
            ], true)) {
                $paramsAlta['origen'] = $retorno;
            }
            $redirect = can('crear-comprobante-proveedor', false)
                ? route('crear_comprobante_proveedor', $paramsAlta)
                : route('editar_precarga_comprobante_proveedor', ['id' => $precargaId]);
            if (can('crear-comprobante-proveedor', false)) {
                $mensaje .= ' Se abrió la carga de la factura. Sin OC indique la cuenta de gasto y pulse Contabilizar.';
            }

            return response()->json([
                'ok' => true,
                'precarga_id' => $precargaId,
                'message' => $mensaje,
                'redirect' => $redirect,
            ]);
        } catch (RuntimeException $e) {
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'confirmar',
                $e->getMessage(),
                422,
                [
                    'numero_oc' => $payload['numero_oc'] ?? $payload['numeroordencompra'] ?? null,
                    'cuit_proveedor' => $payload['cuit_proveedor'] ?? null,
                    'cuit_empresa' => $payload['cuit_empresa'] ?? null,
                    'tipo' => $payload['tipo_abreviatura'] ?? $payload['tipo'] ?? null,
                ],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            PrecargaRecepcionErrorRegistrar::desdePdfIa(
                'confirmar',
                'Error al grabar precarga desde PDF+IA: '.$e->getMessage(),
                500,
                [
                    'numero_oc' => $payload['numero_oc'] ?? $payload['numeroordencompra'] ?? null,
                ],
                $request->file('pdf')?->getClientOriginalName()
            );

            return response()->json(['ok' => false, 'message' => 'Error al grabar precarga desde PDF+IA.'], 500);
        }
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-precarga-proveedores');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PrecargaComprobanteProveedorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $precarga = $this->precarga_comprobante_proveedorRepository->leePrecargaComprobanteProveedor($filtros, false);

                $view = \View::make('compras.precarga_comprobante_proveedor.listado', compact('precarga'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_precarga_comprobante_proveedor';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new CapexExport($this->precarga_comprobante_proveedorRepository))
                    ->parametros($filtros)
                    ->download('precarga_comprobante_proveedor.xlsx');

            case 'CSV':
                return (new CapexExport($this->precarga_comprobante_proveedorRepository))
                    ->parametros($filtros)
                    ->download('precarga_comprobante_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'precarga_comprobante_proveedor',
            PrecargaComprobanteProveedorListadoFiltros::paraQueryString($filtros)
        );
    }

    public function verFacturaPdf(Request $request, int $id): BinaryFileResponse
    {
        if (! puedeVerPrecargaFacturaPdf()) {
            abort(403, 'No tiene permiso para ver el PDF de la precarga.');
        }

        $precarga = $this->precarga_comprobante_proveedorRepository->find($id);
        $path = $this->facturaScanPathResolver->resolve($precarga->rutaalmacenamiento);

        if ($path === null) {
            abort(404, 'No se encontró el PDF en /Facturas_scan/comprobantes ni comprobantes-revisar para: '.$precarga->rutaalmacenamiento);
        }

        $nombre = basename($path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-precarga-proveedores');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipotransaccion_compra_query = $this->tipotransaccion_compraRepository->all('*');
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $retornoListadoQuery = $this->queryRetornoListado($request);
        $data = new Precarga_Comprobante_Proveedor;

        return view('compras.precarga_comprobante_proveedor.crear', compact(
            'empresa_query',
            'tipotransaccion_compra_query',
            'concepto_ivacompra_query',
            'moneda_query',
            'retornoListadoQuery',
            'data'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPrecarga_Comprobante_Proveedor $request)
    {
        if ($motivo = $this->motivoTipoProrrateado($request)) {
            return redirect()->back()->withInput()->with('errores', [$motivo]);
        }

        if ($errores = $this->erroresCargaManual($request, null)) {
            return redirect()->back()->withInput()->with('errores', $errores);
        }

        try {
            DB::transaction(function () use ($request) {
                $payload = $this->payloadCargaManual($request, null);
                $payload['origen_entrada'] = \App\Support\Compras\PrecargaComprobanteOrigenEntrada::MANUAL;
                $payload['estado'] = PrecargaComprobanteEstados::PENDIENTE;
                $precarga = $this->precarga_comprobante_proveedorRepository->create($payload);

                $this->guardarConceptosPrecarga($request, (int) $precarga->id);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo guardar la precarga: '.$e->getMessage()]);
        }

        return $this->redirectIndexConMensaje($request, 'Precarga guardada y sincronizada con Anita.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';

        if ($soloConsulta) {
            // Mails de aviso (cotización, etc.) abren esta URL. Destinatarios (pagos/admin)
            // suelen poder listar comprobantes/OC pero no editar precarga: sin este bypass
            // can() redirige a Inicio y la pantalla queda vacía.
            if (! puedeVerPrecargaFacturaPdf()) {
                can('listar-precarga-proveedores');
            }
        } else {
            can('editar-precarga-proveedores');
        }

        $data = $this->precarga_comprobante_proveedorRepository->find($id);
        $data->loadMissing(['provinciaDestino', 'comprobante_proveedor:id,precarga_comprobante_proveedor_id,estado,letra,sucursal,numerocomprobante']);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipotransaccion_compra_query = $this->tipotransaccion_compraRepository->all('*');
        $ocPrecarga = PrecargaProveedorProrrateoMultiCcSupport::ordencompraDesdeNumero(
            (string) ($data->numeroordencompra ?? '')
        );
        $bloquearTiposProrrateados = $ocPrecarga !== null
            && ! PrecargaProveedorProrrateoMultiCcSupport::ocTieneMultiplesCentrosDestino($ocPrecarga);
        if ($bloquearTiposProrrateados) {
            $tipotransaccion_compra_query = $tipotransaccion_compra_query->reject(
                static fn ($tipo): bool => PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado(
                    (string) ($tipo->abreviatura ?? '')
                )
            );
        }
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $puedeActualizar = can('actualizar-precarga-proveedores', false);
        $soloLectura = $soloConsulta && ! $puedeActualizar;
        $retornoListadoQuery = $this->queryRetornoListado($request);

        return view('compras.precarga_comprobante_proveedor.editar', compact(
            'data',
            'empresa_query',
            'tipotransaccion_compra_query',
            'concepto_ivacompra_query',
            'moneda_query',
            'soloConsulta',
            'soloLectura',
            'puedeActualizar',
            'retornoListadoQuery',
            'bloquearTiposProrrateados'
        ));
    }

    private function motivoTipoProrrateado(Request $request): ?string
    {
        $tipoId = (int) $request->input('tipotransaccion_compra_id', 0);
        if ($tipoId <= 0) {
            return null;
        }
        $abrev = (string) (Tipotransaccion_Compra::query()->whereKey($tipoId)->value('abreviatura') ?? '');
        $numero = trim((string) $request->input('numeroordencompra', ''));
        $oc = $numero !== ''
            ? PrecargaProveedorProrrateoMultiCcSupport::ordencompraDesdeNumero($numero)
            : null;

        return PrecargaProveedorProrrateoMultiCcSupport::motivoRechazoAsignacionManual($abrev, $oc);
    }

    public function marcarCargadaEnAnita(Request $request, int $id)
    {
        can('editar-precarga-proveedores');

        try {
            $resultado = $this->marcarCargadaAnitaService->marcar($id);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('errores', [$e->getMessage()]);
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok', 'detalle' => $resultado['mensaje']]);
        }

        return $this->redirectIndexConMensaje($request, $resultado['mensaje']);
    }

    public function detectarCargadasEnAnita(Request $request)
    {
        can('editar-precarga-proveedores');

        try {
            $resultado = $this->marcarCargadaAnitaService->detectarYMarcarPendientes(
                40,
                $this->empresaRepository->traeEmpresasAsignadas()
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('precarga_comprobante_proveedor', $this->queryRetornoListado($request))
                ->with('errores', [$e->getMessage()]);
        }

        $params = $this->queryRetornoListado($request);
        if (($resultado['marcadas'] ?? 0) > 0) {
            unset($params['estado'], $params['estado_todas']);
            $params['estado'] = PrecargaComprobanteEstados::CARGADA_ANITA;
        }

        return redirect()
            ->route('precarga_comprobante_proveedor', $params)
            ->with('mensaje', $resultado['mensaje']);
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPrecarga_Comprobante_Proveedor $request, $id)
    {
        can('actualizar-precarga-proveedores');

        $actual = Precarga_Comprobante_Proveedor::query()->whereKey($id)->value('estado');
        if ((string) $actual === PrecargaComprobanteEstados::CARGADA_ANITA) {
            return redirect()
                ->back()
                ->with('errores', ['Esta precarga ya está marcada como cargada en Anita y no se puede modificar.']);
        }

        if ($motivo = $this->motivoTipoProrrateado($request)) {
            return redirect()->back()->withInput()->with('errores', [$motivo]);
        }

        $precargaActual = Precarga_Comprobante_Proveedor::query()->find($id);
        if (! $precargaActual) {
            return redirect()->back()->with('errores', ['No se encontró la precarga.']);
        }

        if ($errores = $this->erroresCargaManual($request, $precargaActual->rutaalmacenamiento)) {
            return redirect()->back()->withInput()->with('errores', $errores);
        }

        try {
            DB::transaction(function () use ($request, $id, $precargaActual) {
                $payload = $this->payloadCargaManual($request, $precargaActual->rutaalmacenamiento);
                $this->precarga_comprobante_proveedorRepository->update($payload, $id);

                $this->precarga_comprobante_proveedor_conceptoRepository
                    ->deletePorPrecargaComprobanteProveedor($id);

                $this->guardarConceptosPrecarga($request, (int) $id);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo actualizar la precarga en Anita: '.$e->getMessage()]);
        }

        return $this->redirectIndexConMensaje($request, 'Precarga actualizada y sincronizada con Anita.');
    }

    /**
     * Datos de la factura en la precarga manual. Conceptos y totales pueden ir vacíos.
     * La orden de compra es opcional (factura de servicio).
     *
     * @return list<string>
     */
    private function erroresCargaManual(Request $request, ?string $rutaExistente): array
    {
        $errores = [];

        if ((int) $request->input('empresa_id') <= 0) {
            $errores[] = 'Indicá la empresa.';
        }
        if ((int) $request->input('proveedor_id') <= 0) {
            $errores[] = 'Indicá el proveedor.';
        }
        if ((int) $request->input('tipotransaccion_compra_id') <= 0) {
            $errores[] = 'Indicá el tipo de transacción.';
        }

        $letra = OrdencompraLegajoFacturaArcaSupport::normalizarLetra((string) $request->input('letra'));
        if (! in_array($letra, ['A', 'B', 'C', 'M', 'E'], true)) {
            $errores[] = 'Indicá la letra (A, B, C, M o E).';
        }

        $sucursalRaw = trim((string) $request->input('sucursal', ''));
        if ($sucursalRaw === '' || ! ctype_digit($sucursalRaw)) {
            $errores[] = 'Indicá la sucursal.';
        }

        $numero = (int) $request->input('numerocomprobante');
        if ($numero <= 0) {
            $errores[] = 'Indicá el número de comprobante.';
        }

        $fecha = trim((string) $request->input('fechafactura'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) {
            $errores[] = 'Indicá la fecha del comprobante.';
        }

        $pdf = $request->file('factura_pdf');
        if (! $pdf && ! filled($rutaExistente)) {
            $errores[] = 'Adjuntá el PDF de la factura.';
        } elseif ($pdf) {
            $ext = strtolower((string) $pdf->getClientOriginalExtension());
            $mime = strtolower((string) $pdf->getMimeType());
            if (! $pdf->isValid() || ($ext !== 'pdf' && ! str_contains($mime, 'pdf'))) {
                $errores[] = 'El scan tiene que ser un PDF.';
            } elseif ($pdf->getSize() > 20 * 1024 * 1024) {
                $errores[] = 'El PDF supera los 20 MB.';
            }
        }

        return $errores;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadCargaManual(Request $request, ?string $rutaExistente): array
    {
        $payload = $this->precargaAnitaSync->enriquecerPayloadParaAnita(
            $request->except(['factura_pdf'])
        );
        $payload['letra'] = OrdencompraLegajoFacturaArcaSupport::normalizarLetra((string) ($payload['letra'] ?? ''));
        $payload['sucursal'] = (int) ($payload['sucursal'] ?? 0);
        $payload['numerocomprobante'] = (int) ($payload['numerocomprobante'] ?? 0);
        $payload['numeroordencompra'] = trim((string) ($payload['numeroordencompra'] ?? ''));

        $pdf = $request->file('factura_pdf');
        if ($pdf) {
            $proveedor = Proveedor::query()->find((int) $request->input('proveedor_id'));
            if (! $proveedor) {
                throw new RuntimeException('Proveedor inexistente para guardar el scan.');
            }

            $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos((int) $proveedor->id, null);
            $duplicada = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
                (int) $request->input('empresa_id'),
                (int) $request->input('tipotransaccion_compra_id'),
                $payload['letra'],
                $payload['sucursal'],
                $payload['numerocomprobante'],
                $cuit,
                $request->route('id') ? (int) $request->route('id') : null,
            );
            if ($duplicada) {
                throw new RuntimeException(
                    'Ya existe una precarga con esa factura'
                    .($duplicada->numeroordencompra ? ' (OC '.$duplicada->numeroordencompra.')' : '')
                    .'.'
                );
            }

            $abrev = (string) (Tipotransaccion_Compra::query()
                ->whereKey((int) $request->input('tipotransaccion_compra_id'))
                ->value('abreviatura') ?? 'FAC');

            $payload['rutaalmacenamiento'] = $this->legajoFacturaPdfService->publicarPdfFactura(
                $pdf,
                $proveedor,
                (string) $request->input('fechafactura'),
                $abrev,
                $payload['letra'],
                $payload['sucursal'],
                $payload['numerocomprobante'],
            );
        } elseif (filled($rutaExistente)) {
            $payload['rutaalmacenamiento'] = $rutaExistente;
        }

        return $payload;
    }

    private function guardarConceptosPrecarga(Request $request, int $precargaId): void
    {
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::lineasDesdeArrays(
            $request->input('concepto_ivacompra_ids', []),
            $request->input('montos', []),
        );
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::enriquecerCodigosAnita(
            ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas)
        );

        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }

            $concepto = $this->concepto_ivacompraRepository->find($conceptoId);
            if (! $concepto) {
                throw new RuntimeException('Concepto IVA compra id «'.$conceptoId.'» inexistente.');
            }

            $this->precarga_comprobante_proveedor_conceptoRepository->create([
                'precarga_comprobante_proveedor_id' => $precargaId,
                'concepto_ivacompra_id' => $concepto->id,
                'codigo_concepto_anita' => $linea['codigo_concepto_anita'] ?? $concepto->codigo,
                'monto' => $linea['monto'] ?? 0,
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-precarga-proveedores');

        if (! $request->ajax()) {
            abort(404);
        }

        try {
            $fl_borro = (bool) $this->precarga_comprobante_proveedorRepository->delete($id);
        } catch (\Throwable $e) {
            return response()->json([
                'mensaje' => 'ng',
                'error' => $e->getMessage(),
            ], 422);
        }

        if ($fl_borro) {
            return response()->json(['mensaje' => 'ok']);
        }

        return response()->json(['mensaje' => 'ng', 'error' => 'No se pudo eliminar la precarga.']);
    }

    /**
     * @return array<string, string|int>
     */
    private function queryRetornoListado(Request $request): array
    {
        return QueryRetornoListado::desdeRequestSiIndex($request, PrecargaComprobanteProveedorListadoFiltros::class);
    }

    private function redirectIndexConMensaje(Request $request, string $mensaje)
    {
        return redirect()
            ->route('precarga_comprobante_proveedor', $this->queryRetornoListado($request))
            ->with('mensaje', $mensaje);
    }
}
