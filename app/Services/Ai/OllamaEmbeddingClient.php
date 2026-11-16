<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente Ollama /api/embeddings (modelo tipo nomic-embed-text).
 */
final class OllamaEmbeddingClient
{
    public function habilitado(): bool
    {
        return filter_var(config('ai.rag_manuales.embeddings_habilitado', false), FILTER_VALIDATE_BOOLEAN)
            && ! filter_var(config('ai.kill_switch', false), FILTER_VALIDATE_BOOLEAN)
            && trim((string) config('ai.drivers.ollama.url', '')) !== '';
    }

    /**
     * @return list<float>|null
     */
    public function embed(string $texto): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }

        $url = rtrim((string) config('ai.drivers.ollama.url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('ai.rag_manuales.embeddings_model', 'nomic-embed-text');
        $timeout = max(5, (int) config('ai.rag_manuales.embeddings_timeout', 60));

        try {
            $response = Http::timeout($timeout)->post($url.'/api/embeddings', [
                'model' => $model,
                'prompt' => mb_substr($texto, 0, 8000),
            ]);
            if (! $response->successful()) {
                Log::channel((string) config('ai.log_channel', 'ai'))->warning('ai.ollama_embeddings_http', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                    'model' => $model,
                ]);

                return null;
            }
            $json = $response->json();
            $vec = $json['embedding'] ?? null;
            if (! is_array($vec) || $vec === []) {
                return null;
            }

            return array_values(array_map('floatval', $vec));
        } catch (Throwable $e) {
            Log::channel((string) config('ai.log_channel', 'ai'))->warning('ai.ollama_embeddings_error', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            return null;
        }
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
