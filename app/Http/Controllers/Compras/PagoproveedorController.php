<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PagoproveedorListadoExport;
use App\Http\Controllers\Controller;
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
use App\Support\Compras\PagoproveedorAplicacionLadoSupport;
use App\Support\Compras\PagoproveedorDocumentosRelacionadosSupport;
use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use App\Support\Compras\PropuestaPagoModoSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PagoproveedorController extends Controller
{
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

        $filtros = $this->resolverFiltrosListado($request);
        $filtrosQuery = PagoproveedorListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->pagoproveedorRepository->leePagoproveedor($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $camposFiltro = PagoproveedorListadoFiltros::CAMPOS;

        return view('compras.pagoproveedor.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'empresa_query',
            'camposFiltro'
        ));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-pagoproveedor');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
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
            ])->setPaper('legal', 'landscape');

            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $path = $dir.'/listado_pagoproveedor.pdf';
            $pdf->save($path);

            return response()->file($path);
        }

        $export = app(PagoproveedorListadoExport::class)->parametros($filtros);
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
        can('editar-pagoproveedor');

        $data = $this->pagoproveedorRepository->find($id);

        return view('compras.pagoproveedor.editar', $this->datosFormulario($data));
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

            return redirect()->route('pagoproveedor')->with('mensaje', $mensaje);
        } catch (\Throwable $e) {
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
        if (! can('listar-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
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
        if (! can('listar-pagoproveedor', false) && ! can('listar-cuentacorriente-proveedor', false)) {
            abort(403);
        }

        return $this->pagoproveedorComprobantePdfService->generarRespuesta($id);
    }

    public function imprimirRetencion(int $id, int $retencionId)
    {
        can('listar-pagoproveedor');

        return $this->pagoproveedorComprobantePdfService->streamRetencion($id, $retencionId);
    }

    public function datosEnvioProveedor(int $id)
    {
        if (! can('listar-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        return response()->json($this->pagoproveedorEnvioProveedorService->datosEnvio($id));
    }

    public function enviarProveedor(Request $request, int $id)
    {
        if (! can('listar-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['mensaje' => 'error', 'errores' => 'Sin permisos para enviar la OP.'], 403);
        }

        $request->validate([
            'email' => 'required|string|max:500',
            'mensaje' => 'nullable|string|max:4000',
        ]);

        $ret = $this->pagoproveedorEnvioProveedorService->enviar(
            $id,
            $request->input('email'),
            $request->input('mensaje')
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
