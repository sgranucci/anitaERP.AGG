<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCheque;
use App\Exports\Caja\ChequeAgingExport;
use App\Exports\Caja\ChequeDepositoConciliacionExport;
use App\Exports\Caja\ChequeDepositoHistorialExport;
use App\Exports\Caja\ChequeListadoExport;
use App\Exports\Caja\ChequeReporteExport;
use App\Models\Caja\Cheque;
use App\Models\Caja\Chequera;
use App\Models\Caja\Cuentacaja;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Caja\ChequeCaucionService;
use App\Services\Caja\ChequeDepositoService;
use App\Services\Caja\ChequeEcheqService;
use App\Services\Caja\ChequeIngresoMasivoService;
use App\Services\Caja\ChequeRechazadoNotaDebitoService;
use App\Support\Caja\ChequeAgingListadoFiltros;
use App\Support\Caja\ChequeCarteraAgingSupport;
use App\Support\Caja\ChequeCarteraConsultaSupport;
use App\Support\Caja\ChequeCashflowSemanalSupport;
use App\Support\Caja\ChequeDepositoComprobanteSupport;
use App\Support\Caja\ChequeDepositoConciliacionFiltros;
use App\Support\Caja\ChequeDepositoConciliacionSupport;
use App\Support\Caja\ChequeDepositoHistorialFiltros;
use App\Support\Caja\ChequeDepositoHistorialSupport;
use App\Support\Caja\ChequeConsultaChequeraSupport;
use App\Support\Caja\ChequeListadoFiltros;
use App\Support\Caja\ChequeNdConfigSupport;
use App\Support\Caja\ChequeReporteFiltros;
use App\Support\Caja\ChequeReporteSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Caja\Echeq\ChequeEcheqProviderResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use InvalidArgumentException;

class ChequeController extends Controller
{
	private $repository;
    private $cuentacajaRepository;
    private $chequeraRepository;
    private $empresaRepository;
    private $monedaRepository;
    private ChequeRechazadoNotaDebitoService $chequeRechazadoNdService;
    private ChequeDepositoService $chequeDepositoService;
    private ChequeIngresoMasivoService $chequeIngresoMasivoService;
    private ChequeCaucionService $chequeCaucionService;
    private ChequeEcheqService $chequeEcheqService;

