<?php

declare(strict_types=1);

namespace App\Jobs\Compras;

use App\Mail\Compras\PagoproveedorEnvioMasivoResultadoMail;
use App\Models\Seguridad\Usuario;
use App\Services\Compras\PagoproveedorEnvioMasivoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envío masivo de OP al proveedor en cola: el POST solo encola y el operador
 * recibe un mail de resumen al terminar (evita Gateway Timeout del browser).
 */
class PagoproveedorEnvioMasivoJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public int $uniqueFor;

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $ids
     */
    public function __construct(
        public readonly int $usuarioId,
        public readonly array $filtros,
        public readonly array $ids,
        public readonly ?string $mensaje,
    ) {
        $cfg = config('compras.pagoproveedor_envio_masivo', []);
        $porOp = max(20, (int) ($cfg['segundos_por_op'] ?? 45));
        $min = max(300, (int) ($cfg['timeout_minimo'] ?? 600));
        $max = max($min, (int) ($cfg['timeout_maximo'] ?? 5400));
        $this->timeout = min($max, max($min, count($ids) * $porOp));
        $this->uniqueFor = max(600, (int) ($cfg['unique_for'] ?? 7200));
        $this->onQueue((string) ($cfg['cola'] ?? 'reports-mail'));
    }

    public function uniqueId(): string
    {
        return 'pagoproveedor-envio-masivo-'.$this->usuarioId;
    }

    public static function claveCandado(int $usuarioId): string
    {
        return 'pagoproveedor_envio_masivo_running_'.$usuarioId;
    }

    public function handle(PagoproveedorEnvioMasivoService $service): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit($this->timeout);

        $usuario = Usuario::query()->find($this->usuarioId);
        $email = trim((string) ($usuario->email ?? ''));
        $nombre = (string) ($usuario->nombre ?? $usuario->usuario ?? '');

        Log::info('pagoproveedor.envio_masivo.inicio', [
            'usuario_id' => $this->usuarioId,
            'cantidad' => count($this->ids),
            'empresa_id' => (int) ($this->filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($this->filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($this->filtros['fecha_hasta'] ?? ''),
        ]);

        Auth::loginUsingId($this->usuarioId);

        try {
            $resultado = $service->ejecutarConFiltros($this->filtros, $this->ids, $this->mensaje);
            $this->enviarResumen($email, $nombre, true, $resultado, null);

            Log::info('pagoproveedor.envio_masivo.fin', [
                'usuario_id' => $this->usuarioId,
                'enviadas' => (int) ($resultado['enviadas'] ?? 0),
                'errores' => count($resultado['errores'] ?? []),
            ]);
        } catch (Throwable $e) {
            Log::error('pagoproveedor.envio_masivo.error', [
                'usuario_id' => $this->usuarioId,
                'error' => $e->getMessage(),
            ]);
            $this->enviarResumen($email, $nombre, false, [
                'enviadas' => 0,
                'errores' => [['etiqueta' => '', 'mensaje' => $e->getMessage()]],
                'detalle' => [],
            ], $e->getMessage());

            throw $e;
        } finally {
            Auth::logout();
            Cache::forget(self::claveCandado($this->usuarioId));
        }
    }

    /**
     * @param  array{enviadas?: int, errores?: list<array{etiqueta: string, mensaje: string}>, detalle?: list<array{etiqueta: string, ok: bool, mensaje: string}>}  $resultado
     */
    private function enviarResumen(
        string $email,
        string $nombre,
        bool $okProceso,
        array $resultado,
        ?string $errorFatal,
    ): void {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('pagoproveedor.envio_masivo.sin_email', [
                'usuario_id' => $this->usuarioId,
            ]);

            return;
        }

        $enviadas = (int) ($resultado['enviadas'] ?? 0);
        $errores = $resultado['errores'] ?? [];
        $detalle = $resultado['detalle'] ?? [];
        $ok = $okProceso && $errores === [];

        try {
            Mail::to($email)->send(new PagoproveedorEnvioMasivoResultadoMail([
                'ok' => $ok,
                'ok_proceso' => $okProceso,
                'enviadas' => $enviadas,
                'fallidas' => count($errores),
                'total' => count($this->ids),
                'errores' => $errores,
                'detalle' => $detalle,
                'error_fatal' => $errorFatal,
                'usuario_nombre' => $nombre,
                'empresa_id' => (int) ($this->filtros['empresa_id'] ?? 0),
                'fecha_desde' => (string) ($this->filtros['fecha_desde'] ?? ''),
                'fecha_hasta' => (string) ($this->filtros['fecha_hasta'] ?? ''),
                'medio' => (string) ($this->filtros['medio'] ?? 'ambas'),
            ]));
        } catch (Throwable $e) {
            Log::error('pagoproveedor.envio_masivo.mail_resumen_fallo', [
                'usuario_id' => $this->usuarioId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Cache::forget(self::claveCandado($this->usuarioId));
        Log::error('pagoproveedor.envio_masivo.failed', [
            'usuario_id' => $this->usuarioId,
            'error' => $e?->getMessage(),
        ]);
    }
}
