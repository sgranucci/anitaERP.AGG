<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Jobs\EnviarWaitryComandaJob;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\WaitryComandaEnvio;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Persistencia y programación de reintentos Waitry (monitoreo nocturno / cola).
 */
final class WaitryComandaEnvioService
{
    public function buscarPorVenta(int $ventaId): ?WaitryComandaEnvio
    {
        return WaitryComandaEnvio::query()->where('venta_id', $ventaId)->first();
    }

    public function crearRegistro(
        int $ventaId,
        ?int $cuentaGastronomiaId,
        int $empresaId,
        int $placeId,
        string $externalId,
        bool $pagada,
        ?array $payload = null,
    ): WaitryComandaEnvio {
        $maxIntentos = (int) config('waitry.max_intentos', 8);

        return WaitryComandaEnvio::query()->create([
            'venta_id' => $ventaId,
            'cuenta_gastronomia_id' => $cuentaGastronomiaId,
            'empresa_id' => $empresaId,
            'place_id' => $placeId,
            'external_id' => $externalId,
            'estado' => WaitryComandaEnvio::ESTADO_PENDIENTE,
            'pagada' => $pagada,
            'intentos' => 0,
            'max_intentos' => $maxIntentos,
            'payload_json' => $payload,
            'proximo_reintento_at' => now(),
        ]);
    }

    public function marcarEnviando(WaitryComandaEnvio $envio): void
    {
        $envio->estado = WaitryComandaEnvio::ESTADO_ENVIANDO;
        $envio->save();
    }

    /**
     * @param  array<string, mixed>|null  $respuesta
     */
    public function marcarExito(WaitryComandaEnvio $envio, int|string|null $orderId, ?array $respuesta = null): void
    {
        $envio->estado = WaitryComandaEnvio::ESTADO_ENVIADO;
        $envio->waitry_order_id = is_numeric($orderId) ? (int) $orderId : null;
        $envio->ultimo_error = null;
        $envio->ultimo_http_code = 200;
        $envio->respuesta_json = $respuesta;
        $envio->proximo_reintento_at = null;
        $envio->enviado_at = now();
        $envio->save();

        $this->persistirOrderIdEnCuenta($envio->cuenta_gastronomia_id, $orderId);
        VentaGastronomiaEmisionWaitrySupport::persistirOrderIdEnEmision((int) $envio->venta_id, $orderId);
    }

    public function persistirOrderIdEnCuenta(?int $cuentaGastronomiaId, int|string|null $orderId): void
    {
        if ($cuentaGastronomiaId === null || ! is_numeric($orderId)) {
            return;
        }

        CuentaGastronomia::query()
            ->where('id', $cuentaGastronomiaId)
            ->update(['waitry_order_id' => (int) $orderId]);
    }

    public function marcarOmitido(WaitryComandaEnvio $envio, string $motivo): void
    {
        $envio->estado = WaitryComandaEnvio::ESTADO_OMITIDO;
        $envio->ultimo_error = mb_substr($motivo, 0, 2000);
        $envio->proximo_reintento_at = null;
        $envio->save();
    }

    /**
     * @param  array<string, mixed>|null  $respuesta
     * @param  array<string, mixed>|null  $payload
     */
    public function registrarFallo(
        WaitryComandaEnvio $envio,
        string $error,
        ?int $httpCode = null,
        ?array $respuesta = null,
        ?array $payload = null,
    ): void {
        $envio->intentos = (int) $envio->intentos + 1;
        $envio->ultimo_error = mb_substr($error, 0, 2000);
        $envio->ultimo_http_code = $httpCode;
        $envio->respuesta_json = $respuesta;
        if ($payload !== null) {
            $envio->payload_json = $payload;
        }

        if (! $envio->puedeReintentar()) {
            $envio->estado = WaitryComandaEnvio::ESTADO_AGOTADO;
            $envio->proximo_reintento_at = null;
            $envio->save();

            Log::error('waitry.comanda_envio.agotado', [
                'envio_id' => $envio->id,
                'venta_id' => $envio->venta_id,
                'external_id' => $envio->external_id,
                'intentos' => $envio->intentos,
                'error' => $error,
            ]);

            return;
        }

        $envio->estado = WaitryComandaEnvio::ESTADO_ERROR;
        $envio->proximo_reintento_at = $this->calcularProximoReintento((int) $envio->intentos);
        $envio->save();

        Log::warning('waitry.comanda_envio.fallo', [
            'envio_id' => $envio->id,
            'venta_id' => $envio->venta_id,
            'intentos' => $envio->intentos,
            'proximo_reintento_at' => $envio->proximo_reintento_at?->toIso8601String(),
            'error' => $error,
        ]);
    }

    public function encolarReintento(WaitryComandaEnvio $envio, ?int $delaySegundos = null): void
    {
        if (! $envio->puedeReintentar()) {
            return;
        }

        if (! config('waitry.encolar_reintentos', true)) {
            return;
        }

        $delay = $delaySegundos ?? $this->segundosBackoff((int) $envio->intentos);
        $delay = max(0, $delay);

        EnviarWaitryComandaJob::dispatch($envio->id)
            ->onQueue((string) config('waitry.cola', 'default'))
            ->delay(now()->addSeconds($delay));
    }

    public function calcularProximoReintento(int $numeroIntento): Carbon
    {
        $segundos = $this->segundosBackoff($numeroIntento);

        return now()->addSeconds($segundos);
    }

    public function segundosBackoff(int $numeroIntento): int
    {
        $lista = config('waitry.reintento_backoff_segundos', [30, 60, 120, 300, 600, 900, 1800, 3600]);
        if (! is_array($lista) || $lista === []) {
            return 300;
        }

        $idx = max(0, min(count($lista) - 1, $numeroIntento - 1));

        return max(15, (int) $lista[$idx]);
    }
}
