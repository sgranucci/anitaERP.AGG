<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\CumplimientoRequisicionCompraListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Requisicion;
use App\Models\Stock\Depmae;
use App\Repositories\Compras\CumplimientoRequisicionCompraRepositoryInterface;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\CumplimientoRequisicionCompraRevertirService;
use App\Services\Compras\CumplirRequisicionCompraPdfService;
use App\Services\Compras\CumplirRequisicionCompraService;
use App\Services\Compras\RequisicionArticuloCambioService;
use App\Support\Compras\CumplimientoRequisicionCompraListadoFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CumplirRequisicionCompraController extends Controller
{
    public function __construct(
        private CumplirRequisicionCompraService $service,
        private CumplirRequisicionCompraPdfService $pdfService,
        private CumplimientoRequisicionCompraRepositoryInterface $cumplimientoRepository,
        private CumplimientoRequisicionCompraRevertirService $revertirService,
        private EmpresaRepositoryInterface $empresaRepository,
        private Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository,
        private RequisicionArticuloCambioService $articuloCambioService,
    ) {
    }

    public function index(Request $request)
    {
        can('cumplir-requisicion-compra');

        $filtros = CumplimientoRequisicionCompraListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = CumplimientoRequisicionCompraListadoFiltros::paraQueryString($filtros);
        $coleccion = $this->cumplimientoRepository->leeCumplimientos($filtros, true);
        $camposFiltro = CumplimientoRequisicionCompraListadoFiltros::CAMPOS;

        return view('compras.cumplir_requisicion_compra.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'camposFiltro',
        ));
    }

    public function crear(Request $request)
    {
        can('cumplir-requisicion-compra');

        $requisicionId = (int) $request->query('requisicion_id', 0);
        $requisicion = null;
        $lineas = collect();
        $errorCarga = null;
        $pdfToken = session('cumple_pdf_token');

        if ($requisicionId > 0) {
            $carga = $this->service->cargarRequisicion($requisicionId);
            if ($carga['ok'] ?? false) {
                $requisicion = $carga['requisicion'];
                $lineas = $carga['lineas'];
            } else {
                $errorCarga = $carga['mensaje'] ?? 'No se pudo cargar la requisici&oacute;n.';
            }
        }

        return view('compras.cumplir_requisicion_compra.crear', [
            'requisicion' => $requisicion,
            'lineas' => $lineas,
            'errorCarga' => $errorCarga,
            'pdfToken' => $pdfToken,
            'estados_cumplir' => CumplirRequisicionCompraService::estadosPermitidosParaCumplir(),
            'puedeCambiarArticulo' => can('cambiar-articulo-cumplir-requisicion-compra', false),
        ]);
    }

    public function consultar(int $id)
    {
        can('cumplir-requisicion-compra');

        $cumplimiento = $this->cumplimientoRepository->findConDetalle($id);
        if (! $cumplimiento) {
            return redirect()->route('cumplir_requisicion_compra')
                ->with('mensaje-error', 'Cumplimiento no encontrado.');
        }

        $requisiciones = [];
        foreach ($cumplimiento->articulos as $linea) {
            $req = $linea->requisicion;
            if ($req) {
                $requisiciones[(int) $req->id] = $req;
            }
        }

        return view('compras.cumplir_requisicion_compra.consultar', [
            'cumplimiento' => $cumplimiento,
            'requisiciones' => array_values($requisiciones),
            'cambiosArticulo' => $this->articuloCambioService->listarPorCumplimiento((int) $cumplimiento->id),
        ]);
    }

    public function consultaRequisicion(Request $request)
    {
        can('cumplir-requisicion-compra');

        $busqueda = trim((string) $request->query('q', ''));
        $estados = CumplirRequisicionCompraService::estadosPermitidosParaCumplir();
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $query = Requisicion::query()
            ->with(['centrocostos', 'empresas'])
            ->whereIn('estado', $estados)
            ->orderByDesc('id')
            ->limit(30);

        if (! empty($empresas)) {
            $query->whereIn('empresa_id', $empresas);
        }

        if ($busqueda !== '') {
            if (ctype_digit($busqueda)) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numerorequisicion', (int) $busqueda)
                        ->orWhere('id', (int) $busqueda);
                });
            } else {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('comentario', 'like', '%'.$busqueda.'%')
                        ->orWhere('detalle', 'like', '%'.$busqueda.'%');
                });
            }
        }

        $filas = $query->get()->map(function (Requisicion $row) {
            return [
                'id' => $row->id,
                'numerorequisicion' => $row->numerorequisicion,
                'fecha' => $row->fecha ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y') : '',
                'estado' => $row->estado,
                'empresa' => $row->empresas?->nombre,
                'centrocosto' => trim(($row->centrocostos?->codigo ?? '').' '.$row->centrocostos?->nombre),
            ];
        });

        return response()->json(['data' => $filas]);
    }

    public function saldoArticuloDeposito(Request $request): JsonResponse
    {
        can('cumplir-requisicion-compra');

        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = (int) $request->query('deposito_id', 0);
        if ($articuloId <= 0 || $depositoId <= 0) {
            return response()->json(['saldo' => null]);
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null) {
            return response()->json(['error' => 'Depósito no encontrado.'], 404);
        }
        if (! Depmae::autorizadoParaUsuarioYEmpresa((int) $deposito->id, (int) $deposito->empresa_id)) {
            return response()->json(['error' => 'Depósito no autorizado.'], 403);
        }
        if (! UsuarioDepositoAutorizado::depositoAutorizado((int) $deposito->id)) {
            return response()->json(['error' => 'No tiene permiso para operar sobre este depósito.'], 403);
        }

        return response()->json([
            'saldo' => $this->saldoDepositoRepository->saldo($articuloId, $depositoId),
        ]);
    }

    public function datosRequisicion(int $id)
    {
        can('cumplir-requisicion-compra');

        $carga = $this->service->cargarRequisicion($id);
        if (! ($carga['ok'] ?? false)) {
            return response()->json(['ok' => false, 'mensaje' => $carga['mensaje'] ?? 'Error'], 422);
        }

        /** @var Requisicion $req */
        $req = $carga['requisicion'];

        $lineas = $carga['lineas']->map(function ($linea) {
            $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);

            return [
                'id' => $linea->id,
                'articulo_id' => $linea->articulo_id,
                'sku' => $linea->articulos?->sku,
                'descripcion' => $linea->articulos?->descripcion ?? $linea->detalle,
                'cantidad' => (float) $linea->cantidad,
                'cantidadentregada' => (float) ($linea->cantidadentregada ?? 0),
                'pendiente' => $pendiente,
                'precio' => (float) ($linea->precio ?? 0),
                'moneda' => $linea->monedas?->abreviatura ?? $linea->monedas?->nombre,
                'centrocosto_destino' => trim(($linea->centrocostos_destino?->codigo ?? '').' '.($linea->centrocostos_destino?->nombre ?? '')),
            ];
        });

        return response()->json([
            'ok' => true,
            'requisicion' => [
                'id' => $req->id,
                'numerorequisicion' => $req->numerorequisicion,
                'fecha' => $req->fecha ? \Carbon\Carbon::parse($req->fecha)->format('d/m/Y') : '',
                'estado' => $req->estado,
                'empresa' => $req->empresas?->nombre,
                'empresa_id' => $req->empresa_id,
                'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.$req->centrocostos?->nombre),
                'comentario' => $req->comentario,
            ],
            'lineas' => $lineas,
        ]);
    }

    public function grabar(Request $request)
    {
        can('cumplir-requisicion-compra');

        $lineas = $request->input('lineas', []);
        if (! is_array($lineas) || $lineas === []) {
            return redirect()->route('crear_cumplir_requisicion_compra')
                ->with('mensaje-error', 'Debe cargar al menos una l&iacute;nea para cumplir.');
        }

        $result = $this->service->grabar($request->all());
        if (($result['mensaje'] ?? '') !== 'ok') {
            $requisicionId = (int) $request->input('requisicion_id', 0);
            $params = $requisicionId > 0 ? ['requisicion_id' => $requisicionId] : [];

            return redirect()->route('crear_cumplir_requisicion_compra', $params)
                ->withInput()
                ->with('mensaje-error', $result['errores'] ?? 'Error al grabar cumplimiento.');
        }

        $pdfToken = null;
        if (! empty($result['impresion'])) {
            $pdfToken = $this->pdfService->guardarEnSesion($result['impresion']);
        }

        $msg = 'Cumplimiento N&ordm; '.($result['cumplimiento_numero'] ?? '').' registrado con &eacute;xito.';
        $detalleTm = $result['transferencias_detalle'] ?? [];
        if ($detalleTm !== []) {
            $etiquetas = array_map(static function (array $tm): string {
                $codigo = trim((string) ($tm['codigo'] ?? ''));
                $id = (int) ($tm['id'] ?? 0);

                return $codigo !== '' ? $codigo : '#'.$id;
            }, $detalleTm);
            $msg .= ' Transferencias: '.implode(', ', $etiquetas).'.';
        } elseif (! empty($result['transferencias'])) {
            $msg .= ' Transferencias: '.implode(', ', $result['transferencias']).'.';
        }

        $redirectParams = [];
        $requisicionId = (int) $request->input('requisicion_id', 0);
        if ($requisicionId > 0) {
            $req = Requisicion::query()->find($requisicionId);
            if ($req && $this->service->puedeCumplir($req)) {
                $redirectParams['requisicion_id'] = $requisicionId;
                $msg .= ' La requisici&oacute;n sigue con &iacute;tems pendientes; puede continuar el cumplimiento.';
            }
        }

        return redirect()->route('crear_cumplir_requisicion_compra', $redirectParams)
            ->with('mensaje', $msg)
            ->with('cumple_pdf_token', $pdfToken);
    }

    public function imprimirPdf(Request $request, ?string $token = null)
    {
        can('cumplir-requisicion-compra');

        $token = $token ?? $request->query('token');
        try {
            $bytes = $this->pdfService->generarBytes($token);
        } catch (\Throwable $e) {
            return redirect()->route('cumplir_requisicion_compra')
                ->with('mensaje-error', 'No se pudo generar el PDF: '.$e->getMessage());
        }

        return response($bytes['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bytes['nombre'].'"',
        ]);
    }

    public function imprimirCumplimientoPdf(int $id)
    {
        can('cumplir-requisicion-compra');

        try {
            $bytes = $this->pdfService->generarBytesDesdeCumplimientoId($id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('mensaje-error', 'No se pudo generar el PDF: '.$e->getMessage());
        }

        return response($bytes['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$bytes['nombre'].'"',
        ]);
    }

    public function actualizar(Request $request, int $id)
    {
        can('cumplir-requisicion-compra');

        $result = $this->revertirService->actualizarLeyenda($id, $request->input('leyenda'));
        if (($result['mensaje'] ?? '') !== 'ok') {
            return redirect()->back()
                ->withInput()
                ->with('mensaje-error', $result['errores'] ?? 'Error al actualizar.');
        }

        return redirect()->route('consultar_cumplir_requisicion_compra', ['id' => $id])
            ->with('mensaje', 'Cumplimiento actualizado.');
    }

    public function revertir(Request $request, int $id)
    {
        can('cumplir-requisicion-compra');

        $obs = trim((string) $request->input('observacion_reversion', ''));
        $result = $this->revertirService->revertir($id, $obs);
        if (($result['mensaje'] ?? '') !== 'ok') {
            return redirect()->back()->with('mensaje-error', $result['errores'] ?? 'Error al revertir.');
        }

        return redirect()->route('consultar_cumplir_requisicion_compra', ['id' => $id])
            ->with('mensaje', 'Cumplimiento revertido. Se revirtieron las transferencias asociadas y el estado de las l&iacute;neas de requisici&oacute;n.');
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('cumplir-requisicion-compra');

        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $filtros = CumplimientoRequisicionCompraListadoFiltros::resolverDesdeRequest($request);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            return $this->exportarPdf($filtros);
        }
        if ($formato === 'EXCEL') {
            return Excel::download(
                (new CumplimientoRequisicionCompraListadoExport($this->cumplimientoRepository))->parametros($filtros),
                'cumplimientos_requisicion_compra.xlsx'
            );
        }
        if ($formato === 'CSV') {
            return Excel::download(
                (new CumplimientoRequisicionCompraListadoExport($this->cumplimientoRepository))->parametros($filtros),
                'cumplimientos_requisicion_compra.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return redirect()->route('cumplir_requisicion_compra', CumplimientoRequisicionCompraListadoFiltros::paraQueryString($filtros));
    }

    private function exportarPdf(array $filtros)
    {
        $filas = $this->cumplimientoRepository->leeCumplimientos($filtros, false);
        $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);

        $html = view('compras.cumplir_requisicion_compra.listado', [
            'filas' => $filas,
            'filtros' => $filtros,
            'logos' => $logos,
        ])->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cumplimientos_requisicion_compra.pdf"',
        ]);
    }
}
