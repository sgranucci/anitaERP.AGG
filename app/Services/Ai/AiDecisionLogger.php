<?php

namespace App\Services\Ai;

use App\Models\Ai\AiDecision;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste cada decisión de IA en ai_decision (auditoría de governance) y deja traza en log.
 * Nunca interrumpe el flujo de negocio: si falla el guardado, solo loguea.
 */
final class AiDecisionLogger
{
    /**
     * @param  array{
     *   skill:string,
     *   accion:string,
     *   driver?:string,
     *   model?:string,
     *   empresa_id?:int|null,
     *   usuario_id?:int|null,
     *   entidad_tipo?:string|null,
     *   entidad_id?:int|null,
     *   score?:float|null,
     *   latencia_ms?:int|null,
     *   input_hash?:string|null,
     *   payload?:array<string,mixed>|null,
     * }  $datos
     */
    public function registrar(array $datos): ?AiDecision
    {
        $log = [
            'skill' => $datos['skill'] ?? null,
            'accion' => $datos['accion'] ?? null,
            'driver' => $datos['driver'] ?? null,
            'model' => $datos['model'] ?? null,
            'score' => $datos['score'] ?? null,
            'latencia_ms' => $datos['latencia_ms'] ?? null,
        ];
        $this->traza('info', 'ai.decision', $log);

        if (! filter_var(config('ai.decision_log.persistir', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        try {
            return AiDecision::create([
                'skill' => (string) ($datos['skill'] ?? ''),
                'accion' => (string) ($datos['accion'] ?? AiDecision::ACCION_SUGERIDA),
                'driver' => $datos['driver'] ?? null,
                'model' => $datos['model'] ?? null,
                'empresa_id' => $datos['empresa_id'] ?? null,
                'usuario_id' => $datos['usuario_id'] ?? (auth()->id() ?: null),
                'entidad_tipo' => $datos['entidad_tipo'] ?? null,
                'entidad_id' => $datos['entidad_id'] ?? null,
                'score' => $datos['score'] ?? null,
                'latencia_ms' => $datos['latencia_ms'] ?? null,
                'input_hash' => $datos['input_hash'] ?? null,
                'payload' => $this->recortarPayload($datos['payload'] ?? null),
            ]);
        } catch (Throwable $e) {
            $this->traza('warning', 'ai.decision_persist_fallo', [
                'message' => $e->getMessage(),
                'skill' => $datos['skill'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * Marca una decisión previa con la resolución humana (confirmada/descartada/editada).
     *
     * @param  array{entidad_id?:int|null, entidad_tipo?:string|null}  $extra
     *         Datos conocidos recién al resolver (ej. id del registro grabado).
     */
    public function resolver(int $decisionId, string $accion, ?int $usuarioId = null, array $extra = []): void
    {
        if (! filter_var(config('ai.decision_log.persistir', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            $decision = AiDecision::find($decisionId);
            if (! $decision) {
                return;
            }
            $decision->accion = $accion;
            $decision->resuelto_por = $usuarioId ?? (auth()->id() ?: null);
            $decision->resuelto_at = now();
            if (isset($extra['entidad_id']) && is_numeric($extra['entidad_id'])) {
                $decision->entidad_id = (int) $extra['entidad_id'];
            }
            if (! empty($extra['entidad_tipo'])) {
                $decision->entidad_tipo = (string) $extra['entidad_tipo'];
            }
            $decision->save();
        } catch (Throwable $e) {
            $this->traza('warning', 'ai.decision_resolver_fallo', [
                'message' => $e->getMessage(),
                'decision_id' => $decisionId,
            ]);
        }
    }

    /**
     * La traza es observabilidad, no negocio: si el canal falla (permisos, disco),
     * la auditoría en base y el flujo del usuario deben seguir adelante.
     *
     * @param  array<string,mixed>  $contexto
     */
    private function traza(string $nivel, string $mensaje, array $contexto): void
    {
        try {
            Log::channel((string) config('ai.log_channel', 'ai'))->log($nivel, $mensaje, $contexto);
        } catch (Throwable $e) {
            try {
                Log::log($nivel, $mensaje, $contexto + ['_canal_ai_fallo' => $e->getMessage()]);
            } catch (Throwable) {
                // Sin canal disponible: se descarta la traza antes que interrumpir el proceso.
            }
        }
    }

    /**
     * @param  array<string,mixed>|null  $payload
     * @return array<string,mixed>|null
     */
    private function recortarPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $max = (int) config('ai.decision_log.max_payload_chars', 20000);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json !== false && mb_strlen($json) > $max) {
            return ['_truncado' => true, '_resumen' => mb_substr($json, 0, $max)];
        }

        return $payload;
    }
}