    public function __construct(ChequeRepositoryInterface $repository,
                                ChequeraRepositoryInterface $chequerarepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                EmpresaRepositoryInterface $empresarepository,
                                MonedaRepositoryInterface $monedarepository,
                                ChequeRechazadoNotaDebitoService $chequeRechazadoNdService,
                                ChequeDepositoService $chequeDepositoService,
                                ChequeIngresoMasivoService $chequeIngresoMasivoService,
                                ChequeCaucionService $chequeCaucionService,
                                ChequeEcheqService $chequeEcheqService)
    {
        $this->repository = $repository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->chequeraRepository = $chequerarepository;
        $this->empresaRepository = $empresarepository;
        $this->monedaRepository = $monedarepository;
        $this->chequeRechazadoNdService = $chequeRechazadoNdService;
        $this->chequeDepositoService = $chequeDepositoService;
        $this->chequeIngresoMasivoService = $chequeIngresoMasivoService;
        $this->chequeCaucionService = $chequeCaucionService;
        $this->chequeEcheqService = $chequeEcheqService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cheque');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeCheque($filtros, true);
        $origen_enum = Cheque::$enumOrigen;
        $caracter_enum = Cheque::$enumCaracter;
        $estado_enum = Cheque::$enumEstado;
        $puede_nd_cheque = can('generar-nota-de-debito-cheque', false)
            && ChequeNdConfigSupport::habilitado();
        $puede_depositar_cheque = can('editar-cheque', false) || can('actualizar-cheque', false);
        $puede_caucionar_cheque = $puede_depositar_cheque;

        return view('caja.cheque.index', [
            'datas' => $datas,
            'origen_enum' => $origen_enum,
            'caracter_enum' => $caracter_enum,
            'estado_enum' => $estado_enum,
            'puede_nd_cheque' => $puede_nd_cheque,
            'puede_depositar_cheque' => $puede_depositar_cheque,
            'puede_caucionar_cheque' => $puede_caucionar_cheque,
            'filtros' => $filtros,
            'filtrosQuery' => ChequeListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ChequeListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cheque');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $origen_enum = Cheque::$enumOrigen;
        $estado_enum = Cheque::$enumEstado;

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCheque($filtros, false);

                $view = \View::make('caja.cheque.listado', compact('datas', 'origen_enum', 'estado_enum'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_cheque';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ChequeListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cheque.xlsx');

            case 'CSV':
                return (new ChequeListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('cheque.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('cheque', ChequeListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Reporte de cheques emitidos o recibidos, con criterios propios de cada origen.
     */
    public function reporte(Request $request)
    {
        can('listar-cheque');

        $filtros = $this->filtrosReporte($request);
        $consultado = $request->boolean('consultar');
        $datas = null;
        $totales = collect();
        $totalesPorDia = collect();
        if ($consultado) {
            $datas = ChequeReporteSupport::listar($filtros, $this->empresaRepository, true);
            $totales = ChequeReporteSupport::totales($filtros, $this->empresaRepository);
            $totalesPorDia = ChequeReporteSupport::totalesPorDia($filtros, $this->empresaRepository);
        }

        return view('caja.cheque.reporte', [
            'filtros' => $filtros,
            'filtrosQuery' => $consultado ? ChequeReporteFiltros::paraQueryString($filtros) : [],
            'consultado' => $consultado,
            'datas' => $datas,
            'totales' => $totales,
            'totalesPorDia' => $totalesPorDia,
            'subtitulo' => $consultado ? ChequeReporteFiltros::subtitulo($filtros) : '',
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estado_enum' => Cheque::$enumEstado,
        ]);
    }

    /**
     * Export del reporte (PDF / Excel / CSV) con el mismo filtro y orden de la pantalla.
     */
    public function listarReporte(Request $request, $formato = null)
    {
        can('listar-cheque');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosReporte($request);
        $filtros['consultar'] = true;
        $titulo = ($filtros['tipo'] ?? 'E') === 'R' ? 'Cheques recibidos' : 'Cheques emitidos';
        $subtitulo = ChequeReporteFiltros::subtitulo($filtros);
        $estado_enum = Cheque::$enumEstado;
        $etiquetaFechaDoc = ($filtros['tipo'] ?? 'E') === 'R' ? 'Ingreso' : 'Emisión';

        switch ($formato) {
            case 'PDF':
                $datas = ChequeReporteSupport::listar($filtros, $this->empresaRepository, false);
                $totales = ChequeReporteSupport::totales($filtros, $this->empresaRepository);
                $filas = ChequeReporteSupport::filasConSubtotalesDiarios($datas, $filtros);
                $tipoReporte = ($filtros['tipo'] ?? 'E') === 'R' ? 'R' : 'E';
                $html = view('caja.cheque.reporte_listado', compact(
                    'datas',
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                    'estado_enum',
                    'etiquetaFechaDoc',
                    'tipoReporte'
                ))->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'reporte_cheque';
                DompdfListadoSupport::guardarLegalLandscape($html, $path.'/'.$nombre_pdf.'.pdf', [
                    'titulo_corto' => $titulo,
                ]);

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ChequeReporteExport($this->empresaRepository))
                    ->parametros($filtros)
                    ->download('reporte_cheque.xlsx');

            case 'CSV':
                return (new ChequeReporteExport($this->empresaRepository))
                    ->parametros($filtros)
                    ->download('reporte_cheque.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_cheque', ChequeReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosReporte(Request $request): array
    {
        $filtros = ChequeReporteFiltros::resolverDesdeRequest($request);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            $filtros['empresa_id'] = null;
        }

        return $filtros;
    }

    /**
     * Modal: cheques de terceros en cartera.
     */
    public function consultaCartera(Request $request)
    {
        if (! $this->puedeConsultarCartera()) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $filas = ChequeCarteraConsultaSupport::consultar([
            'consulta' => (string) $request->input('consulta', ''),
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'limite' => (int) $request->input('limite', 80),
        ]);

        $puedeAbm = can('editar-cheque', false) || can('listar-cheque', false);
        foreach ($filas as &$fila) {
            $fila['url_abm'] = $puedeAbm
                ? route('editar_cheque', [
                    'id' => (int) $fila['id'],
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                : null;
        }
        unset($fila);

        return response()->json(['data' => $filas]);
    }

    /**
     * Resolver cheque en cartera por id, nro interno Anita o número físico.
     */
    public function resolverCartera(Request $request)
    {
        if (! $this->puedeConsultarCartera()) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;
        $valor = trim((string) $request->input('valor', $request->input('codigo', '')));

        $cheque = null;
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            $cheque = ChequeCarteraConsultaSupport::findEnCarteraPorId($id, $empresaId);
        } elseif ($valor !== '' && ctype_digit($valor)) {
            $cheque = ChequeCarteraConsultaSupport::findEnCarteraPorNroInterno((int) $valor, $empresaId)
                ?: ChequeCarteraConsultaSupport::findEnCarteraPorId((int) $valor, $empresaId);
        }

        if ($cheque === null && $valor !== '') {
            $q = ChequeCarteraConsultaSupport::queryCartera()
                ->where('numerocheque', $valor)
                ->with(['bancos', 'monedas', 'empresas', 'clientes']);
            if ($empresaId) {
                $q->where('empresa_id', $empresaId);
            }
            $cheque = $q->orderByDesc('id')->first();
        }

        if ($cheque === null) {
            return response()->json(['mensaje' => 'ng', 'error' => 'Cheque no encontrado en cartera'], 404);
        }

        return response()->json([
            'mensaje' => 'ok',
            'data' => ChequeCarteraConsultaSupport::serializar($cheque),
        ]);
    }

    private function puedeConsultarCartera(): bool
    {
        return can('listar-cheque', false)
            || can('crear-cheque', false)
            || can('editar-cheque', false)
            || can('crear-pagoproveedor', false)
            || can('editar-pagoproveedor', false)
            || can('listar-pagoproveedor', false)
            || can('crear-ingresos-egresos-caja', false)
            || can('editar-ingresos-egresos-caja', false)
            || can('listar-ingresos-egresos-caja', false);
    }

    /**
     * Precarga modal rechazo CHT → ND electrónica.
     */
    public function datosRechazoNd(int $id)
    {
        can('generar-nota-de-debito-cheque');

        if (! ChequeNdConfigSupport::habilitado()) {
            return response()->json(['mensaje' => 'ng', 'error' => 'ND por cheque rechazado deshabilitada.'], 422);
        }

        try {
            $cheque = $this->repository->findOrFail($id);
            $datos = $this->chequeRechazadoNdService->datosParaModal($cheque);

            return response()->json(['mensaje' => 'ok', 'data' => $datos]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Emite ND (FacturacionService) y marca cheque rechazado.
     */
    public function rechazarConNd(Request $request, int $id)
    {
        can('generar-nota-de-debito-cheque');

        if (! ChequeNdConfigSupport::habilitado()) {
            return response()->json(['mensaje' => 'ng', 'error' => 'ND por cheque rechazado deshabilitada.'], 422);
        }

        $lineas = $request->input('lineas', []);
        if (! is_array($lineas)) {
            $lineas = [];
        }

        try {
            $resultado = $this->chequeRechazadoNdService->emitirNotaDebitoChequeRechazado(
                $id,
                $lineas,
                $request->input('fecha'),
                $request->input('leyenda'),
                $request->input('motivo_rechazo'),
            );

            return response()->json([
                'mensaje' => 'ok',
                'data' => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Depósito CHT en banco / cuenta caja.
     */
    public function depositar(Request $request, int $id)
    {
        can('editar-cheque');

        try {
            $resultado = $this->chequeDepositoService->depositar(
                $id,
                (int) $request->input('cuentacaja_id', 0),
                $request->input('fecha'),
                $request->input('nro_boleta'),
            );
            $resultado['url_comprobante_pdf'] = ChequeDepositoComprobanteSupport::url([
                (int) ($resultado['cheque_id'] ?? 0),
            ]);

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Depósito masivo CHT.
     */
    public function depositarMasivo(Request $request)
    {
        can('editar-cheque');

        try {
            $ids = $request->input('cheque_ids', $request->input('ids', []));
            if (! is_array($ids)) {
                $ids = [];
            }
            $resultado = $this->chequeDepositoService->depositarMasivo(
                $ids,
                (int) $request->input('cuentacaja_id', 0),
                $request->input('fecha'),
                $request->input('nro_boleta'),
            );
            $okIds = [];
            foreach ($resultado['detalle'] ?? [] as $fila) {
                if (! empty($fila['ok'])) {
                    $okIds[] = (int) ($fila['cheque_id'] ?? 0);
                }
            }
            $resultado['url_comprobante_pdf'] = ChequeDepositoComprobanteSupport::url($okIds);

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * PDF boleta de depósito CHT (uno o varios) para archivo.
     */
    public function comprobanteDeposito(Request $request)
    {
        can('listar-cheque');

        try {
            $datos = ChequeDepositoComprobanteSupport::armar(
                ChequeDepositoComprobanteSupport::parseIds($request->input('ids')),
                $this->empresaRepository
            );
        } catch (InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $nombre = ChequeDepositoComprobanteSupport::nombreArchivo(
            (int) $datos['total_cantidad'],
            (string) $datos['fecha_deposito']
        );
        $pdf = Pdf::loadView('caja.cheque.comprobante_deposito', $datos)->setPaper('a4', 'portrait');

        return $pdf->stream($nombre);
    }

    /**
     * Aging cartera CHT.
     */
    public function agingCartera(Request $request)
    {
        can('listar-cheque');

        $filtros = ChequeAgingListadoFiltros::resolverDesdeRequest($request);
        $resumen = ChequeCarteraAgingSupport::resumirPaginado(
            $filtros,
            max(1, (int) $request->input('page', 1)),
            25
        );

        return view('caja.cheque.aging', [
            'resumen' => $resumen,
            'paginator' => $resumen['paginator'],
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtros' => $filtros,
            'filtrosQuery' => ChequeAgingListadoFiltros::paraQueryString($filtros),
            'hasta' => $resumen['hasta'],
            'puede_depositar_cheque' => can('editar-cheque', false) || can('actualizar-cheque', false),
        ]);
    }

    /**
     * Export aging cartera (PDF / Excel / CSV).
     */
    public function listarAging(Request $request, $formato = null)
    {
        can('listar-cheque');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ChequeAgingListadoFiltros::resolverDesdeRequest($request);
        $resumen = ChequeCarteraAgingSupport::resumir(
            $filtros['empresa_id'] ?? null,
            $filtros['hasta'] ?? null,
            $filtros
        );
        $filas = $resumen['filas'] ?? [];

        $partes = [];
        if (! empty($filtros['hasta'])) {
            $partes[] = 'Hasta '.$filtros['hasta'];
        }
        if (! empty($filtros['bucket'])) {
            $partes[] = 'Bucket '.$filtros['bucket'];
        }
        if (! empty($filtros['texto'])) {
            $partes[] = 'Texto: '.$filtros['texto'];
        }
        $subtitulo = implode(' · ', $partes);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.cheque.aging_listado', [
                    'filas' => $filas,
                    'subtitulo' => $subtitulo,
                    'filtros' => $filtros,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_aging_cheque';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ChequeAgingExport)
                    ->parametros($filtros)
                    ->download('aging_cheque.xlsx');

            case 'CSV':
                return (new ChequeAgingExport)
                    ->parametros($filtros)
                    ->download('aging_cheque.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('aging_cheque_cartera', ChequeAgingListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Formulario ingreso masivo CHT.
     */
    public function formImportar()
    {
        can('crear-cheque');

        return view('caja.cheque.importar', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    /**
     * Vista previa AJAX del archivo de importación.
     */
    public function previewImportacion(Request $request)
    {
        can('crear-cheque');

        $request->validate([
            'file' => 'required|file',
            'empresa_id' => 'nullable|integer',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
            'mapping' => 'nullable|array',
        ]);

        $empresaId = (int) $request->input('empresa_id', 0);

        $preview = $this->chequeIngresoMasivoService->preview(
            $request->file('file'),
            is_array($request->input('mapping')) ? $request->input('mapping') : [],
            $empresaId > 0 ? $empresaId : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null
        );

        return response()->json($preview);
    }

    /**
     * Confirmar importación masiva CHT.
     */
    public function importar(Request $request)
    {
        can('crear-cheque');

        $request->validate([
            'file' => 'required|file',
            'empresa_id' => 'nullable|integer',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
            'mapping' => 'nullable|array',
        ]);

        $empresaId = (int) $request->input('empresa_id', 0);
        $resultado = $this->chequeIngresoMasivoService->import(
            $request->file('file'),
            is_array($request->input('mapping')) ? $request->input('mapping') : [],
            $empresaId > 0 ? $empresaId : null,
            $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null,
            $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null
        );

        $msg = 'Importación CHT: '.$resultado['creados'].' creados';
        if ($resultado['omitidos'] > 0) {
            $msg .= ', '.$resultado['omitidos'].' omitidos';
        }
        if ($resultado['errores'] > 0) {
            $msg .= ', '.$resultado['errores'].' con error';
            if (! empty($resultado['detalle_errores'][0]['mensaje'])) {
                $msg .= ' (ej. fila '.$resultado['detalle_errores'][0]['fila'].': '.$resultado['detalle_errores'][0]['mensaje'].')';
            }
        }
        if (($resultado['anita_ok'] ?? 0) > 0 || ($resultado['anita_fail'] ?? 0) > 0) {
            $msg .= '; Anita OK '.$resultado['anita_ok'].' / falló '.$resultado['anita_fail'];
        }

        return redirect()->route('cheque')->with('mensaje', $msg);
    }

    /**
     * Tablero conciliación depósitos (tránsito / acreditados).
     */
    public function conciliacionDeposito(Request $request)
    {
        can('listar-cheque');

        $filtros = ChequeDepositoConciliacionFiltros::resolverDesdeRequest($request);
        $resumen = ChequeDepositoConciliacionSupport::resumirPaginado(
            $filtros,
            max(1, (int) $request->input('page', 1)),
            25
        );

        return view('caja.cheque.conciliacion', [
            'resumen' => $resumen,
            'paginator' => $resumen['paginator'],
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'cuentacaja_query' => $this->cuentacajaRepository->all(),
            'filtros' => $filtros,
            'filtrosQuery' => ChequeDepositoConciliacionFiltros::paraQueryString($filtros),
            'puede_acreditar' => can('editar-cheque', false) || can('actualizar-cheque', false),
        ]);
    }

    /**
     * Historial de boletas de depósito CHT (agrupado por fecha + cuenta + nro. boleta).
     */
    public function historialDepositos(Request $request)
    {
        can('listar-cheque');

        $filtros = ChequeDepositoHistorialFiltros::resolverDesdeRequest($request);
        $resumen = ChequeDepositoHistorialSupport::resumirPaginado(
            $filtros,
            max(1, (int) $request->input('page', 1)),
            25,
            $this->empresaRepository
        );

        $cuentaFiltro = null;
        $cuentaId = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($cuentaId > 0) {
            $cuentaFiltro = Cuentacaja::query()->find($cuentaId);
        }

        return view('caja.cheque.historial', [
            'resumen' => $resumen,
            'paginator' => $resumen['paginator'],
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'cuentaFiltro' => $cuentaFiltro,
            'filtros' => $filtros,
            'filtrosQuery' => ChequeDepositoHistorialFiltros::paraQueryString($filtros),
        ]);
    }

    public function listarHistorialDepositos(Request $request, $formato = null)
    {
        can('listar-cheque');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ChequeDepositoHistorialFiltros::resolverDesdeRequest($request);
        $resumen = ChequeDepositoHistorialSupport::resumir($filtros, $this->empresaRepository);
        $grupos = $resumen['grupos'] ?? [];
        $subtitulo = ChequeDepositoHistorialFiltros::subtitulo($filtros);

        switch ($formato) {
            case 'PDF':
                $html = view('caja.cheque.historial_listado', [
                    'grupos' => $grupos,
                    'subtitulo' => $subtitulo,
                    'filtros' => $filtros,
                ])->render();
                $ruta = storage_path('pdf/listados/listado_historial_deposito_cheque.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Historial depósitos CHT',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new ChequeDepositoHistorialExport)
                    ->parametros($filtros)
                    ->download('historial_deposito_cheque.xlsx');

            case 'CSV':
                return (new ChequeDepositoHistorialExport)
                    ->parametros($filtros)
                    ->download('historial_deposito_cheque.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'historial_deposito_cheque',
            ChequeDepositoHistorialFiltros::paraQueryString($filtros)
        );
    }

    public function listarConciliacionDeposito(Request $request, $formato = null)
    {
        can('listar-cheque');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ChequeDepositoConciliacionFiltros::resolverDesdeRequest($request);
        $resumen = ChequeDepositoConciliacionSupport::resumir($filtros);
        $filas = $resumen['filas'] ?? [];

        $partes = [];
        if (! empty($filtros['estado'])) {
            $partes[] = 'Estado '.$filtros['estado'];
        }
        if (! empty($filtros['boleta'])) {
            $partes[] = 'Boleta '.$filtros['boleta'];
        }
        $subtitulo = implode(' · ', $partes);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('caja.cheque.conciliacion_listado', [
                    'filas' => $filas,
                    'subtitulo' => $subtitulo,
                    'filtros' => $filtros,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_conciliacion_deposito_cheque';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ChequeDepositoConciliacionExport)
                    ->parametros($filtros)
                    ->download('conciliacion_deposito_cheque.xlsx');

            case 'CSV':
                return (new ChequeDepositoConciliacionExport)
                    ->parametros($filtros)
                    ->download('conciliacion_deposito_cheque.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'conciliacion_deposito_cheque',
            ChequeDepositoConciliacionFiltros::paraQueryString($filtros)
        );
    }

    public function acreditar(Request $request, int $id)
    {
        can('editar-cheque');

        try {
            $resultado = $this->chequeDepositoService->acreditar($id, $request->input('fecha'));

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    public function acreditarMasivo(Request $request)
    {
        can('editar-cheque');

        try {
            $ids = $request->input('cheque_ids', $request->input('ids', []));
            if (! is_array($ids)) {
                $ids = [];
            }
            $resultado = $this->chequeDepositoService->acreditarMasivo($ids, $request->input('fecha'));

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    public function caucionar(Request $request, int $id)
    {
        can('editar-cheque');

        try {
            $resultado = $this->chequeCaucionService->caucionar(
                $id,
                (string) $request->input('nro_caucion', ''),
                $request->input('fecha')
            );

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    public function caucionarMasivo(Request $request)
    {
        can('editar-cheque');

        try {
            $ids = $request->input('cheque_ids', $request->input('ids', []));
            if (! is_array($ids)) {
                $ids = [];
            }
            $resultado = $this->chequeCaucionService->caucionarMasivo(
                $ids,
                (string) $request->input('nro_caucion', ''),
                $request->input('fecha')
            );

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    public function liberarCaucion(Request $request, int $id)
    {
        can('editar-cheque');

        try {
            $resultado = $this->chequeCaucionService->liberar($id);

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Dashboard cashflow semanal CHT/CHP.
     */
    public function cashflowSemanal(Request $request)
    {
        can('listar-cheque');

        $empresaId = (int) $request->input('empresa_id', 0);
        $semanas = (int) $request->input('semanas', 8);
        $desde = trim((string) $request->input('desde', ''));
        if ($desde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = '';
        }

        $resumen = ChequeCashflowSemanalSupport::resumir(
            $empresaId > 0 ? $empresaId : null,
            $desde !== '' ? $desde : null,
            $semanas
        );

        return view('caja.cheque.cashflow', [
            'resumen' => $resumen,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtros' => [
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
                'semanas' => $semanas,
                'desde' => $desde !== '' ? $desde : $resumen['desde'],
            ],
        ]);
    }

    /**
     * Listado operativo eCheq (provider configurable por banco).
     */
    public function echeqIndex(Request $request)
    {
        can('listar-cheque');

        $empresaId = (int) $request->input('empresa_id', 0);
        $filas = $this->chequeEcheqService->listarPendientes(
            $empresaId > 0 ? $empresaId : null,
            150
        );

        return view('caja.cheque.echeq', [
            'filas' => $filas,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'provider' => (string) config('cheque.echeq.provider', 'manual'),
            'habilitado' => ChequeEcheqProviderResolver::habilitado(),
            'puede_sync' => can('editar-cheque', false),
        ]);
    }

    public function echeqSync(Request $request, int $id)
    {
        can('editar-cheque');

        try {
            $resultado = $this->chequeEcheqService->sincronizarEstado($id);

            return response()->json(['mensaje' => 'ok', 'data' => $resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cheque');

        $cuentacaja_query = $this->cuentacajaRepository->all();
        $origen_enum = Cheque::$enumOrigen;
        $caracter_enum = Cheque::$enumCaracter;
        $para_dep_enum = Cheque::$enumParaDep;
        $negociable_enum = Cheque::$enumNegociable;
        $estado_enum = Cheque::$enumEstado;
        $chequera_query = $this->chequeraRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $tipodocumento_enum = config('enums.tipodocumento', []);
        $disponible = '';

        return view('caja.cheque.crear', compact('cuentacaja_query',
                                                'origen_enum', 'caracter_enum',
                                                'para_dep_enum', 'negociable_enum',
                                                'estado_enum', 'chequera_query', 'empresa_query',
                                                'moneda_query',
                                                'tipodocumento_enum', 'disponible'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCheque $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/cheque')->with('mensaje', 'cheque creado con éxito');
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
            if (! can('editar-cheque', false) && ! can('listar-cheque', false)) {
                abort(403);
            }
        } else {
            can('editar-cheque');
        }
        $data = $this->repository->findOrFail($id);

        $cuentacaja_query = $this->cuentacajaRepository->all();
        $origen_enum = Cheque::$enumOrigen;
        $caracter_enum = Cheque::$enumCaracter;
        $para_dep_enum = Cheque::$enumParaDep;
        $negociable_enum = Cheque::$enumNegociable;
        $estado_enum = Cheque::$enumEstado;
        $chequera_query = $this->chequeraRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $tipodocumento_enum = config('enums.tipodocumento', []);
        $disponible = $this->disponiblesDesdeChequera($data->chequeras);
        $ocultarVolver = $soloConsulta;
        $puedeActualizarCheque = can('actualizar-cheque', false);

        return view('caja.cheque.editar', compact('data', 'cuentacaja_query',
                                                'origen_enum', 'caracter_enum',
                                                'para_dep_enum', 'negociable_enum',
                                                'estado_enum', 'chequera_query', 'empresa_query',
                                                'moneda_query',
                                                'tipodocumento_enum', 'disponible',
                                                'soloConsulta', 'ocultarVolver', 'puedeActualizarCheque'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCheque $request, $id)
    {
        can('actualizar-cheque');

        // Solo columnas del cheque; no pasar flags de UI (_token, vista, origen=modal_consulta, etc.).
        $data = $request->only((new Cheque())->getFillable());
        if (isset($data['origen']) && ! in_array((string) $data['origen'], ['E', 'R'], true)) {
            unset($data['origen']);
        }

        $this->repository->update($data, $id);

        if ($request->input('vista') === 'consulta'
            || $request->query('origen') === 'modal_consulta'
            || $request->input('origen') === 'modal_consulta') {
            return redirect()
                ->route('editar_cheque', [
                    'id' => $id,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ])
                ->with('mensaje', 'Cheque actualizado con éxito');
        }

        return redirect('caja/cheque')->with('mensaje', 'Cheque actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cheque');

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

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return ChequeListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

    private function disponiblesDesdeChequera(?Chequera $chequera): string
    {
        if (! $chequera) {
            return '';
        }

        $chequeraId = (int) $chequera->id;
        $desde = (int) preg_replace('/\D/', '', (string) ($chequera->desdenumerocheque ?? ''));
        $hasta = (int) preg_replace('/\D/', '', (string) ($chequera->hastanumerocheque ?? ''));
        $ultimos = ChequeConsultaChequeraSupport::ultimosNumeros([$chequeraId]);
        $disp = ChequeConsultaChequeraSupport::disponibles($ultimos[$chequeraId] ?? null, $desde, $hasta);

        return $disp === null ? '' : (string) $disp;
    }
}
