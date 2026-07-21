<?php

namespace App\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Recepcion_Error;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Persiste errores de recepción de precarga (API Facturas_scan / PDF+IA) para verlos en el ERP.
 * Nunca lanza: un fallo de logging no debe romper la API ni el modal.
 */
final class PrecargaRecepcionErrorRegistrar
{
    public const ORIGEN_API = 'API';

    public const ORIGEN_PDF_IA = 'PDF_IA';

    /**
     * @param  array{
     *   origen?: string,
     *   fase?: string|null,
     *   evento?: string|null,
     *   http_status?: int|null,
     *   mensaje: string,
     *   trace_id?: string|null,
     *   numero_oc?: string|null,
     *   cuit_proveedor?: string|null,
     *   cuit_empresa?: string|null,
     *   tipo_comprobante?: string|null,
     *   archivo_nombre?: string|null,
     *   usuario_id?: int|null,
     *   precarga_id?: int|null,
     *   ip?: string|null,
     *   contexto?: array<string, mixed>|null
     * }  $datos
     */
    public static function registrar(array $datos): void
    {
        try {
            $mensaje = trim((string) ($datos['mensaje'] ?? ''));
            if ($mensaje === '') {
                $mensaje = 'Error sin mensaje';
            }

            $contexto = $datos['contexto'] ?? null;
            $contextoJson = null;
            if (is_array($contexto) && $contexto !== []) {
                $contextoJson = self::truncarJson($contexto);
            }

            Precarga_Comprobante_Recepcion_Error::query()->create([
                'origen' => (string) ($datos['origen'] ?? self::ORIGEN_API),
                'fase' => self::nullSiVacio($datos['fase'] ?? null),
                'evento' => self::nullSiVacio($datos['evento'] ?? null),
                'http_status' => isset($datos['http_status']) ? (int) $datos['http_status'] : null,
                'mensaje' => mb_substr($mensaje, 0, 5000),
                'trace_id' => self::nullSiVacio($datos['trace_id'] ?? null),
                'numero_oc' => self::nullSiVacio($datos['numero_oc'] ?? null),
                'cuit_proveedor' => self::nullSiVacio($datos['cuit_proveedor'] ?? null),
                'cuit_empresa' => self::nullSiVacio($datos['cuit_empresa'] ?? null),
                'tipo_comprobante' => self::nullSiVacio($datos['tipo_comprobante'] ?? null),
                'archivo_nombre' => self::nullSiVacio($datos['archivo_nombre'] ?? null),
                'usuario_id' => $datos['usuario_id'] ?? (Auth::id() ? (int) Auth::id() : null),
                'precarga_id' => isset($datos['precarga_id']) ? (int) $datos['precarga_id'] : null,
                'ip' => self::nullSiVacio($datos['ip'] ?? null),
                'contexto_json' => $contextoJson,
            ]);
        } catch (\Throwable $e) {
            try {
                Log::warning('precarga.recepcion_error.persistir_fallo', [
                    'mensaje' => $e->getMessage(),
                ]);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    /**
     * Desde el logger de la API externa (Facturas_scan / IA).
     *
     * @param  array<string, mixed>  $context
     */
    public static function desdeApiLogger(string $evento, array $context, string $traceId): void
    {
        $status = (int) ($context['status'] ?? 0);
        if ($status < 400) {
            return;
        }

        // Evitar doble fila en lista_concepto (warning intermedio + respuesta_error).
        if (str_starts_with($evento, 'lista_concepto.') && $evento !== 'lista_concepto.respuesta_error') {
            return;
        }

        $payload = $context['payload'] ?? ($context['data'] ?? null);
        $mensaje = (string) ($context['message'] ?? $context['exception'] ?? $evento);
        if ($mensaje === '' && isset($context['errores'])) {
            $mensaje = is_string($context['errores'])
                ? $context['errores']
                : json_encode($context['errores'], JSON_UNESCAPED_UNICODE);
        }

        $fase = null;
        if (str_contains($evento, '.')) {
            $fase = explode('.', $evento, 2)[0];
        }

        self::registrar([
            'origen' => self::ORIGEN_API,
            'fase' => $fase,
            'evento' => $evento,
            'http_status' => $status,
            'mensaje' => $mensaje !== '' ? $mensaje : $evento,
            'trace_id' => $traceId,
            'numero_oc' => self::extraerString($context, ['numero_oc', 'numeroordencompra'])
                ?? self::extraerStringPayload($payload, ['numero_oc', 'numeroordencompra']),
            'cuit_proveedor' => self::extraerString($context, ['cuit_proveedor'])
                ?? self::extraerStringPayload($payload, ['cuit_proveedor']),
            'cuit_empresa' => self::extraerString($context, ['cuit_empresa'])
                ?? self::extraerStringPayload($payload, ['cuit_empresa']),
            'tipo_comprobante' => self::extraerString($context, ['tipo', 'tipo_comprobante', 'tipo_abreviatura', 'tipo_solicitado'])
                ?? self::extraerStringPayload($payload, ['tipo']),
            'ip' => self::extraerString($context, ['ip']),
            'contexto' => $context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    public static function desdePdfIa(
        string $fase,
        string $mensaje,
        int $httpStatus,
        array $contexto = [],
        ?string $archivoNombre = null
    ): void {
        self::registrar([
            'origen' => self::ORIGEN_PDF_IA,
            'fase' => $fase,
            'evento' => 'pdf_ia.'.$fase,
            'http_status' => $httpStatus,
            'mensaje' => $mensaje,
            'numero_oc' => self::extraerString($contexto, ['numero_oc', 'numeroordencompra']),
            'cuit_proveedor' => self::extraerString($contexto, ['cuit_proveedor']),
            'cuit_empresa' => self::extraerString($contexto, ['cuit_empresa']),
            'tipo_comprobante' => self::extraerString($contexto, ['tipo', 'tipo_abreviatura']),
            'archivo_nombre' => $archivoNombre,
            'usuario_id' => Auth::id() ? (int) Auth::id() : null,
            'ip' => request()?->ip(),
            'contexto' => $contexto,
        ]);
    }

    public static function etiquetaOrigen(?string $origen): string
    {
        return match ($origen) {
            self::ORIGEN_PDF_IA => 'PDF — IA Anita',
            self::ORIGEN_API => 'Agente / API (IA)',
            default => (string) $origen,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $keys
     */
    private static function extraerString(array $context, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }
            $v = trim((string) $context[$key]);
            if ($v !== '') {
                return mb_substr($v, 0, 40);
            }
        }

        return null;
    }

    /**
     * @param  mixed  $payload
     * @param  list<string>  $keys
     */
    private static function extraerStringPayload(mixed $payload, array $keys): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        return self::extraerString($payload, $keys);
    }

    private static function nullSiVacio(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $s = trim((string) $valor);

        return $s === '' ? null : mb_substr($s, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private static function truncarJson(array $contexto): string
    {
        $json = json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (! is_string($json)) {
            return '{}';
        }

        if (strlen($json) > 60000) {
            return substr($json, 0, 60000).'…';
        }

        return $json;
    }
}
