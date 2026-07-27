<?php

namespace App\Support\Ai;

use App\Models\Ai\AiAgenteEvento;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puente: auditor/proceso determinístico → fila ai_agente_evento + plan HITL.
 * No ejecuta escritura de negocio; no llama al LLM salvo que el plan lo pida después el humano.
 */
final class AiAgenteEventoDispatcherSupport
{
    /**
     * @param  array{
     *   evento: string,
     *   origen: string,
     *   resumen: string,
     *   severidad?: string,
     *   entidad_tipo?: string|null,
     *   entidad_id?: int|null,
     *   empresa_id?: int|null,
     *   payload?: array<string,mixed>,
     *   plan_params?: array<string,mixed>
     * }  $entrada
     */
    public static function registrar(array $entrada): ?AiAgenteEvento
    {
        if (! filter_var(config('ai.agente_evento.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }
        if (filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $evento = strtolower(trim((string) ($entrada['evento'] ?? '')));
        $origen = trim((string) ($entrada['origen'] ?? ''));
        $resumen = trim((string) ($entrada['resumen'] ?? ''));
        if ($evento === '' || $origen === '' || $resumen === '') {
            return null;
        }

        $permitidos = (array) config('ai.agente_evento.eventos', []);
        if ($permitidos !== [] && ! in_array($evento, $permitidos, true)) {
            return null;
        }

        try {
            $planParams = is_array($entrada['plan_params'] ?? null) ? $entrada['plan_params'] : [];
            $planParams['evento'] = $evento;
            $planResult = AiAgenteOperativoSupport::proponerPlan($evento, $planParams);
            $planJson = null;
            if (($planResult['ok'] ?? false) === true) {
                $planJson = [
                    'parrafos' => $planResult['parrafos'] ?? [],
                    'pasos' => $planResult['datos']['pasos'] ?? [],
                    'tabla' => $planResult['tabla'] ?? null,
                ];
            }

            $payload = is_array($entrada['payload'] ?? null) ? $entrada['payload'] : [];
            $maxChars = max(500, (int) config('ai.agente_evento.max_payload_chars', 8000));
            $payloadJson = self::recortarPayload($payload, $maxChars);

            return AiAgenteEvento::query()->create([
                'evento' => $evento,
                'origen' => mb_substr($origen, 0, 120),
                'severidad' => self::normalizarSeveridad((string) ($entrada['severidad'] ?? 'media')),
                'estado' => AiAgenteEvento::ESTADO_PENDIENTE,
                'entidad_tipo' => isset($entrada['entidad_tipo']) ? (string) $entrada['entidad_tipo'] : null,
                'entidad_id' => isset($entrada['entidad_id']) ? (int) $entrada['entidad_id'] : null,
                'empresa_id' => isset($entrada['empresa_id']) && (int) $entrada['empresa_id'] > 0
                    ? (int) $entrada['empresa_id']
                    : null,
                'resumen' => mb_substr($resumen, 0, 500),
                'payload_json' => $payloadJson,
                'plan_json' => $planJson,
            ]);
        } catch (Throwable $e) {
            Log::warning('ai.agente_evento.registrar_fallo', [
                'evento' => $evento,
                'origen' => $origen,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private static function recortarPayload(array $payload, int $maxChars): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || strlen($json) <= $maxChars) {
            return $payload;
        }

        return [
            '_truncated' => true,
            '_bytes' => strlen($json),
            'vista' => mb_substr($json, 0, $maxChars),
        ];
    }

    private static function normalizarSeveridad(string $sev): string
    {
        $sev = strtolower(trim($sev));

        return in_array($sev, ['baja', 'media', 'alta'], true) ? $sev : 'media';
    }
}
