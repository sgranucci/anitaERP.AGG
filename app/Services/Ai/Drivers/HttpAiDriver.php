<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiDriverInterface;
use App\Services\Ai\Support\AiJsonExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver HTTP OpenAI-compatible (/v1/chat/completions): OpenAI, vLLM, LM Studio, etc.
 * Usar solo con datos permitidos fuera de on-prem (aplicar redacción antes en la Skill).
 */
final class HttpAiDriver implements AiDriverInterface
{
    public function nombre(): string
    {
        return 'http';
    }

    public function disponible(): bool
    {
        return trim((string) config('ai.drivers.http.url', '')) !== '';
    }

    public function generar(AiPrompt $prompt): AiResult
    {
        $url = rtrim((string) config('ai.drivers.http.url', ''), '/');
        $model = $prompt->model ?? (string) config('ai.drivers.http.model', '');
        $apiKey = (string) config('ai.drivers.http.api_key', '');
        $timeout = $prompt->timeout ?? (int) config('ai.drivers.http.timeout', 120);
        $temperature = $prompt->temperature ?? (float) config('ai.drivers.http.temperature', 0.05);
        $maxTokens = $prompt->maxTokens ?? (int) config('ai.drivers.http.max_tokens', 4096);

        if ($url === '') {
            return AiResult::fallo('AI_HTTP_URL no configurada', $this->nombre(), $model);
        }

        $mensajes = [];
        if ($prompt->system !== null && $prompt->system !== '') {
            $mensajes[] = ['role' => 'system', 'content' => $prompt->system];
        }
        $mensajes[] = ['role' => 'user', 'content' => $prompt->prompt];

        $payload = [
            'model' => $model,
            'messages' => $mensajes,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];
        if ($prompt->esperaJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $inicio = microtime(true);

        try {
            $request = Http::timeout($timeout)->asJson();
            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url.'/v1/chat/completions', $payload);
            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            if (! $response->successful()) {
                Log::channel($this->logChannel())->warning('ai.http_error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'meta' => $prompt->meta,
                ]);

                return AiResult::fallo('API respondió '.$response->status(), $this->nombre(), $model, $latencia);
            }

            $body = $response->json();
            $raw = (string) ($body['choices'][0]['message']['content'] ?? '');
            $json = $prompt->esperaJson ? AiJsonExtractor::extraer($raw) : null;

            return AiResult::exito($raw, $json, $this->nombre(), $model, $latencia, [
                'usage' => $body['usage'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $latencia = (int) round((microtime(true) - $inicio) * 1000);
            Log::channel($this->logChannel())->info('ai.http_no_disponible', [
                'message' => $e->getMessage(),
                'meta' => $prompt->meta,
            ]);

            return AiResult::fallo($e->getMessage(), $this->nombre(), $model, $latencia);
        }
    }

    private function logChannel(): string
    {
        return (string) config('ai.log_channel', 'ai');
    }
}
