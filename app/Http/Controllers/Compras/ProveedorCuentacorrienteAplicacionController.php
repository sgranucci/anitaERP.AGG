<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAplicacionCuentacorrienteProveedor;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionService;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionMatcherSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ProveedorCuentacorrienteAplicacionController extends Controller
{
    public function __construct(
        private Proveedor_CuentacorrienteRepositoryInterface $cuentacorrienteRepository,
        private ProveedorRepositoryInterface $proveedorRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private ProveedorCuentacorrienteAplicacionService $aplicacionService,
    ) {}

    public function index(Request $request)
    {
        can('aplicar-cuentacorriente-proveedor');

        $proveedorId = (int) $request->query('proveedor_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        $proveedor = null;
        if ($proveedorId > 0) {
            try {
                $proveedor = $this->proveedorRepository->find($proveedorId);
            } catch (ModelNotFoundException) {
                $proveedorId = 0;
            }
        }

        $creditos = collect();
        $deudas = collect();
        $recientes = collect();
        $kpis = [
            'creditos' => 0.0,
            'deudas' => 0.0,
            'nc' => 0.0,
            'pagos' => 0.0,
            'vencida' => 0.0,
        ];

        if ($proveedor) {
            $payload = $this->armarWorkbench($proveedorId, $empresaId);
            $creditos = $payload['creditos'];
            $deudas = $payload['deudas'];
            $recientes = $payload['recientes'];
            $kpis = $payload['kpis'];
        }

        return view('compras.aplicacion_cuentacorriente.index', [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'proveedor' => $proveedor,
            'proveedor_id' => $proveedorId,
            'empresa_id' => $empresaId,
            'fecha' => $request->query('fecha', now()->format('Y-m-d')),
            'creditos' => $creditos,
            'deudas' => $deudas,
            'recientes' => $recientes,
            'kpis' => $kpis,
        ]);
    }

    public function apiPendientes(Request $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-proveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->query('proveedor_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($proveedorId <= 0) {
            return response()->json(['creditos' => [], 'deudas' => [], 'recientes' => [], 'kpis' => [
                'creditos' => 0, 'deudas' => 0, 'nc' => 0, 'pagos' => 0, 'vencida' => 0,
            ]]);
        }

        return response()->json($this->armarWorkbench($proveedorId, $empresaId));
    }

    public function apiSugerir(Request $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-proveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->query('proveedor_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        $modo = (string) $request->query('modo', 'fifo');
        if ($proveedorId <= 0) {
            return response()->json(['lineas' => []]);
        }

        $payload = $this->armarWorkbench($proveedorId, $empresaId);
        $creditos = $payload['creditos'];
        $deudas = $payload['deudas'];
        $lineas = $modo === 'parear'
            ? ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirParearImportes($creditos, $deudas)
            : ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifo($creditos, $deudas);

        return response()->json(['lineas' => $lineas, 'modo' => $modo === 'parear' ? 'parear' : 'fifo']);
    }

    public function aplicar(ValidacionAplicacionCuentacorrienteProveedor $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-proveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }

        $lineas = [];
        foreach ($request->input('lineas', []) as $linea) {
            $lineas[] = [
                'credito_id' => (int) ($linea['credito_id'] ?? 0),
                'deuda_id' => (int) ($linea['deuda_id'] ?? 0),
                'monto' => (float) ($linea['monto'] ?? 0),
            ];
        }

        try {
            $resultado = $this->aplicacionService->aplicar(
                (int) $request->input('proveedor_id'),
                (string) $request->input('fecha'),
                $lineas
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No se pudo aplicar: '.$e->getMessage()], 500);
        }

        $payload = $this->armarWorkbench(
            (int) $request->input('proveedor_id'),
            (int) $request->query('empresa_id', $request->input('empresa_id', 0))
        );

        return response()->json([
            'ok' => true,
            'mensaje' => $resultado['aplicadas'].' aplicación(es) por '.number_format($resultado['monto'], 2, ',', '.'),
            'resultado' => $resultado,
            'workbench' => $payload,
        ]);
    }

    public function desaplicar(Request $request, int $id): JsonResponse
    {
        if (! can('desaplicar-cuentacorriente-proveedor', false) && ! can('aplicar-cuentacorriente-proveedor', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $proveedorId = (int) $request->input('proveedor_id', 0);
        if ($proveedorId <= 0) {
            return response()->json(['error' => 'Indique el proveedor.'], 422);
        }

        try {
            $this->aplicacionService->desaplicar($id, $proveedorId);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No se pudo desaplicar: '.$e->getMessage()], 500);
        }

        $payload = $this->armarWorkbench($proveedorId, (int) $request->input('empresa_id', 0));

        return response()->json([
            'ok' => true,
            'mensaje' => 'Aplicación revertida.',
            'workbench' => $payload,
        ]);
    }

    /**
     * @return array{creditos: list<array<string, mixed>>, deudas: list<array<string, mixed>>, recientes: list<array<string, mixed>>, kpis: array<string, float>}
     */
    private function armarWorkbench(int $proveedorId, int $empresaId): array
    {
        $creditos = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion($proveedorId, 'credito', $empresaId > 0 ? $empresaId : null)
            ->map(fn ($fila) => ProveedorCuentacorrienteAplicacionFilaSupport::desdeModelo($fila))
            ->values()
            ->all();
        $deudas = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion($proveedorId, 'deuda', $empresaId > 0 ? $empresaId : null)
            ->map(fn ($fila) => ProveedorCuentacorrienteAplicacionFilaSupport::desdeModelo($fila))
            ->values()
            ->all();

        $recientes = $this->cuentacorrienteRepository
            ->listarAplicacionesManualesRecientes($proveedorId, $empresaId > 0 ? $empresaId : null)
            ->map(function ($apl) {
                $deudaCc = $apl->proveedor_cuentacorrientes;
                $creditoCc = $apl->proveedor_cuentacorriente_aplicados;

                return [
                    'id' => (int) $apl->id,
                    'fecha' => optional($apl->fecha)->format('Y-m-d'),
                    'monto' => round(abs((float) $apl->total), 4),
                    'moneda' => $apl->monedas->abreviatura ?? '',
                    'deuda' => $deudaCc
                        ? ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                            $deudaCc,
                            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($deudaCc, 'deuda')
                        )
                        : (string) $apl->comprobanteaplicado,
                    'credito' => $creditoCc
                        ? ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
                            $creditoCc,
                            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($creditoCc, 'credito')
                        )
                        : '',
                    'deuda_id' => (int) $apl->proveedor_cuentacorriente_id,
                    'credito_id' => (int) ($apl->proveedor_cuentacorriente_aplicado_id ?? 0),
                ];
            })
            ->values()
            ->all();

        $kpis = [
            'creditos' => 0.0,
            'deudas' => 0.0,
            'nc' => 0.0,
            'pagos' => 0.0,
            'vencida' => 0.0,
        ];
        foreach ($creditos as $c) {
            $kpis['creditos'] += (float) $c['saldo'];
            if ($c['tipo'] === ProveedorCuentacorrienteAplicacionFilaSupport::TIPO_NC) {
                $kpis['nc'] += (float) $c['saldo'];
            } else {
                $kpis['pagos'] += (float) $c['saldo'];
            }
        }
        foreach ($deudas as $d) {
            $kpis['deudas'] += (float) $d['saldo'];
            if (in_array($d['aging'], ['vencida', '30', '60'], true)) {
                $kpis['vencida'] += (float) $d['saldo'];
            }
        }
        foreach ($kpis as $k => $v) {
            $kpis[$k] = round($v, 2);
        }

        return compact('creditos', 'deudas', 'recientes', 'kpis');
    }
}
