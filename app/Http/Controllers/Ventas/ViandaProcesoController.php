<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\ConfiguracionTerminalVianda;
use App\Models\Ventas\ViandaUsuario;
use App\Services\Ventas\Vianda\ViandaProcesoService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Throwable;

class ViandaProcesoController extends Controller
{
    private const SESSION_USUARIO = 'vianda_proceso_usuario_id';

    public function __construct(
        private ViandaProcesoService $procesoService,
    ) {
    }

    public function index(Request $request)
    {
        can('usar-proceso-vianda-gastronomia');

        // Cada ingreso a la pantalla del kiosco exige identificarse de nuevo: al abrir
        // el proceso (tras "Salir", recargar o volver del menú) no debe quedar logueado
        // el último empleado. El auto-logout tras marchar ya limpia la sesión aparte.
        $request->session()->forget(self::SESSION_USUARIO);

        $cfg = $this->procesoService->resolverTerminal($request);
        $identificadorPc = GastronomiaIdentificadorPc::resolver($request);
        $tieneCfg = $cfg !== null;
        $estadoJornada = $tieneCfg ? $this->procesoService->estadoJornada($cfg) : null;

        return view('ventas.vianda.proceso.index', [
            'tiene_cfg' => $tieneCfg,
            'identificador_pc_actual' => $identificadorPc,
            'terminal_nombre' => $cfg?->descripcion,
            'terminal_ubicacion' => $cfg?->ubicacion?->nombre,
            'empresa_nombre' => $cfg?->empresa?->nombre,
            'empresa_id' => $cfg?->empresa_id,
            'estado_jornada' => $estadoJornada,
            'preview_pantalla' => (bool) config('gastronomia.vianda_voucher_preview_pantalla', false),
        ]);
    }

    public function apiEstado(Request $request): JsonResponse
    {
        if (! can('usar-proceso-vianda-gastronomia', false)) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $cfg = $this->procesoService->resolverTerminal($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'sin_configuracion' => true]);
        }

        $usuario = $this->usuarioSesion();
        $payload = [
            'ok' => true,
            'jornada' => $this->procesoService->estadoJornada($cfg),
            'usuario' => null,
            'menu' => null,
        ];

        if ($usuario !== null) {
            $payload['usuario'] = $this->usuarioPayload($usuario);
            $payload['menu'] = $this->procesoService->menuDelDia($usuario);
            $payload['pedido_diario'] = $this->procesoService->estadoPedidoDiario($usuario, $cfg);
        }

        return response()->json($payload);
    }

    public function apiLogin(Request $request): JsonResponse
    {
        if (! can('usar-proceso-vianda-gastronomia', false)) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $cfg = $this->procesoService->resolverTerminal($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Terminal de viandas sin configuración.'], 422);
        }

        try {
            $usuario = $this->procesoService->autenticar(
                (string) $request->input('codigo', ''),
                (string) $request->input('password', ''),
                (int) $cfg->empresa_id,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $request->session()->put(self::SESSION_USUARIO, (int) $usuario->id);

        return response()->json([
            'ok' => true,
            'usuario' => $this->usuarioPayload($usuario),
            'menu' => $this->procesoService->menuDelDia($usuario),
            'jornada' => $this->procesoService->estadoJornada($cfg),
            'pedido_diario' => $this->procesoService->estadoPedidoDiario($usuario, $cfg),
        ]);
    }

    public function apiMarchar(Request $request): JsonResponse
    {
        if (! can('usar-proceso-vianda-gastronomia', false)) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $cfg = $this->procesoService->resolverTerminal($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Terminal de viandas sin configuración.'], 422);
        }

        $usuario = $this->usuarioSesion();
        if ($usuario === null) {
            return response()->json(['ok' => false, 'requiere_login' => true, 'mensaje' => 'Sesión de empleado expirada. Vuelva a identificarse.'], 401);
        }

        $lineas = $request->input('lineas', []);
        if (! is_array($lineas)) {
            $lineas = [];
        }

        try {
            $resultado = $this->procesoService->marchar(
                $cfg,
                $usuario,
                $lineas,
                (string) $request->input('observacion', ''),
                Auth::id(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => 'No se pudo marchar la comanda: '.$e->getMessage()], 500);
        }

        // Auto-logout: la terminal queda lista para el siguiente empleado.
        $request->session()->forget(self::SESSION_USUARIO);

        $consumo = $resultado['consumo'];
        $voucher = $resultado['voucher'];

        return response()->json([
            'ok' => true,
            'consumo' => [
                'id' => (int) $consumo->id,
                'codigo_retiro' => (string) $consumo->codigo_retiro,
                'fecha' => $consumo->fecha?->format('d/m/Y'),
                'hora' => (string) $consumo->hora,
                'empleado' => trim((string) $consumo->login_usuario.' - '.$consumo->nombre_usuario, ' -'),
                'centrocosto' => (string) ($consumo->centrocosto->nombre ?? ''),
                'cantidad_items' => (int) $consumo->cantidad_items,
            ],
            'voucher' => [
                'impreso' => (bool) ($voucher['ok'] ?? false),
                'mensaje' => $voucher['mensaje'] ?? null,
                'texto_preview' => $voucher['texto_preview'] ?? '',
            ],
        ]);
    }

    public function apiReimprimir(Request $request): JsonResponse
    {
        if (! can('usar-proceso-vianda-gastronomia', false)) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin permiso.'], 403);
        }

        $cfg = $this->procesoService->resolverTerminal($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Terminal de viandas sin configuración.'], 422);
        }

        $consumoId = (int) $request->input('consumo_id', 0);
        if ($consumoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Voucher inválido para reimprimir.'], 422);
        }

        try {
            $voucher = $this->procesoService->reimprimirVoucher($cfg, $consumoId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => 'No se pudo reimprimir el voucher: '.$e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'voucher' => [
                'impreso' => (bool) ($voucher['ok'] ?? false),
                'mensaje' => $voucher['mensaje'] ?? null,
                'texto_preview' => $voucher['texto_preview'] ?? '',
            ],
        ]);
    }

    public function apiLogout(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_USUARIO);

        return response()->json(['ok' => true]);
    }

    private function usuarioSesion(): ?ViandaUsuario
    {
        $id = (int) session(self::SESSION_USUARIO, 0);
        if ($id <= 0) {
            return null;
        }

        return ViandaUsuario::query()->where('estado', 'A')->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function usuarioPayload(ViandaUsuario $usuario): array
    {
        $usuario->loadMissing(['centrocosto', 'tipoMenu']);

        return [
            'id' => (int) $usuario->id,
            'codigo' => (string) $usuario->codigo_usuario,
            'nombre' => (string) $usuario->nombre,
            'centrocosto' => $usuario->centrocosto?->nombre,
            'tipo_menu' => $usuario->tipoMenu?->nombre,
        ];
    }
}
