<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAplicacionCuentacorrienteProveedor;
use App\Models\Compras\AplicacionCcProveedorComprobante;
use App\Models\Compras\AplicacionCcProveedorComprobanteLinea;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionService;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
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
            $payload = $this->armarWorkbench($proveedorId, 0);
            $creditos = $payload['creditos'];
            $deudas = $payload['deudas'];
            $recientes = $payload['recientes'];
            $kpis = $payload['kpis'];
        }

        $soloConsulta = $request->query('origen') === 'modal_consulta';
        $volverProveedorId = (int) $request->query('volver_proveedor_id', $proveedorId);

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
            'soloConsulta' => $soloConsulta,
            'volverProveedorId' => $volverProveedorId,
            'aplicacionCcInicial' => [
                'creditos' => $creditos,
                'deudas' => $deudas,
                'recientes' => $recientes,
                'kpis' => $kpis,
            ],
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

        return response()->json($this->armarWorkbench($proveedorId, 0));
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

        $payload = $this->armarWorkbench($proveedorId, 0);
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
                'cotizacion_liquidacion' => isset($linea['cotizacion_liquidacion'])
                    ? (float) $linea['cotizacion_liquidacion']
                    : null,
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

        $payload = $this->armarWorkbench((int) $request->input('proveedor_id'), 0);

        $mensaje = $resultado['aplicadas'].' aplicación(es) por '.number_format($resultado['monto'], 2, ',', '.');
        $dc = (float) ($resultado['dc'] ?? 0);
        if (ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            $mensaje .= ' · DC '.number_format(abs($dc), 2, ',', '.').' ('.ProveedorCuentacorrienteAplicacionDcSupport::etiqueta($dc).')';
        }
        $asientos = (int) ($resultado['asientos_dc'] ?? 0);
        if ($asientos > 0) {
            $mensaje .= ' · '.$asientos.' asiento(s) contable(s)';
        }
        $numeros = array_column($resultado['comprobantes'] ?? [], 'numero');
        if ($numeros !== []) {
            $mensaje .= ' · Comprobante '.implode(', ', $numeros);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => $mensaje,
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
            $anulado = $this->aplicacionService->desaplicar($id, $proveedorId);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No se pudo desaplicar: '.$e->getMessage()], 500);
        }

        $payload = $this->armarWorkbench($proveedorId, 0);

        return response()->json([
            'ok' => true,
            'mensaje' => $anulado
                ? 'Comprobante '.$anulado->numeroFormateado().' anulado ('.(int) $anulado->cantidad_lineas.' aplicación(es) revertida(s)).'
                : 'Aplicación revertida.',
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

        $aplicacionesRecientes = $this->cuentacorrienteRepository
            ->listarAplicacionesManualesRecientes($proveedorId, $empresaId > 0 ? $empresaId : null);
        $comprobantesPorAplicacion = AplicacionCcProveedorComprobanteLinea::query()
            ->join('aplicacion_cc_proveedor_comprobante as apc', 'apc.id', '=', 'aplicacion_cc_proveedor_comprobante_linea.comprobante_id')
            ->whereIn('aplicacion_cc_proveedor_comprobante_linea.aplicacion_deuda_id', $aplicacionesRecientes->pluck('id')->all() ?: [0])
            ->where('apc.estado', AplicacionCcProveedorComprobante::ESTADO_EMITIDO)
            ->get([
                'aplicacion_cc_proveedor_comprobante_linea.aplicacion_deuda_id',
                'apc.id as comprobante_id',
                'apc.numero',
                'apc.cantidad_lineas',
            ])
            ->keyBy('aplicacion_deuda_id');

        $recientes = $aplicacionesRecientes
            ->map(function ($apl) use ($comprobantesPorAplicacion) {
                $comp = $comprobantesPorAplicacion->get($apl->id);
                $deudaCc = $apl->proveedor_cuentacorrientes;
                $creditoCc = $apl->proveedor_cuentacorriente_aplicados;
                $deuda = $deudaCc
                    ? ProveedorCuentacorrienteAplicacionFilaSupport::resumenEtiqueta($deudaCc, 'deuda')
                    : ['etiqueta' => (string) $apl->comprobanteaplicado, 'tipo' => '', 'abreviatura' => ''];
                $credito = $creditoCc
                    ? ProveedorCuentacorrienteAplicacionFilaSupport::resumenEtiqueta($creditoCc, 'credito')
                    : ['etiqueta' => '', 'tipo' => '', 'abreviatura' => ''];

                return [
                    'id' => (int) $apl->id,
                    'fecha' => optional($apl->fecha)->format('Y-m-d'),
                    'monto' => round(abs((float) $apl->total), 4),
                    'moneda' => $apl->monedas->abreviatura ?? '',
                    'moneda_contraparte' => $creditoCc?->monedas?->abreviatura ?? '',
                    'cotizacion_liquidacion' => $apl->cotizacion_liquidacion !== null
                        ? (float) $apl->cotizacion_liquidacion
                        : null,
                    'deuda' => $deuda['etiqueta'],
                    'deuda_tipo' => $deuda['tipo'],
                    'deuda_abreviatura' => $deuda['abreviatura'],
                    'credito' => $credito['etiqueta'],
                    'credito_tipo' => $credito['tipo'],
                    'credito_abreviatura' => $credito['abreviatura'],
                    'deuda_id' => (int) $apl->proveedor_cuentacorriente_id,
                    'credito_id' => (int) ($apl->proveedor_cuentacorriente_aplicado_id ?? 0),
                    'diferencia_cambio' => round((float) ($apl->diferencia_cambio ?? 0), 4),
                    'asiento_id' => (int) ($apl->asiento_id ?? 0) ?: null,
                    'comprobante_id' => $comp ? (int) $comp->comprobante_id : null,
                    'comprobante' => $comp ? AplicacionCcProveedorComprobante::formatearNumero($comp->numero) : '',
                    'comprobante_lineas' => $comp ? (int) $comp->cantidad_lineas : 0,
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
