<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;
use App\Services\Ai\Contracts\AiDriverInterface;
use App\Services\Ai\Support\AiJsonExtractor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver Ollama local (/api/generate). Mismo motor que el pipeline de facturas.
 */
final class OllamaAiDriver implements AiDriverInterface
{
    public function nombre(): string
    {
        return 'ollama';
    }

    public function disponible(): bool
    {
        return trim((string) config('ai.drivers.ollama.url', '')) !== '';
    }

    public function generar(AiPrompt $prompt): AiResult
    {
        $url = rtrim((string) config('ai.drivers.ollama.url', 'http://127.0.0.1:11434'), '/');
        $model = $prompt->model ?? (string) config('ai.drivers.ollama.model', 'qwen2.5:14b-instruct');
        $timeout = $prompt->timeout ?? (int) config('ai.drivers.ollama.timeout', 180);
        $temperature = $prompt->temperature ?? (float) config('ai.drivers.ollama.temperature', 0.05);
        $maxTokens = $prompt->maxTokens ?? (int) config('ai.drivers.ollama.max_tokens', 4096);

        $texto = $prompt->system !== null && $prompt->system !== ''
            ? $prompt->system."\n\n".$prompt->prompt
            : $prompt->prompt;

        $inicio = microtime(true);

        try {
            $payload = [
                'model' => $model,
                'prompt' => $texto,
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $maxTokens,
                ],
            ];
            if ($prompt->esperaJson) {
                $payload['format'] = 'json';
            }

            $response = Http::timeout($timeout)->post($url.'/api/generate', $payload);
            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            if (! $response->successful()) {
                Log::channel($this->logChannel())->warning('ai.ollama_error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'meta' => $prompt->meta,
                ]);

                return AiResult::fallo('Ollama respondió '.$response->status(), $this->nombre(), $model, $latencia);
            }

            $body = $response->json();
            $raw = (string) ($body['response'] ?? '');
            $json = $prompt->esperaJson ? AiJsonExtractor::extraer($raw) : null;

            return AiResult::exito($raw, $json, $this->nombre(), $model, $latencia, [
                'eval_count' => $body['eval_count'] ?? null,
                'prompt_eval_count' => $body['prompt_eval_count'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $latencia = (int) round((microtime(true) - $inicio) * 1000);
            Log::channel($this->logChannel())->info('ai.ollama_no_disponible', [
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
