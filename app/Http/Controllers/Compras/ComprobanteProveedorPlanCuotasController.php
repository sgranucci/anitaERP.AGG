<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Condicionpago;
use App\Services\Compras\ComprobanteProveedorPlanCuotasService;
use App\Support\Compras\ComprobanteProveedorIngresoEgresoEdicionSupport;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use Illuminate\Http\Request;
use RuntimeException;

class ComprobanteProveedorPlanCuotasController extends Controller
{
    public function __construct(
        private ComprobanteProveedorPlanCuotasService $planService,
    ) {}

    public function index(Request $request)
    {
        can('editar-plan-cuotas-comprobante-proveedor');

        $texto = trim((string) $request->query('q', ''));
        $comprobantes = $texto !== '' ? $this->planService->buscar($texto) : [];
        $filas = [];
        foreach ($comprobantes as $comprobante) {
            $cotizaciones = $comprobante->comprobante_proveedor_cuotas
                ->pluck('cotizacion')
                ->map(fn ($c) => round((float) $c, 4))
                ->unique()
                ->values();
            $filas[] = [
                'comprobante' => $comprobante,
                'etiqueta' => $this->planService->etiqueta($comprobante),
                'cuotas' => $comprobante->comprobante_proveedor_cuotas->count(),
                'cotizaciones' => $cotizaciones->count(),
                'impago' => ! ComprobanteProveedorPagoSupport::tienePagosAplicados((int) $comprobante->id),
            ];
        }

        return view('compras.comprobante_proveedor.plan_cuotas.index', [
            'texto' => $texto,
            'filas' => $filas,
        ]);
    }

    public function editar(int $id)
    {
        can('editar-plan-cuotas-comprobante-proveedor');

        $comprobante = $this->comprobante($id);
        $comprobante->loadMissing('caja_movimientos');
        $cotizaciones = $comprobante->comprobante_proveedor_cuotas
            ->pluck('cotizacion')
            ->map(fn ($c) => round((float) $c, 4))
            ->unique()
            ->sort()
            ->values();

        return view('compras.comprobante_proveedor.plan_cuotas.editar', [
            'data' => $comprobante,
            'etiqueta' => $this->planService->etiqueta($comprobante),
            'cuotas' => $comprobante->comprobante_proveedor_cuotas->sortBy('numero_cuota')->values(),
            'condiciones' => Condicionpago::query()->orderBy('nombre')->get(),
            'cotizaciones_distintas' => $cotizaciones,
            'impago' => ! ComprobanteProveedorPagoSupport::tienePagosAplicados($id),
            'bloqueado_ingreso_egreso' => ComprobanteProveedorIngresoEgresoEdicionSupport::es($comprobante),
            'url_ingreso_egreso' => ComprobanteProveedorIngresoEgresoEdicionSupport::urlMovimiento($comprobante),
            'numero_ingreso_egreso' => optional($comprobante->caja_movimientos)->numerotransaccion,
        ]);
    }

    public function actualizar(Request $request, int $id)
    {
        can('editar-plan-cuotas-comprobante-proveedor');

        $accion = (string) $request->input('accion', 'manual');
        $condicionId = (int) $request->input('condicionpago_id', 0) ?: null;
        $fechas = $request->input('cuota_fechavencimiento', []);
        $montos = $request->input('cuota_monto', []);
        $formas = $request->input('cuota_formapago_id', []);
        $filas = [];
        foreach ($fechas as $i => $fecha) {
            $filas[] = [
                'fechavencimiento' => $fecha,
                'monto' => $montos[$i] ?? 0,
                'formapago_id' => $formas[$i] ?? 1,
            ];
        }

        try {
            $mensaje = $this->planService->aplicar($id, $accion, $condicionId, $filas);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('editar_plan_cuotas_comprobante_proveedor', $id)
                ->withInput()
                ->with('errores', [$e->getMessage()]);
        }

        return redirect()
            ->route('editar_plan_cuotas_comprobante_proveedor', $id)
            ->with('mensaje', $mensaje);
    }

    private function comprobante(int $id): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()
            ->with([
                'tipotransaccion_compras',
                'proveedores',
                'monedas',
                'condicionpagos',
                'comprobante_proveedor_cuotas',
            ])
            ->find($id);

        abort_if($comprobante === null, 404);

        return $comprobante;
    }
}
