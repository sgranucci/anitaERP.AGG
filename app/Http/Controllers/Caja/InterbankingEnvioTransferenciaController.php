<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingEnvio;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\InterbankingArchivoPagoService;
use App\Services\Caja\InterbankingEnvioTransferenciaService;
use App\Support\Caja\InterbankingArchivoPagoFiltros;
use App\Support\Compras\CbuSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Envío on-line de transferencias Interbanking (OP/I-E + solicitud retiro juego).
 */
class InterbankingEnvioTransferenciaController extends Controller
{
    private const PERMISO = 'enviar-transferencias-interbanking';

    private const SESSION_PREVIEW = 'interbanking_envio_preview';

    public function __construct(
        private readonly InterbankingEnvioTransferenciaService $service,
        private readonly InterbankingArchivoPagoService $archivoPagoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        can(self::PERMISO);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $fuente = in_array($request->input('fuente'), ['proveedores', 'solicitud_retiro'], true)
            ? (string) $request->input('fuente')
            : 'proveedores';

        $filtros = InterbankingArchivoPagoFiltros::resolverDesdeRequest($request);
        $cuentaOrigen = $this->hidratarCuenta($request, $filtros);
        $filtros['cuentacaja_id'] = $cuentaOrigen ? (int) $cuentaOrigen->id : (int) ($filtros['cuentacaja_id'] ?? 0);
        $filtros['cbu_origen'] = $cuentaOrigen
            ? CbuSupport::normalizar((string) $cuentaOrigen->cbu)
            : '';

        $preview = null;
        $consultado = false;
        $filtroError = null;

        if ($fuente === 'proveedores' && $request->boolean('consultar')) {
            $consultado = true;
            if ((int) ($filtros['cuentacaja_id'] ?? 0) <= 0 || $cuentaOrigen === null) {
                $filtroError = 'Indique la cuenta de caja origen (filtro): solo se listan OP/I-E pagadas por esa cuenta y con ella se debita en Interbanking.';
                $preview = [
                    'ok' => false,
                    'mensaje' => $filtroError,
                    'filas' => [],
                    'omitidas' => [],
                    'total_importe' => 0.0,
                    'cantidad' => 0,
                    'seleccionables' => 0,
                ];
            } else {
                ini_set('memory_limit', '512M');
                $preview = $this->service->previewProveedores($filtros);
                if (! empty($preview['ok'])) {
                    $this->guardarPreviewSesion('proveedores', $filtros, $preview['filas']);
                }
            }
        }

        $ultimoEnvioId = (int) (session('ib_envio_id') ?: $request->input('envio_id', 0));
        $ultimoEnvio = $this->cargarUltimoEnvio($ultimoEnvioId);

        return view('caja.interbanking.envio_transferencias', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'fuente' => $fuente,
            'consultado' => $consultado,
            'preview' => $preview,
            'cuenta_origen' => $cuentaOrigen,
            'filtro_error' => $filtroError,
            'ultimo_envio' => $ultimoEnvio,
        ]);
    }

    public function previewRetiro(Request $request): View|RedirectResponse
    {
        can(self::PERMISO);

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = InterbankingArchivoPagoFiltros::resolverDesdeRequest($request);
        $cuentaOrigen = $this->hidratarCuenta($request, $filtros);
        $filtros['cuentacaja_id'] = $cuentaOrigen ? (int) $cuentaOrigen->id : (int) ($filtros['cuentacaja_id'] ?? 0);

        if ($cuentaOrigen === null || (int) ($filtros['cuentacaja_id'] ?? 0) <= 0) {
            return redirect()
                ->route('interbanking_envio_transferencias', ['fuente' => 'solicitud_retiro'])
                ->with('mensaje_error', 'Indique la cuenta de caja origen (filtro / débito Interbanking) antes de cargar el CSV.');
        }

        $archivo = $request->file('archivo_csv');
        if ($archivo === null || ! $archivo->isValid()) {
            return redirect()
                ->route('interbanking_envio_transferencias', ['fuente' => 'solicitud_retiro'])
                ->with('mensaje_error', 'Suba un CSV de solicitud de retiro válido.');
        }

        $csv = (string) file_get_contents($archivo->getRealPath());
        ini_set('memory_limit', '512M');
        $preview = $this->service->previewSolicitudRetiro(
            (int) $filtros['empresa_id'],
            (int) $filtros['cuentacaja_id'],
            $csv
        );

        if (! empty($preview['ok'])) {
            $this->guardarPreviewSesion('solicitud_retiro', $filtros, $preview['filas']);
            if ($preview['cuenta'] instanceof Cuentacaja) {
                $cuentaOrigen = $preview['cuenta'];
            }
        }

        return view('caja.interbanking.envio_transferencias', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'fuente' => 'solicitud_retiro',
            'consultado' => true,
            'preview' => $preview,
            'cuenta_origen' => $cuentaOrigen,
            'filtro_error' => null,
            'ultimo_envio' => null,
        ]);
    }

    public function enviar(Request $request): RedirectResponse|JsonResponse
    {
        can(self::PERMISO);

        $keys = $request->input('selection_keys', []);
        if (! is_array($keys)) {
            $keys = [];
        }
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));

        $preview = session(self::SESSION_PREVIEW);
        if (! is_array($preview) || empty($preview['filas']) || ! is_array($preview['filas'])) {
            return $this->respuestaEnvio($request, false, 'No hay preview en sesión. Consulte de nuevo antes de enviar.');
        }

        $seleccionadas = [];
        foreach ($keys as $key) {
            if (isset($preview['filas'][$key]) && empty($preview['filas'][$key]['ya_enviado'])) {
                $seleccionadas[] = $preview['filas'][$key];
            }
        }

        if ($seleccionadas === []) {
            return $this->respuestaEnvio($request, false, 'Seleccione al menos una transferencia pendiente.');
        }

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $resultado = $this->service->enviarSeleccion(
            (int) ($preview['empresa_id'] ?? 0),
            (int) ($preview['cuentacaja_id'] ?? 0),
            (string) ($preview['fuente'] ?? InterbankingEnvio::FUENTE_PROVEEDORES),
            $seleccionadas
        );

        // Refrescar flags ya_enviado en sesión
        foreach ($resultado['resultados'] as $r) {
            $k = (string) ($r['selection_key'] ?? '');
            if ($k !== '' && ($r['estado'] ?? '') === 'ok' && isset($preview['filas'][$k])) {
                $preview['filas'][$k]['ya_enviado'] = true;
            }
        }
        session([self::SESSION_PREVIEW => $preview]);

        if ($request->expectsJson()) {
            return response()->json($resultado);
        }

        $redirect = redirect()
            ->route('interbanking_envio_transferencias', [
                'fuente' => $preview['fuente'] ?? 'proveedores',
                'empresa_id' => $preview['empresa_id'] ?? null,
                'cuentacaja_id' => $preview['cuentacaja_id'] ?? null,
                'fecha_desde' => $preview['filtros']['fecha_desde'] ?? null,
                'fecha_hasta' => $preview['filtros']['fecha_hasta'] ?? null,
                'tipo_op' => $preview['filtros']['tipo_op'] ?? null,
                'consultar' => ($preview['fuente'] ?? '') === 'proveedores' ? 1 : null,
                'envio_id' => $resultado['envio_id'] ?? null,
            ])
            ->with('ib_envio_id', $resultado['envio_id'] ?? null);

        if (! empty($resultado['ok'])) {
            return $redirect->with('mensaje', $resultado['mensaje']);
        }

        return $redirect->with('mensaje_error', $resultado['mensaje'] ?? 'Error al enviar.');
    }

    /**
     * @param  list<array<string,mixed>>  $filas
     * @param  array<string,mixed>  $filtros
     */
    private function guardarPreviewSesion(string $fuente, array $filtros, array $filas): void
    {
        $keyed = [];
        foreach ($filas as $fila) {
            $k = (string) ($fila['selection_key'] ?? '');
            if ($k !== '') {
                $keyed[$k] = $fila;
            }
        }

        session([
            self::SESSION_PREVIEW => [
                'fuente' => $fuente,
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
                'cuentacaja_id' => (int) ($filtros['cuentacaja_id'] ?? 0),
                'filtros' => $filtros,
                'filas' => $keyed,
            ],
        ]);
    }

    /**
     * @param  array<string,mixed>  $filtros
     */
    private function hidratarCuenta(Request $request, array &$filtros): ?Cuentacaja
    {
        $id = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return $this->archivoPagoService->buscarCuentaOrigen((int) ($filtros['empresa_id'] ?? 0), $id);
    }

    private function cargarUltimoEnvio(int $envioId): ?InterbankingEnvio
    {
        if ($envioId <= 0) {
            return null;
        }

        return InterbankingEnvio::query()
            ->with(['lineas' => static function ($q) {
                $q->orderBy('id');
            }, 'cuentacaja:id,codigo,nombre'])
            ->find($envioId);
    }

    private function respuestaEnvio(Request $request, bool $ok, string $mensaje): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'mensaje' => $mensaje]);
        }

        $redir = redirect()->route('interbanking_envio_transferencias');

        return $ok ? $redir->with('mensaje', $mensaje) : $redir->with('mensaje_error', $mensaje);
    }
}
