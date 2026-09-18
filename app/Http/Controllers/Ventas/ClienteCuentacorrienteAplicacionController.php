<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAplicacionCuentacorrienteCliente;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Ventas\ClienteCuentacorrienteAplicacionService;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionDcSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionFilaSupport;
use App\Support\Ventas\ClienteCuentacorrienteAplicacionMatcherSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ClienteCuentacorrienteAplicacionController extends Controller
{
    public function __construct(
        private Cliente_CuentacorrienteRepositoryInterface $cuentacorrienteRepository,
        private ClienteRepositoryInterface $clienteRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private ClienteCuentacorrienteAplicacionService $aplicacionService,
    ) {}

    public function index(Request $request)
    {
        can('aplicar-cuentacorriente-cliente');

        $clienteId = (int) $request->query('cliente_id', 0);
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $cliente = null;
        if ($clienteId > 0) {
            try {
                $cliente = $this->clienteRepository->find($clienteId);
            } catch (ModelNotFoundException) {
                $clienteId = 0;
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

        if ($cliente) {
            $payload = $this->armarWorkbench($clienteId, 0);
            $creditos = $payload['creditos'];
            $deudas = $payload['deudas'];
            $recientes = $payload['recientes'];
            $kpis = $payload['kpis'];
        }

        $soloConsulta = $request->query('origen') === 'modal_consulta';
        $volverClienteId = (int) $request->query('volver_cliente_id', $clienteId);

        return view('ventas.aplicacion_cuentacorriente.index', [
            'empresa_query' => $empresaQuery,
            'cliente' => $cliente,
            'cliente_id' => $clienteId,
            'empresa_id' => $empresaId,
            'fecha' => $request->query('fecha', now()->format('Y-m-d')),
            'creditos' => $creditos,
            'deudas' => $deudas,
            'recientes' => $recientes,
            'kpis' => $kpis,
            'soloConsulta' => $soloConsulta,
            'volverClienteId' => $volverClienteId,
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
        if (! can('aplicar-cuentacorriente-cliente', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $clienteId = (int) $request->query('cliente_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($clienteId <= 0) {
            return response()->json(['creditos' => [], 'deudas' => [], 'recientes' => [], 'kpis' => [
                'creditos' => 0, 'deudas' => 0, 'nc' => 0, 'pagos' => 0, 'vencida' => 0,
            ]]);
        }

        return response()->json($this->armarWorkbench($clienteId, 0));
    }

    public function apiCotizacionMonedaFecha(Request $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-cliente', false)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $request->validate([
            'fecha' => 'required|date',
            'moneda_id' => 'required|integer|exists:moneda,id',
        ]);

        $fecha = substr((string) $request->query('fecha'), 0, 10);
        $monedaId = (int) $request->query('moneda_id');

        return response()->json([
            'cotizacion' => CotizacionVigenteSupport::ventaValor($fecha, $monedaId),
            'moneda_id' => $monedaId,
            'fecha' => $fecha,
        ]);
    }

    public function apiSugerir(Request $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-cliente', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $clienteId = (int) $request->query('cliente_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        $modo = (string) $request->query('modo', 'fifo');
        if ($clienteId <= 0) {
            return response()->json(['lineas' => []]);
        }

        $payload = $this->armarWorkbench($clienteId, 0);
        $creditos = $payload['creditos'];
        $deudas = $payload['deudas'];
        $lineas = $modo === 'parear'
            ? ClienteCuentacorrienteAplicacionMatcherSupport::sugerirParearImportes($creditos, $deudas)
            : ClienteCuentacorrienteAplicacionMatcherSupport::sugerirFifo($creditos, $deudas);

        return response()->json(['lineas' => $lineas, 'modo' => $modo === 'parear' ? 'parear' : 'fifo']);
    }

    public function aplicar(ValidacionAplicacionCuentacorrienteCliente $request): JsonResponse
    {
        if (! can('aplicar-cuentacorriente-cliente', false)) {
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
                (int) $request->input('cliente_id'),
                (string) $request->input('fecha'),
                $lineas
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No se pudo aplicar: '.$e->getMessage()], 500);
        }

        $payload = $this->armarWorkbench((int) $request->input('cliente_id'), 0);

        $mensaje = $resultado['aplicadas'].' aplicación(es) por '.number_format($resultado['monto'], 2, ',', '.');
        $dc = (float) ($resultado['dc'] ?? 0);
        if (ProveedorCuentacorrienteAplicacionDcSupport::requiereAsiento($dc)) {
            $mensaje .= ' · DC '.number_format(abs($dc), 2, ',', '.').' ('.ProveedorCuentacorrienteAplicacionDcSupport::etiqueta($dc).')';
        }
        $asientos = (int) ($resultado['asientos_dc'] ?? 0);
        if ($asientos > 0) {
            $mensaje .= ' · '.$asientos.' asiento(s) contable(s)';
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
        if (! can('desaplicar-cuentacorriente-cliente', false) && ! can('aplicar-cuentacorriente-cliente', false)) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }
        $clienteId = (int) $request->input('cliente_id', 0);
        if ($clienteId <= 0) {
            return response()->json(['error' => 'Indique el cliente.'], 422);
        }

        try {
            $this->aplicacionService->desaplicar($id, $clienteId);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'No se pudo desaplicar: '.$e->getMessage()], 500);
        }

        $payload = $this->armarWorkbench($clienteId, 0);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Aplicación revertida.',
            'workbench' => $payload,
        ]);
    }

    /**
     * @return array{creditos: list<array<string, mixed>>, deudas: list<array<string, mixed>>, recientes: list<array<string, mixed>>, kpis: array<string, float>}
     */
    private function armarWorkbench(int $clienteId, int $empresaId): array
    {
        $creditos = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion($clienteId, 'credito', $empresaId > 0 ? $empresaId : null)
            ->map(fn ($fila) => ClienteCuentacorrienteAplicacionFilaSupport::desdeModelo($fila))
            ->values()
            ->all();
        $deudas = $this->cuentacorrienteRepository
            ->listarPendientesAplicacion($clienteId, 'deuda', $empresaId > 0 ? $empresaId : null)
            ->map(fn ($fila) => ClienteCuentacorrienteAplicacionFilaSupport::desdeModelo($fila))
            ->values()
            ->all();

        $recientes = $this->cuentacorrienteRepository
            ->listarAplicacionesManualesRecientes($clienteId, $empresaId > 0 ? $empresaId : null)
            ->map(function ($apl) {
                $deudaCc = $apl->cliente_cuentacorrientes;
                $creditoCc = $apl->cliente_cuentacorriente_aplicados;
                $deuda = $deudaCc
                    ? ClienteCuentacorrienteAplicacionFilaSupport::resumenEtiqueta($deudaCc, 'deuda')
                    : ['etiqueta' => (string) $apl->comprobanteaplicado, 'tipo' => '', 'abreviatura' => ''];
                $credito = $creditoCc
                    ? ClienteCuentacorrienteAplicacionFilaSupport::resumenEtiqueta($creditoCc, 'credito')
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
                    'deuda_id' => (int) $apl->cliente_cuentacorriente_id,
                    'credito_id' => (int) ($apl->cliente_cuentacorriente_aplicado_id ?? 0),
                    'diferencia_cambio' => round((float) ($apl->diferencia_cambio ?? 0), 4),
                    'asiento_id' => (int) ($apl->asiento_id ?? 0) ?: null,
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
            if ($c['tipo'] === ClienteCuentacorrienteAplicacionFilaSupport::TIPO_NC) {
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
