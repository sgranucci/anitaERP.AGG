<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPagoproveedor;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Proveedor;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Compras\PagoproveedorService;
use App\Services\Compras\RetencionesPagoCalculator;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PagoproveedorController extends Controller
{
    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private PagoproveedorService $pagoproveedorService,
        private EmpresaRepositoryInterface $empresaRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private CajaRepositoryInterface $cajaRepository,
        private ChequeraRepositoryInterface $chequeraRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private Proveedor_CuentacorrienteRepositoryInterface $proveedorCuentacorrienteRepository,
        private RetencionesPagoCalculator $retencionesPagoCalculator,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-pagoproveedor');

        $filtros = [
            'empresa_id' => $request->query('empresa_id'),
            'proveedor_id' => $request->query('proveedor_id'),
            'estado' => $request->query('estado'),
            'fecha_desde' => $request->query('fecha_desde'),
            'fecha_hasta' => $request->query('fecha_hasta'),
            'numero' => $request->query('numero'),
        ];
        $filtrosQuery = array_filter($filtros, static fn ($v) => $v !== null && $v !== '');

        $coleccion = $this->pagoproveedorRepository->leePagoproveedor($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('compras.pagoproveedor.index', compact('coleccion', 'filtros', 'filtrosQuery', 'empresa_query'));
    }

    public function crear(Request $request)
    {
        can('crear-pagoproveedor');

        $proveedorId = (int) $request->query('proveedor_id', 0);
        $data = (object) [
            'proveedor_id' => $proveedorId ?: null,
            'caja_movimientos' => collect(),
            'cheques' => collect(),
            'pagoproveedor_estados' => collect(),
            'pagoproveedor_retenciones' => collect(),
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

        $resultado = $this->pagoproveedorService->guardaPago($request);
        if (! empty($resultado['errores'])) {
            return back()->withErrors(['error' => $resultado['errores']])->withInput();
        }

        return redirect()
            ->route('editar_pagoproveedor', $resultado['pagoproveedor_id'])
            ->with('mensaje', 'Orden de pago grabada.');
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

        return redirect()
            ->route('editar_pagoproveedor', $id)
            ->with('mensaje', 'Orden de pago actualizada.');
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
        if ($proveedorId <= 0) {
            return response()->json(['filas' => []]);
        }

        $filas = $this->proveedorCuentacorrienteRepository->listarDeudaProveedor('', $proveedorId, false);
        if ($empresaId > 0) {
            $filas = $filas->where('empresa_id', $empresaId)->values();
        }

        $out = $filas->map(static function ($cc) {
            $comp = $cc->comprobante_proveedores;
            $aplicado = (float) ($cc->aplicado ?? 0);
            $saldo = abs((float) $cc->total + $aplicado);

            return [
                'id' => $cc->id,
                'fecha' => optional($cc->fecha)->format('Y-m-d'),
                'vencimiento' => optional($cc->fechavencimiento)->format('Y-m-d'),
                'comprobante' => $comp
                    ? sprintf(
                        '%s %s-%04d-%s',
                        $comp->tipotransaccion_compras?->abreviatura ?? 'FAC',
                        $comp->letra,
                        (int) $comp->sucursal,
                        $comp->numerocomprobante
                    )
                    : 'CC#'.$cc->id,
                'moneda_id' => (int) $cc->moneda_id,
                'moneda' => $cc->monedas?->abreviatura,
                'cotizacion' => (float) $cc->cotizacion,
                'total' => (float) $cc->total,
                'saldo' => round($saldo, 4),
                'ordencompra_id' => $comp?->ordencompra_id,
            ];
        })->values();

        return response()->json(['filas' => $out]);
    }

    public function apiCalcularRetenciones(Request $request)
    {
        if (! can('crear-pagoproveedor', false) && ! can('editar-pagoproveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->input('proveedor_id', 0);
        $proveedor = Proveedor::query()->find($proveedorId);
        if ($proveedor === null) {
            return response()->json(['error' => 'Proveedor no encontrado'], 422);
        }

        $resultado = $this->retencionesPagoCalculator->calcular(new RetencionesPagoInput(
            proveedor: $proveedor,
            importeNetoPago: (float) $request->input('importe_neto', 0),
            importeIvaPago: (float) $request->input('importe_iva', 0),
            fecha: $request->input('fecha'),
            retenciongananciaIdPago: $request->filled('retencionganancia_id') ? (int) $request->input('retencionganancia_id') : null,
            retencionivaIdPago: $request->filled('retencioniva_id') ? (int) $request->input('retencioniva_id') : null,
            retencionsussIdPago: $request->filled('retencionsuss_id') ? (int) $request->input('retencionsuss_id') : null,
            iibbProvinciaId: $request->filled('iibb_provincia_id') ? (int) $request->input('iibb_provincia_id') : null,
            iibbTasaOverride: $request->filled('iibb_tasa') ? (float) $request->input('iibb_tasa') : null,
        ));

        return response()->json([
            'ganancias' => [
                'aplica' => $resultado->ganancias->aplica,
                'importe' => $resultado->ganancias->importeRetencion,
                'alicuota' => $resultado->ganancias->alicuotaAplicada,
                'motivo' => $resultado->ganancias->motivo,
                'detalle' => $resultado->ganancias->detalle,
            ],
            'iva' => [
                'aplica' => $resultado->iva->aplica,
                'importe' => $resultado->iva->importeRetencion,
                'alicuota' => $resultado->iva->alicuotaAplicada,
                'motivo' => $resultado->iva->motivo,
                'detalle' => $resultado->iva->detalle,
            ],
            'suss' => [
                'aplica' => $resultado->suss->aplica,
                'importe' => $resultado->suss->importeRetencion,
                'alicuota' => $resultado->suss->alicuotaAplicada,
                'motivo' => $resultado->suss->motivo,
                'detalle' => $resultado->suss->detalle,
            ],
            'iibb' => [
                'aplica' => $resultado->iibb->aplica,
                'importe' => $resultado->iibb->importeRetencion,
                'alicuota' => $resultado->iibb->alicuotaAplicada,
                'motivo' => $resultado->iibb->motivo,
                'detalle' => $resultado->iibb->detalle,
                'provincia_id' => $resultado->iibb->detalle['provincia_id'] ?? null,
            ],
            'total' => $resultado->totalRetenciones(),
        ]);
    }

    public function imprimir(int $id)
    {
        can('listar-pagoproveedor');

        $pago = $this->pagoproveedorRepository->find($id);
        $pdf = Pdf::loadView('compras.pagoproveedor.comprobante', [
            'pago' => $pago,
            'retenciones' => $pago->pagoproveedor_retenciones,
            'aplicaciones' => $pago->pagoproveedor_comprobantes,
        ])->setPaper('a4');

        $dir = storage_path('pdf/pagoproveedor');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/op_'.$pago->id.'.pdf';
        $pdf->save($path);

        return response()->file($path);
    }

    public function imprimirRetencion(int $id, int $retencionId)
    {
        can('listar-pagoproveedor');

        $pago = $this->pagoproveedorRepository->find($id);
        $retencion = $pago->pagoproveedor_retenciones->firstWhere('id', $retencionId);
        if ($retencion === null) {
            abort(404);
        }

        $pdf = Pdf::loadView('compras.pagoproveedor.retencion', [
            'pago' => $pago,
            'retencion' => $retencion,
        ])->setPaper('a4');

        return $pdf->stream('retencion_'.$retencion->id.'.pdf');
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
            'modos' => Pagoproveedor::$enumModoCotizacion,
        ];
    }
}
