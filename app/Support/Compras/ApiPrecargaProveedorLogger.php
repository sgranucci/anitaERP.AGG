<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ApiPrecargaProveedorLogger
{
    private string $traceId;

    public function __construct(?string $traceId = null)
    {
        $this->traceId = $traceId ?? (string) Str::uuid();
    }

    public static function trace(?string $traceId = null): self
    {
        return new self($traceId);
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
        $this->persistirSiErrorHttp($event, $context);
    }

    public function error(string $event, array $context = [], ?\Throwable $exception = null): void
    {
        if ($exception !== null) {
            $context['exception'] = $exception->getMessage();
            $context['exception_class'] = $exception::class;
        }

        $this->write('error', $event, $context);
        $this->persistirSiErrorHttp($event, $context);
    }

    public function requestPayload(Request $request): array
    {
        return $request->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function persistirSiErrorHttp(string $event, array $context): void
    {
        PrecargaRecepcionErrorRegistrar::desdeApiLogger($event, $context, $this->traceId);
    }

    private function write(string $level, string $event, array $context): void
    {
        $payload = array_merge(
            ['trace_id' => $this->traceId],
            $context
        );
        $message = 'precarga_proveedor_api.'.$event;

        try {
            Log::channel(config('precarga_comprobante.log_channel'))
                ->{$level}($message, $payload);
        } catch (\Throwable $e) {
            try {
                Log::{$level}($message, array_merge($payload, [
                    'log_channel_fallback' => true,
                    'log_channel_error' => $e->getMessage(),
                ]));
            } catch (\Throwable) {
                // No interrumpir la API si el logging falla por permisos u otro motivo.
            }
        }
    }
}
