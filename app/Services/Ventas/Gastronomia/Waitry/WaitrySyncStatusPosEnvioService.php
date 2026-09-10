<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Jobs\EnviarWaitrySyncStatusPosJob;
use App\Models\Ventas\WaitrySyncStatusPos;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Persistencia y reintentos de syncStatusPOS / KDS tras facturar una orden Waitry.
 */
final class WaitrySyncStatusPosEnvioService
{
    public function buscarPorVenta(int $ventaId): ?WaitrySyncStatusPos
    {
        return WaitrySyncStatusPos::query()->where('venta_id', $ventaId)->first();
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function crearOReusarRegistro(
        int $ventaId,
        ?int $cuentaGastronomiaId,
        int $waitryOrderId,
        int $empresaId,
        int $placeId,
        array $mediosPago,
    ): WaitrySyncStatusPos {
        $existente = $this->buscarPorVenta($ventaId);
        if ($existente !== null) {
            if ($existente->estado !== WaitrySyncStatusPos::ESTADO_ENVIADO
                && $mediosPago !== []
                && $existente->mediosPagoPersistidos() === []
            ) {
                $payload = is_array($existente->payload_json) ? $existente->payload_json : [];
                $payload['medios_pago'] = $mediosPago;
                $existente->payload_json = $payload;
                $existente->save();
            }

            return $existente;
        }

        $maxIntentos = (int) config('waitry.max_intentos', 8);

        try {
            return WaitrySyncStatusPos::query()->create([
                'venta_id' => $ventaId,
                'cuenta_gastronomia_id' => $cuentaGastronomiaId,
                'waitry_order_id' => $waitryOrderId,
                'empresa_id' => $empresaId,
                'place_id' => $placeId,
                'estado' => WaitrySyncStatusPos::ESTADO_PENDIENTE,
                'sync_pos_ok' => false,
                'kds_ok' => false,
                'intentos' => 0,
                'max_intentos' => $maxIntentos,
                'payload_json' => ['medios_pago' => $mediosPago],
                'proximo_reintento_at' => now(),
            ]);
        } catch (QueryException $e) {
            $duplicado = $this->buscarPorVenta($ventaId);
            if ($duplicado !== null) {
                return $duplicado;
            }

            throw $e;
        }
    }

    public function marcarEnviando(WaitrySyncStatusPos $registro): bool
    {
        $actualizados = WaitrySyncStatusPos::query()
            ->where('id', $registro->id)
            ->whereIn('estado', [
                WaitrySyncStatusPos::ESTADO_PENDIENTE,
                WaitrySyncStatusPos::ESTADO_ERROR,
            ])
            ->update([
                'estado' => WaitrySyncStatusPos::ESTADO_ENVIANDO,
                'updated_at' => now(),
            ]);

        if ($actualizados === 0) {
            return false;
        }

        $registro->refresh();

        return true;
    }

    public function marcarPasoOk(WaitrySyncStatusPos $registro, string $paso, ?array $respuesta = null): void
    {
        if ($paso === 'sync_status_pos') {
            $registro->sync_pos_ok = true;
        }
        if ($paso === 'update_order_status') {
            $registro->kds_ok = true;
        }
        if ($respuesta !== null) {
            $registro->respuesta_json = $respuesta;
        }
        $registro->ultimo_error = null;
        $registro->save();
    }

    public function marcarExito(WaitrySyncStatusPos $registro, ?array $respuesta = null): void
    {
        $registro->estado = WaitrySyncStatusPos::ESTADO_ENVIADO;
        $registro->sync_pos_ok = true;
        $registro->kds_ok = true;
        $registro->ultimo_error = null;
        $registro->ultimo_http_code = 200;
        $registro->respuesta_json = $respuesta;
        $registro->proximo_reintento_at = null;
        $registro->enviado_at = now();
        $registro->save();
    }

    public function marcarOmitido(WaitrySyncStatusPos $registro, string $motivo): void
    {
        $registro->estado = WaitrySyncStatusPos::ESTADO_OMITIDO;
        $registro->ultimo_error = mb_substr($motivo, 0, 2000);
        $registro->proximo_reintento_at = null;
        $registro->save();
    }

    /**
     * @param  array<string, mixed>|null  $respuesta
     */
    public function registrarFallo(
        WaitrySyncStatusPos $registro,
        string $error,
        ?int $httpCode = null,
        ?array $respuesta = null,
    ): void {
        $registro->intentos = (int) $registro->intentos + 1;
        $registro->ultimo_error = mb_substr($error, 0, 2000);
        $registro->ultimo_http_code = $httpCode;
        $registro->respuesta_json = $respuesta;

        if (! $registro->puedeReintentar()) {
            $registro->estado = WaitrySyncStatusPos::ESTADO_AGOTADO;
            $registro->proximo_reintento_at = null;
            $registro->save();

            Log::error('waitry.sync_status_pos.agotado', [
                'registro_id' => $registro->id,
                'venta_id' => $registro->venta_id,
                'waitry_order_id' => $registro->waitry_order_id,
                'intentos' => $registro->intentos,
                'error' => $error,
            ]);

            return;
        }

        $registro->estado = WaitrySyncStatusPos::ESTADO_ERROR;
        $registro->proximo_reintento_at = $this->calcularProximoReintento((int) $registro->intentos);
        $registro->save();

        Log::warning('waitry.sync_status_pos.fallo', [
            'registro_id' => $registro->id,
            'venta_id' => $registro->venta_id,
            'waitry_order_id' => $registro->waitry_order_id,
            'intentos' => $registro->intentos,
            'proximo_reintento_at' => $registro->proximo_reintento_at?->toIso8601String(),
            'error' => $error,
        ]);
    }

    public function encolarReintento(WaitrySyncStatusPos $registro, ?int $delaySegundos = null): void
    {
        if (! $registro->puedeReintentar()) {
            return;
        }

        if (! config('waitry.encolar_reintentos', true)) {
            return;
        }

        if (! $this->colaRealDisponible()) {
            return;
        }

        $delay = $delaySegundos ?? $this->segundosBackoff((int) $registro->intentos);
        $delay = max(0, $delay);

        EnviarWaitrySyncStatusPosJob::dispatch($registro->id)
            ->onQueue((string) config('waitry.cola', 'default'))
            ->delay(now()->addSeconds($delay));
    }

    public function encolarInmediato(WaitrySyncStatusPos $registro): void
    {
        if ($registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO
            || $registro->estado === WaitrySyncStatusPos::ESTADO_OMITIDO
        ) {
            return;
        }

        if (! config('waitry.encolar_reintentos', true)) {
            return;
        }

        if (! $this->colaRealDisponible()) {
            return;
        }

        EnviarWaitrySyncStatusPosJob::dispatch($registro->id)
            ->onQueue((string) config('waitry.cola', 'default'));
    }

    public function colaRealDisponible(): bool
    {
        if (! filter_var(config('waitry.sync_status_pos_en_cola', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $driver = (string) config('queue.default', 'sync');

        return $driver !== 'sync' && $driver !== '';
    }

    /**
     * @return list<WaitrySyncStatusPos>
     */
    public function pendientesDeReintento(int $limite): array
    {
        $limite = max(1, min(200, $limite));

        $this->liberarEnviandoEstancados();

        return WaitrySyncStatusPos::query()
            ->whereIn('estado', [
                WaitrySyncStatusPos::ESTADO_PENDIENTE,
                WaitrySyncStatusPos::ESTADO_ERROR,
            ])
            ->where(function ($q): void {
                $q->whereNull('proximo_reintento_at')
                    ->orWhere('proximo_reintento_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limite)
            ->get()
            ->all();
    }

    public function liberarEnviandoEstancados(): int
    {
        $minutos = max(2, (int) config('waitry.sync_status_pos_stale_minutos', 5));

        return WaitrySyncStatusPos::query()
            ->where('estado', WaitrySyncStatusPos::ESTADO_ENVIANDO)
            ->where('updated_at', '<', now()->subMinutes($minutos))
            ->update([
                'estado' => WaitrySyncStatusPos::ESTADO_ERROR,
                'proximo_reintento_at' => now(),
                'ultimo_error' => 'Waitry: envío estancado (worker interrumpido); se reencola.',
                'updated_at' => now(),
            ]);
    }

    public function calcularProximoReintento(int $numeroIntento): Carbon
    {
        return now()->addSeconds($this->segundosBackoff($numeroIntento));
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
