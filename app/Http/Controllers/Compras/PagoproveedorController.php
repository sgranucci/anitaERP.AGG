<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PagoproveedorListadoExport;
use App\Http\Controllers\Controller;
use App\Mail\Compras\PagoproveedorListadoMail;
use App\Models\Admin\Rol;
use App\Models\Listado\ListadoEnvioProgramado;
use App\Models\Listado\ListadoVista;
use App\Http\Requests\ValidacionPagoproveedor;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Compras\PagoproveedorAnularRevertirService;
use App\Services\Compras\PagoproveedorComprobantePdfService;
use App\Services\Compras\PagoproveedorEnvioProveedorService;
use App\Services\Compras\PagoproveedorService;
use App\Services\Compras\ProveedorCuentacorrienteImportarDesdeAnitaService;
use App\Services\Compras\RetencionesPagoCalculator;
use App\Services\Compras\RetencionesPagoContextoBuilder;
use App\Support\Caja\IngresoEgresoPagoProveedorSupport;
use App\Support\Compras\PagoproveedorAplicacionLadoSupport;
use App\Support\Compras\PagoproveedorArchivoSupport;
use App\Support\Compras\PagoproveedorDocumentosRelacionadosSupport;
use App\Support\Compras\PagoproveedorListadoAnalisisSupport;
use App\Support\Compras\PagoproveedorListadoEnvioSupport;
use App\Support\Compras\PagoproveedorListadoColumnas;
use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Compras\PagoproveedorListadoPreferenciasUsuario;
use App\Support\Compras\PagoproveedorListadoUnificadoSupport;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use App\Support\Compras\PropuestaPagoModoSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoDisenadorPreviewSupport;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoVistaMenuSupport;
use App\Support\Listado\ListadoVisualSupport;
use App\Support\Listado\ListadoVistaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class PagoproveedorController extends Controller
{
    private const MAIL_LISTADO_MAX_FILAS = 2000;
    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private PagoproveedorService $pagoproveedorService,
        private PagoproveedorAnularRevertirService $anularRevertirService,
        private EmpresaRepositoryInterface $empresaRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CajaRepositoryInterface $cajaRepository,
        private ChequeraRepositoryInterface $chequeraRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private Proveedor_CuentacorrienteRepositoryInterface $proveedorCuentacorrienteRepository,
        private RetencionesPagoCalculator $retencionesPagoCalculator,
        private RetencionesPagoContextoBuilder $retencionesPagoContextoBuilder,
        private PagoproveedorComprobantePdfService $pagoproveedorComprobantePdfService,
        private PagoproveedorEnvioProveedorService $pagoproveedorEnvioProveedorService,
        private ProveedorCuentacorrienteImportarDesdeAnitaService $proveedorCuentacorrienteImportarDesdeAnitaService,
    ) {}

    public function index(Request $request)
    {
        can('listar-pagoproveedor');

        $armado = $this->armarListado($request);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }

        return view('compras.pagoproveedor.index', $armado);
    }

    public function previewWorkbench(Request $request)
    {
        can('listar-pagoproveedor');

        $filtros = $this->resolverFiltrosConVista($request);
        $filtros['_per_page'] = ListadoDisenadorPreviewSupport::LIMITE_MUESTRA;
        $layout = PagoproveedorListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
        $page = $this->pagoproveedorRepository->leePagoproveedor($filtros, true);
        $total = method_exists($page, 'total') ? (int) $page->total() : $page->count();
        $filas = method_exists($page, 'getCollection') ? $page->getCollection() : $page;
        $orden = ListadoOrdenamientoSupport::normalizar(
            $request->input('sort', $filtros['sort'] ?? []),
            PagoproveedorListadoFiltros::camposOrdenables()
        );
        $agrupar = ListadoAgrupacionSupport::normalizar(
            $request->input('group', $filtros['agrupar'] ?? []),
            PagoproveedorListadoFiltros::camposOrdenables()
        );
        $filtrosCortes = $filtros;
        $filtrosCortes['agrupar'] = $agrupar;
        $cortes = $agrupar !== []
            ? app(PagoproveedorListadoUnificadoSupport::class)->cortes($filtrosCortes, $etiquetas)
            : ['activo' => false];

        return response()->json(ListadoDisenadorPreviewSupport::payload(
            $layout,
            $orden,
            $agrupar,
            $filas,
            static fn (object $row, string $key): string => PagoproveedorListadoColumnas::valorCelda($row, $key),
            $total,
            $etiquetas,
            $cortes
        ));
    }

    public function guardarVistaListado(Request $request)
    {
        can('listar-pagoproveedor');

        $filtros = $this->resolverFiltrosConVista($request);
        $layout = PagoproveedorListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $orden = $filtros['sort'] ?? [];
        $vista = ListadoVistaSupport::guardar(
            PagoproveedorListadoColumnas::RECURSO,
            (int) auth()->id(),
            (string) $request->input('nombre', ''),
            [
                'modo' => $filtros['modo'],
                'qbe' => $filtros['qbe'] ?? [],
                'sort' => $orden,
                'orden' => $orden,
                'agrupar' => $filtros['agrupar'] ?? [],
                'grafico' => $filtros['grafico'] ?? PagoproveedorListadoAnalisisSupport::graficoVacio(),
                'graficos' => $filtros['graficos'] ?? [],
                'formato' => $filtros['formato'] ?? [],
                'calculadas' => $filtros['calculadas'] ?? [],
            ],
            $layout,
            $request->boolean('es_default'),
            $request->boolean('compartida'),
            $request->filled('vista_id') ? (int) $request->input('vista_id') : null
        );
        if (! $vista) {
            return redirect()->route('pagoproveedor', PagoproveedorListadoFiltros::paraQueryString($filtros))
                ->with('error', 'No se pudo guardar la vista.');
        }
        ListadoVistaMenuSupport::sincronizar($vista, $request->boolean('crear_en_menu'));
        $this->asignarVistaAlRol($vista, $request);
        $qs = PagoproveedorListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs['vista_id'] = $vista->id;

        return redirect()->route('pagoproveedor', $qs)
            ->with('mensaje', 'Vista «'.$vista->nombre.'» guardada.');
    }

    public function eliminarVistaListado(int $id)
    {
        can('listar-pagoproveedor');
        $ok = ListadoVistaSupport::eliminar($id, PagoproveedorListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('pagoproveedor', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        can('listar-pagoproveedor');
        $layout = PagoproveedorListadoPreferenciasUsuario::normalizarLayout($request->input('grilla'));
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($layout);
        $vistaId = $request->filled('vista_id') ? (int) $request->input('vista_id') : 0;
        if ($vistaId > 0 && $request->boolean('actualizar_vista')) {
            $vista = ListadoVistaSupport::findParaUsuario($vistaId, PagoproveedorListadoColumnas::RECURSO, (int) auth()->id());
            if ($vista && (int) $vista->usuario_id === (int) auth()->id()) {
                $vista->columnas_json = $layout;
                $vista->save();
            }
        } else {
            PagoproveedorListadoPreferenciasUsuario::persistirGrillaEstandar($layout);
        }
        $filtros = $this->resolverFiltrosConVista($request);
        $qs = PagoproveedorListadoFiltros::paraQueryString($filtros);
        $qs['columnas'] = implode(',', $columnasVisibles);
        $qs[$vistaId > 0 ? 'vista_id' : 'vista_estandar'] = $vistaId > 0 ? $vistaId : 1;

        return redirect()->route('pagoproveedor', $qs)->with('mensaje', 'Grilla actualizada.');
    }

    public function guardarEtiquetasListado(Request $request)
    {
        can('listar-pagoproveedor');
        $etiquetas = $request->input('etiquetas', []);
        if (! is_array($etiquetas)) {
            $etiquetas = [];
        }
        ListadoColumnaEtiquetaSupport::guardar(
            PagoproveedorListadoColumnas::RECURSO,
            $etiquetas,
            array_keys(PagoproveedorListadoColumnas::catalogoActivo())
        );

        return redirect()->route(
            'pagoproveedor',
            PagoproveedorListadoFiltros::paraQueryString($this->resolverFiltrosConVista($request))
        )->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-pagoproveedor');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = $this->resolverFiltrosConVista($request, $busqueda);
        $formato = strtoupper((string) $formato);

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('pagoproveedor', PagoproveedorListadoFiltros::paraQueryString($filtros));
        }

        if ($formato === 'PDF') {
            $datas = $this->pagoproveedorRepository->leePagoproveedor($filtros, false);
            $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
        $pdf = Pdf::loadView('compras.pagoproveedor.listado', [
            'datas' => $datas,
            'logosCabecera' => $logos,
            'filtros' => $filtros,
            'calculadas' => array_values(is_array($filtros['calculadas'] ?? null) ? $filtros['calculadas'] : []),
        ])->setPaper('legal', 'landscape');

            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir.'/listado_pagoproveedor.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = app(PagoproveedorListadoExport::class)->parametros($filtros, $formato === 'CSV');
        $nombre = 'pagoproveedor_'.date('Ymd_His');

        if ($formato === 'CSV') {
            return Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, $nombre.'.xlsx');
    }

    public function crear(Request $request)
    {
        can('crear-pagoproveedor');

        $empresaId = (int) ($request->query('empresa_id') ?: session('empresa_id') ?: 0);
        if ($empresaId > 0 && ! PropuestaPagoModoSupport::config($empresaId)->permite_op_sin_propuesta) {
            return redirect()
                ->route('propuesta_pago')
                ->withErrors(['error' => 'Esta empresa exige OP vía propuesta de pagos (modo premium sin OP unitaria).']);
        }

        $proveedorId = (int) $request->query('proveedor_id', 0);
        $data = (object) [
            'proveedor_id' => $proveedorId ?: null,
            'caja_movimientos' => collect(),
            'cheques' => collect(),
            'pagoproveedor_estados' => collect(),
            'pagoproveedor_retenciones' => collect(),
            'asientos' => null,
        ];
        if ($proveedorId > 0) {
            $proveedor = Proveedor::query()->find($proveedorId);
            if ($proveedor) {
                $data->proveedores = $proveedor;
            }
        }

        return view('compras.pagoproveedor.crear', $this->datosFormulario($data));
    }

    public function guardar(ValidacionPagoproveedor $request)
    {
        can('crear-pagoproveedor');
        session(['empresa_id' => $request->empresa_id]);

        $empresaId = (int) $request->empresa_id;
        if ($empresaId > 0 && ! PropuestaPagoModoSupport::config($empresaId)->permite_op_sin_propuesta) {
            return back()->withErrors(['error' => 'Esta empresa exige OP vía propuesta de pagos.'])->withInput();
        }

        $resultado = $this->pagoproveedorService->guardaPago($request);
        if (! empty($resultado['errores'])) {
            return back()->withErrors(['error' => $resultado['errores']])->withInput();
        }

        $pagoId = (int) ($resultado['pagoproveedor_id'] ?? 0);
        $numero = (string) ($resultado['numerotransaccion'] ?? '');
        $mensaje = $numero !== ''
            ? 'Orden de pago '.$numero.' grabada.'
            : 'Orden de pago grabada.';
        if (! empty($resultado['aviso'])) {
            $mensaje .= ' '.$resultado['aviso'];
        }

        return redirect()
            ->route('pagoproveedor', ['empresa_id' => $empresaId])
            ->with('mensaje', $mensaje)
            ->with('imprimir_pagoproveedor_url', route('imprimir_pagoproveedor', $pagoId))
            ->with('imprimir_comprobante_label', 'Imprimir orden de pago');
    }

    public function editar(int $id)
    {
        $puedeEditar = can('editar-pagoproveedor', false);
        if (! $puedeEditar && ! $this->puedeConsultarOrdenPago()) {
            can('editar-pagoproveedor');
        }

        $data = $this->pagoproveedorRepository->find($id);

        return view('compras.pagoproveedor.editar', array_merge($this->datosFormulario($data), [
            'consultaSinEditar' => ! $puedeEditar,
        ]));
    }

    public function actualizar(ValidacionPagoproveedor $request, int $id)
    {
        can('actualizar-pagoproveedor');
        session(['empresa_id' => $request->empresa_id]);

        $resultado = $this->pagoproveedorService->actualizaPago($request, $id);
        if (! empty($resultado['errores'])) {
            return back()->withErrors(['error' => $resultado['errores']])->withInput();
        }

        $empresaId = (int) $request->empresa_id;
        $mensaje = 'Orden de pago actualizada.';
        if (! empty($resultado['aviso'])) {
            $mensaje .= ' '.$resultado['aviso'];
        }
        if ((string) $request->input('sincronizar_archivos_op') === '1') {
            try {
                PagoproveedorArchivoSupport::sincronizar(
                    $id,
                    (array) $request->input('nombresanteriores', []),
                    array_values(array_filter((array) $request->file('nombrearchivos', [])))
                );
            } catch (\Throwable $e) {
                report($e);
                $mensaje .= ' No se pudieron guardar los archivos adjuntos: '.$e->getMessage();
            }
        }

        return redirect()
            ->route('pagoproveedor', ['empresa_id' => $empresaId])
            ->with('mensaje', $mensaje)
            ->with('imprimir_pagoproveedor_url', route('imprimir_pagoproveedor', $id))
            ->with('imprimir_comprobante_label', 'Imprimir orden de pago');
    }

    public function confirmar(Request $request, int $id)
    {
        can('confirmar-pagoproveedor');

        $resultado = $this->pagoproveedorService->confirmar($id);
        if (! empty($resultado['errores'])) {
            return redirect()->back()->with('mensaje', $resultado['errores']);
        }

        $mensaje = 'Orden de pago confirmada.';
        if (! empty($resultado['aviso'])) {
            $mensaje .= ' '.$resultado['aviso'];
        }

        return redirect()->route('pagoproveedor')->with('mensaje', $mensaje);
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-pagoproveedor');

        $resultado = $this->pagoproveedorService->eliminarPreCarga($id);
        if (! empty($resultado['errores'])) {
            return redirect()->back()->with('mensaje', $resultado['errores']);
        }

        return redirect()->route('pagoproveedor')->with('mensaje', 'Orden de pago eliminada.');
    }

    public function anularFisicamente(Request $request, int $id)
    {
        can('anular-pagoproveedor');

        try {
            $this->anularRevertirService->anularFisicamente($id);

            return redirect()->route('pagoproveedor')->with('mensaje', 'Orden de pago anulada físicamente.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    public function revertir(Request $request, int $id)
    {
        can('revertir-pagoproveedor');

        try {
            $resultado = $this->anularRevertirService->revertir($id, $request->input('fecha'));

            $mensaje = 'OP revertida. Compensatoria N° '.$resultado['numerotransaccion'].'.';
            if (! empty($resultado['aviso'])) {
                $mensaje .= ' '.$resultado['aviso'];
            }

            if ($request->ajax()) {
                return response()->json([
                    'mensaje' => 'ok',
                    'resultado' => $resultado,
                ]);
            }

            return redirect()->route('pagoproveedor')->with('mensaje', $mensaje);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('mensaje', $e->getMessage());
        }
    }

    public function marcarPagada(Request $request, int $id)
    {
        can('marcar-pagada-pagoproveedor');

        $resultado = $this->pagoproveedorService->marcarPagada($id);
        if (! empty($resultado['errores'])) {
            return redirect()->back()->with('mensaje', $resultado['errores']);
        }

        return redirect()->route('editar_pagoproveedor', $id)->with('mensaje', 'OP marcada como PAGADA.');
    }

    public function marcarConciliada(Request $request, int $id)
    {
        can('marcar-conciliada-pagoproveedor');

        $resultado = $this->pagoproveedorService->marcarConciliada($id);
        if (! empty($resultado['errores'])) {
            return redirect()->back()->with('mensaje', $resultado['errores']);
        }

        return redirect()->route('editar_pagoproveedor', $id)->with('mensaje', 'OP marcada como CONCILIADA.');
    }

    public function generaAsientoContable(Request $request)
    {
        if (! can('crear-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }

        return response()->json($this->pagoproveedorService->generaAsientoContable($request->all()));
    }

    public function apiDeudaProveedor(Request $request)
    {
        if (! can('crear-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->query('proveedor_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        $pagoId = (int) $request->query('pagoproveedor_id', 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            return response()->json([
                'filas' => [],
                'aviso' => $empresaId <= 0
                    ? 'Seleccione empresa para ver la deuda.'
                    : 'Seleccione proveedor.',
            ]);
        }

        $filasTodas = $this->proveedorCuentacorrienteRepository->listarDeudaProveedor('', $proveedorId, false);
        $creditos = $this->proveedorCuentacorrienteRepository->listarPendientesAplicacion(
            $proveedorId,
            'credito',
            $empresaId
        );
        if ($pagoId > 0) {
            $esDeEstaOp = static fn ($cc): bool => (int) ($cc->pagoproveedor_id ?? 0) === $pagoId;
            $filasTodas = $filasTodas->reject($esDeEstaOp)->values();
            $creditos = $creditos->reject($esDeEstaOp)->values();
        }
        $filas = $filasTodas->where('empresa_id', $empresaId)
            ->concat($creditos)
            ->unique('id')
            ->filter(static fn ($cc) => PagoproveedorAplicacionLadoSupport::esAplicableEnOrdenDePago($cc))
            ->values();
        $aviso = null;
        if ($filas->isEmpty() && $filasTodas->isNotEmpty()) {
            $nombres = $filasTodas
                ->map(static fn ($cc) => (string) ($cc->empresas->nombre ?? ('empresa '.$cc->empresa_id)))
                ->unique()
                ->values()
                ->implode(', ');
            $aviso = 'Sin deuda pendiente en la empresa elegida. Hay comprobantes en: '.$nombres.'.';
        }

        $puedeVerComprobante = can('editar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false);
        $puedeVerPago = can('editar-pagoproveedor', false)
            || can('listar-pagoproveedor', false);

        $mapearFila = static function ($cc, float $aplicadoOp = 0.0) use ($puedeVerComprobante, $puedeVerPago): array {
            $comp = $cc->comprobante_proveedores;
            $aplicado = (float) ($cc->aplicado ?? 0);
            $saldoPendiente = abs((float) $cc->total + $aplicado);
            // Al editar una OP, el saldo editable incluye lo que esta OP ya aplicó.
            $saldo = round($saldoPendiente + $aplicadoOp, 4);
            $compId = $comp ? (int) $comp->id : 0;
            $pagoOrigenId = (int) ($cc->pagoproveedor_id ?? 0);
            $esOpa = PagoproveedorAplicacionLadoSupport::esOpa($cc);
            $signo = PagoproveedorAplicacionLadoSupport::signo($cc);

            $comprobanteUrl = null;
            if ($compId > 0 && $puedeVerComprobante) {
                $comprobanteUrl = route('editar_comprobante_proveedor', [
                    'id' => $compId,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
            } elseif ($esOpa && $pagoOrigenId > 0 && $puedeVerPago) {
                $comprobanteUrl = route('editar_pagoproveedor', [
                    'id' => $pagoOrigenId,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
            }

            return [
                'id' => (int) $cc->id,
                'fecha' => optional($cc->fecha)->format('Y-m-d'),
                'vencimiento' => optional($cc->fechavencimiento)->format('Y-m-d'),
                'comprobante' => ProveedorCuentacorrienteGrillaSupport::etiquetaComprobanteAbreviado($cc),
                'comprobante_proveedor_id' => $compId > 0 ? $compId : null,
                'comprobante_url' => $comprobanteUrl,
                'moneda_id' => (int) $cc->moneda_id,
                'moneda' => $cc->monedas?->abreviatura,
                'cotizacion' => (float) $cc->cotizacion,
                'total' => (float) $cc->total,
                'saldo' => $saldo,
                'aplicado_op' => round($aplicadoOp, 4),
                'ordencompra_id' => $comp?->ordencompra_id,
                'signo' => $signo,
                'lado' => $signo < 0 ? 'credito' : 'deuda',
                'es_opa' => $esOpa,
                'es_nc' => $signo < 0 && ! $esOpa,
                'afecta_retenciones' => PagoproveedorAplicacionLadoSupport::afectaRetenciones($cc),
            ];
        };

        $porCcId = [];
        foreach ($filas as $cc) {
            $porCcId[(int) $cc->id] = $mapearFila($cc, 0.0);
        }

        if ($pagoId > 0) {
            $aplicaciones = Pagoproveedor_Comprobante::query()
                ->with([
                    'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                    'proveedor_cuentacorrientes.comprobante_proveedores.comprobante_proveedor_cuotas',
                    'proveedor_cuentacorrientes.comprobante_proveedor_cuotas',
                    'proveedor_cuentacorrientes.pagoproveedores',
                    'proveedor_cuentacorrientes.monedas',
                    'proveedor_cuentacorrientes.empresas',
                ])
                ->where('pagoproveedor_id', $pagoId)
                ->get();

            foreach ($aplicaciones as $apl) {
                $cc = $apl->proveedor_cuentacorrientes;
                if ($cc === null) {
                    continue;
                }
                if ((int) $cc->empresa_id !== $empresaId) {
                    continue;
                }
                if ((int) ($cc->pagoproveedor_id ?? 0) === $pagoId
                    && PagoproveedorAplicacionLadoSupport::esOpa($cc)) {
                    continue;
                }
                $ccId = (int) $cc->id;
                $montoApl = abs((float) $apl->montoaplicado);
                if (isset($porCcId[$ccId])) {
                    $porCcId[$ccId]['aplicado_op'] = round($montoApl, 4);
                    $porCcId[$ccId]['saldo'] = round(
                        (float) $porCcId[$ccId]['saldo'] + $montoApl,
                        4
                    );
                } else {
                    // Recalcular aplicado de la CC (puede venir sin el select de deuda pendiente).
                    if (! isset($cc->aplicado)) {
                        $cc->aplicado = (float) Proveedor_Cuentacorriente_Aplicacion::query()
                            ->where('proveedor_cuentacorriente_id', $ccId)
                            ->sum('total');
                    }
                    $porCcId[$ccId] = $mapearFila($cc, $montoApl);
                }
            }
        }

        $out = collect($porCcId)->values();
        if ($out->isEmpty() && $aviso === null && $pagoId <= 0) {
            $aviso = 'Sin deuda pendiente';
        }

        return response()->json(['filas' => $out, 'aviso' => $aviso]);
    }

    /**
     * AGG: importa deuda Anita impaga del proveedor elegido y deja lista la grilla ERP.
     */
    public function apiImportarDeudaAnita(Request $request)
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return response()->json(['error' => 'Solo disponible en AGG'], 404);
        }
        if (! can('crear-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }

        $proveedorId = (int) $request->input('proveedor_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $codigoRequest = trim((string) $request->input('proveedor', ''));

        $proveedor = null;
        if ($proveedorId > 0) {
            $proveedor = Proveedor::query()->find($proveedorId);
        }
        if ($proveedor === null && $codigoRequest !== '') {
            $norm = ltrim($codigoRequest, '0');
            if ($norm === '') {
                $norm = '0';
            }
            $proveedor = Proveedor::query()
                ->where(function ($q) use ($norm, $codigoRequest) {
                    $q->where('codigo', $norm)
                        ->orWhere('codigo', str_pad($norm, 6, '0', STR_PAD_LEFT))
                        ->orWhere('codigo', $codigoRequest);
                })
                ->first();
            if ($proveedor !== null) {
                $proveedorId = (int) $proveedor->id;
            }
        }

        if ($proveedorId <= 0 || $empresaId <= 0 || $proveedor === null) {
            return response()->json([
                'ok' => false,
                'error' => $empresaId <= 0
                    ? 'Seleccione empresa.'
                    : 'Seleccione proveedor.',
            ], 422);
        }

        $codigo = trim((string) ($proveedor->codigo ?? ''));
        if ($codigo === '') {
            return response()->json(['ok' => false, 'error' => 'El proveedor no tiene código Anita'], 422);
        }

        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        try {
            $stats = $this->proveedorCuentacorrienteImportarDesdeAnitaService->importar(
                false,
                $codigo,
                null,
                null,
                max(1, (int) (auth()->id() ?: 1)),
                null,
                $empresaId,
                25,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        $mensaje = sprintf(
            'Anita → ERP: %d CP, %d OPA, %d CC, %d apps (%d nativas solo apps; a procesar %d; al día %d).',
            (int) ($stats['cp_creados'] ?? 0),
            (int) ($stats['opa_creados'] ?? 0),
            (int) ($stats['cc_creadas'] ?? 0),
            (int) ($stats['aplicaciones_creadas'] ?? 0),
            (int) ($stats['nativas_solo_apps'] ?? 0),
            (int) ($stats['a_procesar'] ?? 0),
            (int) ($stats['omitidas_al_dia'] ?? 0),
        );

        return response()->json([
            'ok' => true,
            'mensaje' => $mensaje,
            'stats' => [
                'cp_creados' => (int) ($stats['cp_creados'] ?? 0),
                'opa_creados' => (int) ($stats['opa_creados'] ?? 0),
                'cc_creadas' => (int) ($stats['cc_creadas'] ?? 0),
                'aplicaciones_creadas' => (int) ($stats['aplicaciones_creadas'] ?? 0),
                'aplicaciones_omitidas' => (int) ($stats['aplicaciones_omitidas'] ?? 0),
                'nativas_solo_apps' => (int) ($stats['nativas_solo_apps'] ?? 0),
                'a_procesar' => (int) ($stats['a_procesar'] ?? 0),
                'omitidas_al_dia' => (int) ($stats['omitidas_al_dia'] ?? 0),
                'omitidas_sin_compra' => (int) ($stats['omitidas_sin_compra'] ?? 0),
                'credito_sin_compra_anita' => (int) ($stats['credito_sin_compra_anita'] ?? 0),
                'errores' => array_slice((array) ($stats['errores'] ?? []), 0, 10),
            ],
        ]);
    }

    public function apiCalcularRetenciones(Request $request)
    {
        if (! can('crear-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->input('proveedor_id', 0);
        $proveedor = Proveedor::query()->with(['condicionivas', 'condicionIIBBs'])->find($proveedorId);
        if ($proveedor === null) {
            return response()->json(['error' => 'Proveedor no encontrado'], 422);
        }

        $aplicaciones = $this->normalizarAplicacionesRequest($request->input('aplicaciones', []));
        if ($aplicaciones === []) {
            // Compat: UI vieja envía solo ids/montos planos.
            $ids = $request->input('idcuentacorrientes', []);
            $montos = $request->input('montoaplicadocomprobantes', []);
            $cots = $request->input('cotizacion_aplicada_dia', $request->input('cotizacioncomprobantes', []));
            $monedas = $request->input('monedacomprobante_ids', []);
            foreach ($ids as $i => $ccId) {
                $aplicaciones[] = [
                    'proveedor_cuentacorriente_id' => (int) $ccId,
                    'montoaplicado' => (float) ($montos[$i] ?? 0),
                    'cotizacion_aplicada' => (float) ($cots[$i] ?? 0),
                    'moneda_id' => (int) ($monedas[$i] ?? 0) ?: null,
                ];
            }
        }

        $empresaId = $request->filled('empresa_id')
            ? (int) $request->input('empresa_id')
            : ((int) session('empresa_id') ?: null);

        $ctx = $this->retencionesPagoContextoBuilder->armarInput(
            proveedor: $proveedor,
            aplicaciones: $aplicaciones,
            fecha: $request->input('fecha'),
            empresaId: $empresaId,
            monedaPagoId: (int) ($request->input('moneda_id') ?: 1),
            cotizacionPago: $request->filled('cotizacion') ? (float) $request->input('cotizacion') : null,
            excluirPagoproveedorId: $request->filled('pagoproveedor_id') ? (int) $request->input('pagoproveedor_id') : null,
            overrides: [
                'retencionganancia_id' => $request->input('retencionganancia_id'),
                'retencioniva_id' => $request->input('retencioniva_id'),
                'retencionsuss_id' => $request->input('retencionsuss_id'),
                'iibb_provincia_id' => $request->input('iibb_provincia_id'),
                'iibb_tasa' => $request->input('iibb_tasa'),
            ],
            importeNetoFallback: (float) $request->input('importe_neto', 0),
            importeIvaFallback: (float) $request->input('importe_iva', 0),
        );

        /** @var \App\Support\Compras\Retencion\RetencionesPagoInput $input */
        $input = $ctx['input'];
        $resultado = $this->retencionesPagoCalculator->calcular($input);
        $bases = $ctx['bases'];

        return response()->json([
            'ganancias' => [
                'aplica' => $resultado->ganancias->aplica,
                'importe' => $resultado->ganancias->importeRetencion,
                'alicuota' => $resultado->ganancias->alicuotaAplicada,
                'motivo' => $resultado->ganancias->motivo,
                'detalle' => $resultado->ganancias->detalle,
                'base' => $input->netoGanancias(),
                'base_periodo' => $resultado->ganancias->baseCalculo,
                'base_retenible' => $resultado->ganancias->baseRetenible,
            ],
            'iva' => [
                'aplica' => $resultado->iva->aplica,
                'importe' => $resultado->iva->importeRetencion,
                'alicuota' => $resultado->iva->alicuotaAplicada,
                'motivo' => $resultado->iva->motivo,
                'detalle' => $resultado->iva->detalle,
                'base_neto' => $input->importeNetoPago,
                'base_iva' => $input->importeIvaPago,
            ],
            'suss' => [
                'aplica' => $resultado->suss->aplica,
                'importe' => $resultado->suss->importeRetencion,
                'alicuota' => $resultado->suss->alicuotaAplicada,
                'motivo' => $resultado->suss->motivo,
                'detalle' => $resultado->suss->detalle,
                'base' => $input->netoSuss(),
            ],
            'iibb' => [
                'aplica' => $resultado->iibb->aplica,
                'importe' => $resultado->iibb->importeRetencion,
                'alicuota' => $resultado->iibb->alicuotaAplicada,
                'motivo' => $resultado->iibb->motivo,
                'detalle' => $resultado->iibb->detalle,
                'provincia_id' => $resultado->iibb->detalle['provincia_id'] ?? null,
                'provincia_nombre' => $resultado->iibb->detalle['provincia_nombre'] ?? null,
                'base' => $input->netoIibb(),
            ],
            'bases' => $bases->toArray(),
            'acumulado_ganancias' => $ctx['acumulado_ganancias'],
            'exclusiones' => $ctx['exclusiones'] ?? null,
            'total' => $resultado->totalRetenciones(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizarAplicacionesRequest(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $ccId = (int) ($fila['proveedor_cuentacorriente_id'] ?? $fila['cc_id'] ?? 0);
            if ($ccId <= 0) {
                continue;
            }
            $out[] = [
                'proveedor_cuentacorriente_id' => $ccId,
                'montoaplicado' => (float) ($fila['montoaplicado'] ?? $fila['monto'] ?? 0),
                'cotizacion_aplicada' => (float) ($fila['cotizacion_aplicada'] ?? $fila['cotizacion'] ?? 0),
                'moneda_id' => isset($fila['moneda_id']) ? (int) $fila['moneda_id'] : null,
            ];
        }

        return $out;
    }

    public function documentosRelacionados(int $id)
    {
        if (! $this->puedeConsultarOrdenPago()) {
            return response()->json(['message' => 'No tiene permisos para esta consulta.'], 403);
        }

        $pago = Pagoproveedor::query()->find($id);
        if ($pago === null) {
            return response()->json(['message' => 'Orden de pago no encontrada.'], 404);
        }

        return response()->json(PagoproveedorDocumentosRelacionadosSupport::armar($pago));
    }

    public function imprimir(int $id)
    {
        if (! $this->puedeConsultarOrdenPago()) {
            abort(403);
        }

        return $this->pagoproveedorComprobantePdfService->generarRespuesta($id);
    }

    public function imprimirRetencion(int $id, int $retencionId)
    {
        if (! $this->puedeConsultarOrdenPago()) {
            abort(403);
        }

        return $this->pagoproveedorComprobantePdfService->streamRetencion($id, $retencionId);
    }

    /**
     * Ver la OP, su PDF y el certificado desde el legajo, sin el ABM de órdenes de pago.
     * Enc-compras entra al legajo y no tiene listar/editar-pagoproveedor.
     */
    private function puedeConsultarOrdenPago(): bool
    {
        return can('listar-pagoproveedor', false)
            || can('editar-pagoproveedor', false)
            || can('crear-pagoproveedor', false)
            || can('listar-cuentacorriente-proveedor', false)
            || can('listar-legajo-compra', false)
            || can('listar-seguimiento-legajo-compra', false)
            || can('listar-ordencompra', false)
            || can('listar-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false);
    }

    /**
     * Quien lista ingresos y egresos puede mandar el mail solo si esa orden
     * es un OPP o una OPA de caja.
     */
    private function puedeEnviarOrdenPago(int $id): bool
    {
        if (can('listar-pagoproveedor', false) || can('editar-pagoproveedor', false)) {
            return true;
        }
        if (! can('listar-ingresos-egresos-caja', false) && ! can('editar-ingresos-egresos-caja', false)) {
            return false;
        }

        return IngresoEgresoPagoProveedorSupport::ordenTieneMovimiento($id);
    }

    public function datosEnvioProveedor(int $id)
    {
        if (! $this->puedeEnviarOrdenPago($id)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        return response()->json($this->pagoproveedorEnvioProveedorService->datosEnvio($id));
    }

    public function enviarProveedor(Request $request, int $id)
    {
        if (! $this->puedeEnviarOrdenPago($id)) {
            return response()->json(['mensaje' => 'error', 'errores' => 'Sin permisos para enviar la OP.'], 403);
        }

        $request->validate([
            'email' => 'required|string|max:500',
            'mensaje' => 'nullable|string|max:4000',
            'nombrearchivos' => 'nullable|array|max:10',
            'nombrearchivos.*' => 'file|max:10240',
        ]);

        $archivos = array_values(array_filter(
            (array) $request->file('nombrearchivos', []),
            static fn ($archivo) => $archivo instanceof \Illuminate\Http\UploadedFile && $archivo->isValid()
        ));

        $ret = $this->pagoproveedorEnvioProveedorService->enviar(
            $id,
            $request->input('email'),
            $request->input('mensaje'),
            [],
            $archivos
        );

        $status = ($ret['mensaje'] ?? '') === 'ok' ? 200 : 422;

        return response()->json($ret, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = (int) (session('empresa_id') ?: 0);
        if ($empresaDefault <= 0) {
            $empresaDefault = (int) (optional($this->empresaRepository->allFiltrado()->first())->id ?: 0);
        }

        return PagoproveedorListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault > 0 ? $empresaDefault : null
        );
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function armarListado(Request $request): array|\Illuminate\Http\RedirectResponse
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistas = ListadoVistaSupport::listarParaUsuario(PagoproveedorListadoColumnas::RECURSO, $usuarioId);
        $vistaActiva = null;
        $forzarEstandar = $request->boolean('vista_estandar')
            || $request->input('vista_modo') === 'estandar';

        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                PagoproveedorListadoColumnas::RECURSO,
                $usuarioId
            );
        } elseif (
            ! $forzarEstandar
            && ! $request->has('filtro_valor')
            && ! $request->has('qbe')
            && ! $request->boolean('filtro_limpiar')
            && ! $request->has('empresa_id')
            && ! $request->has('empresa_todas')
            && ! $request->has('mail')
            && ! $request->has('fecha_desde')
            && ! $request->has('fecha_hasta')
            && ! $request->has('filtro_periodo')
        ) {
            $vistaActiva = ListadoVistaSupport::defaultDelUsuario(PagoproveedorListadoColumnas::RECURSO, $usuarioId)
                ?? ListadoVistaSupport::defaultDelRol(
                    PagoproveedorListadoColumnas::RECURSO,
                    (int) session('rol_id')
                );
        }

        $filtrosRequest = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request),
            $request
        );
        $filtros = $filtrosRequest;
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = PagoproveedorListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito'], $filtros['_limpiar'], $filtros['_grafico_explicito'], $filtros['_formato_explicito'], $filtros['_calculadas_explicito']);
        ListadoVistaSupport::recordarQbeSiEnvio($vistaActiva, $request, $filtros);
        if ($vistaActiva && ($request->exists('group') || $request->exists('sort'))) {
            if ($request->exists('group')) {
                $filtros['agrupar'] = $filtrosRequest['agrupar'] ?? [];
            }
            if ($request->exists('sort')) {
                $filtros['sort'] = $filtrosRequest['sort'] ?? [];
            }
            ListadoVistaSupport::recordarOrdenYAgrupar(
                $vistaActiva,
                $filtros['sort'] ?? [],
                $filtros['agrupar'] ?? []
            );
        }

        if ($request->boolean('quitar_orden')) {
            $filtros['sort'] = [];
            if ($vistaActiva) {
                ListadoVistaSupport::recordarOrdenYAgrupar(
                    $vistaActiva,
                    [],
                    $filtros['agrupar'] ?? []
                );
            }
            $params = PagoproveedorListadoFiltros::paraQueryString($filtros);
            if ($vistaActiva) {
                $params['vista_id'] = $vistaActiva->id;
            } elseif ($forzarEstandar) {
                $params['vista_estandar'] = 1;
            }

            return redirect()
                ->route('pagoproveedor', $params)
                ->with('mensaje', 'Se quitó el orden de la grilla.');
        }

        $catalogo = PagoproveedorListadoColumnas::catalogoActivo();
        $etiquetasInstalacion = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            PagoproveedorListadoColumnas::RECURSO,
            $catalogo
        );
        if ($vistaActiva && is_array($vistaActiva->columnas_json) && $vistaActiva->columnas_json !== []) {
            $grillaLayout = PagoproveedorListadoPreferenciasUsuario::normalizarLayout($vistaActiva->columnas_json);
        } else {
            $grillaLayout = PagoproveedorListadoPreferenciasUsuario::grillaEstandar();
        }
        $columnasVisibles = ListadoGrillaConfigSupport::keysVisibles($grillaLayout);
        $etiquetas = ListadoGrillaConfigSupport::etiquetasDesdeLayout($grillaLayout);
        $coleccion = $this->pagoproveedorRepository->leePagoproveedor($filtros, true);
        $camposFiltro = PagoproveedorListadoFiltros::camposQbeDisponibles();
        foreach ($camposFiltro as $key => $meta) {
            $camposFiltro[$key]['label'] = $etiquetas[$key] ?? $etiquetasInstalacion[$key] ?? $meta['label'];
        }
        $filtrosQuery = PagoproveedorListadoFiltros::paraQueryString($filtros);
        $filtrosQuery['columnas'] = implode(',', $columnasVisibles);
        if ($request->boolean('filtro_limpiar')) {
            $filtrosQuery['filtro_limpiar'] = 1;
        }
        if ($vistaActiva) {
            $filtrosQuery['vista_id'] = $vistaActiva->id;
        } elseif ($forzarEstandar) {
            $filtrosQuery['vista_estandar'] = 1;
        }

        $graficoSeries = $this->seriesGraficos($filtros, $etiquetas);

        return [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => $camposFiltro,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'columnasVisibles' => $columnasVisibles,
            'grillaLayout' => $grillaLayout,
            'catalogoColumnas' => $catalogo,
            'etiquetasColumnas' => $etiquetas,
            'etiquetasInstalacion' => $etiquetasInstalacion,
            'vistasListado' => $vistas,
            'vistaActiva' => $vistaActiva,
            'workbenchListo' => ListadoVistaSupport::tablasDisponibles(),
            'cortes' => app(PagoproveedorListadoUnificadoSupport::class)->cortes($filtros, $etiquetas),
            'graficoSeries' => $graficoSeries,
            'graficoSerie' => $graficoSeries[0] ?? ['labels' => [], 'series' => [], 'tipo' => '', 'titulo' => ''],
            'rolesVista' => $this->rolesParaVistaInstalacion(),
            'enviosProgramados' => ListadoEnvioProgramado::query()
                ->where('usuario_id', (int) auth()->id())
                ->where('recurso', PagoproveedorListadoColumnas::RECURSO)
                ->where('activo', true)
                ->orderByDesc('id')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $etiquetas
     * @return list<array<string, mixed>>
     */
    private function seriesGraficos(array $filtros, array $etiquetas): array
    {
        $lista = is_array($filtros['graficos'] ?? null) ? $filtros['graficos'] : [];
        if ($lista === [] && ($filtros['grafico']['tipo'] ?? '') !== '') {
            $lista = [$filtros['grafico']];
        }
        $out = [];
        foreach ($lista as $grafico) {
            if (! is_array($grafico) || ($grafico['tipo'] ?? '') === '') {
                continue;
            }
            $para = $filtros;
            $para['grafico'] = $grafico;
            $out[] = ListadoVisualSupport::serieDeConsulta(
                $para,
                PagoproveedorListadoFiltros::camposOrdenables(),
                $etiquetas,
                fn (array $consulta): array => app(PagoproveedorListadoUnificadoSupport::class)->cortes($consulta, $etiquetas)
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $etiquetas
     * @return array{tipo: string, dimension: string, medida: string, labels: list<string>, valores: list<float>, titulo: string}
     */
    private function serieGrafico(array $filtros, array $etiquetas): array
    {
        return $this->seriesGraficos($filtros, $etiquetas)[0] ?? PagoproveedorListadoAnalisisSupport::serie(
            PagoproveedorListadoAnalisisSupport::graficoVacio(),
            ['filas' => []],
            $etiquetas
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Rol>
     */
    private function rolesParaVistaInstalacion()
    {
        if ((string) session('rol_nombre') !== 'administrador' || ! ListadoVistaSupport::columnaRolDisponible()) {
            return collect();
        }

        return Rol::query()->orderBy('nombre')->get(['id', 'nombre']);
    }

    private function asignarVistaAlRol(ListadoVista $vista, Request $request): void
    {
        if ((string) session('rol_nombre') !== 'administrador' || ! Schema::hasColumn('listado_vista', 'rol_id')) {
            return;
        }
        $rolId = (int) $request->input('rol_id', 0);
        if ($rolId > 0 && ! Rol::query()->whereKey($rolId)->exists()) {
            return;
        }
        if ($rolId > 0) {
            ListadoVista::query()
                ->where('recurso', PagoproveedorListadoColumnas::RECURSO)
                ->where('rol_id', $rolId)
                ->where('id', '!=', $vista->id)
                ->get()
                ->each(function (ListadoVista $otra) {
                    $otra->rol_id = null;
                    $otra->save();
                });
        }
        $vista->rol_id = $rolId > 0 ? $rolId : null;
        $vista->save();
    }

    public function enviarListado(Request $request)
    {
        can('listar-pagoproveedor');
        $email = trim((string) $request->input('email', ''));
        $filtros = $this->resolverFiltrosConVista($request);
        $qs = PagoproveedorListadoFiltros::paraQueryString($filtros);
        if ($request->filled('vista_id')) {
            $qs['vista_id'] = (int) $request->input('vista_id');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('pagoproveedor', $qs)->with('error', 'El correo no es válido.');
        }

        $frecuencia = (string) $request->input('programar', '');
        if (in_array($frecuencia, ['diaria', 'semanal'], true)) {
            ListadoEnvioProgramado::query()->create([
                'recurso' => PagoproveedorListadoColumnas::RECURSO,
                'usuario_id' => (int) auth()->id(),
                'email' => $email,
                'frecuencia' => $frecuencia,
                'filtros_json' => $filtros,
                'activo' => true,
            ]);

            return redirect()->route('pagoproveedor', $qs)->with(
                'mensaje',
                $frecuencia === 'semanal'
                    ? 'El listado queda programado cada lunes a '.$email.'.'
                    : 'El listado queda programado todos los días a '.$email.'.'
            );
        }

        try {
            $filas = PagoproveedorListadoEnvioSupport::enviar($filtros, $email);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('pagoproveedor', $qs)
                ->with('error', 'No se pudo enviar el listado. Revisá el correo o la configuración de mail.');
        }

        $recorte = $filas >= PagoproveedorListadoEnvioSupport::MAX_FILAS;
        $aviso = 'Listado enviado a '.$email.'.';
        if ($recorte) {
            $aviso .= ' El archivo trae hasta '.number_format(PagoproveedorListadoEnvioSupport::MAX_FILAS, 0, ',', '.').' filas.';
        }

        return redirect()->route('pagoproveedor', $qs)->with('mensaje', $aviso);
    }

    public function quitarGraficoListado(Request $request)
    {
        can('listar-pagoproveedor');
        $filtros = $this->resolverFiltrosConVista($request);
        $filtros['grafico'] = PagoproveedorListadoAnalisisSupport::graficoVacio();
        $filtros['graficos'] = [];
        $filtros['grafico_off'] = true;
        $filtros['grafico_click'] = '';
        $filtros['grafico_click_dimension'] = '';
        if ($request->filled('vista_id')) {
            $vista = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                PagoproveedorListadoColumnas::RECURSO,
                (int) auth()->id()
            );
            if ($vista && (int) $vista->usuario_id === (int) auth()->id() && is_array($vista->filtros_json)) {
                $json = $vista->filtros_json;
                $json['grafico'] = PagoproveedorListadoAnalisisSupport::graficoVacio();
                $json['graficos'] = [];
                $vista->filtros_json = $json;
                $vista->save();
            }
        }
        $qs = PagoproveedorListadoFiltros::paraQueryString($filtros);
        if ($request->filled('vista_id')) {
            $qs['vista_id'] = (int) $request->input('vista_id');
        }

        return redirect()->route('pagoproveedor', $qs)->with('mensaje', 'Se quitó el gráfico.');
    }

    public function bajaEnvioProgramado(int $id)
    {
        can('listar-pagoproveedor');
        $envio = ListadoEnvioProgramado::query()
            ->where('usuario_id', (int) auth()->id())
            ->whereKey($id)
            ->first();
        if ($envio) {
            $envio->activo = false;
            $envio->save();
        }

        return redirect()->back()->with('mensaje', 'Se dio de baja el envío programado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosConVista(Request $request, ?string $busquedaRuta = null): array
    {
        $usuarioId = auth()->id() ? (int) auth()->id() : null;
        $vistaActiva = null;
        if ($request->filled('vista_id')) {
            $vistaActiva = ListadoVistaSupport::findParaUsuario(
                (int) $request->input('vista_id'),
                PagoproveedorListadoColumnas::RECURSO,
                $usuarioId
            );
        }
        $filtros = ListadoVistaSupport::prepararQbeContraVista(
            $this->resolverFiltrosListado($request, $busquedaRuta),
            $request
        );
        if ($vistaActiva && is_array($vistaActiva->filtros_json)) {
            $filtros = PagoproveedorListadoFiltros::fusionarDesdeVista($filtros, $vistaActiva->filtros_json);
        }
        unset($filtros['_qbe_explicito'], $filtros['_limpiar'], $filtros['_grafico_explicito'], $filtros['_formato_explicito'], $filtros['_calculadas_explicito']);

        return PagoproveedorListadoFiltros::aplicarClickGrafico($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(object $data): array
    {
        return [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'moneda_query' => $this->monedaRepository->all(),
            'caja_query' => $this->cajaRepository->all(),
            'chequera_query' => $this->chequeraRepository->all(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'caracter_enum' => Cheque::$enumCaracter,
            'para_dep_enum' => Cheque::$enumParaDep,
            'negociable_enum' => Cheque::$enumNegociable,
            'modos' => Pagoproveedor::$enumModoCotizacion,
        ];
    }
}
