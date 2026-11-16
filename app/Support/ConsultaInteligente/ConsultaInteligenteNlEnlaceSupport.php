<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteNlEnlace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Enlace compartible de una pregunta NL (token opaco, con permiso al abrir).
 */
final class ConsultaInteligenteNlEnlaceSupport
{
    private const CACHE_PREFIX = 'ci_nl_enlace_';

    private const TTL_DIAS = 90;

    /**
     * @param  array<string, mixed>|null  $query
     * @return array{ok: bool, token?: string, url?: string, mensaje?: string}
     */
    public static function crear(
        string $pregunta,
        ?string $titulo = null,
        ?string $entidad = null,
        ?array $query = null,
        ?int $usuarioId = null
    ): array {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $pregunta = trim(preg_replace('/\s+/u', ' ', $pregunta) ?? $pregunta);
        if ($usuarioId < 1 || $pregunta === '') {
            return ['ok' => false, 'mensaje' => 'No hay pregunta para compartir.'];
        }
        $titulo = trim((string) $titulo);
        if ($titulo === '') {
            $titulo = mb_substr($pregunta, 0, 60);
        }
        $token = Str::lower(Str::random(40));
        $payload = [
            'token' => $token,
            'usuario_id' => $usuarioId,
            'titulo' => mb_substr($titulo, 0, 80),
            'pregunta' => mb_substr($pregunta, 0, 500),
            'entidad' => $entidad ? mb_substr($entidad, 0, 64) : null,
            'query_json' => $query,
            'created_at' => now()->toDateTimeString(),
        ];

        if (self::tablaLista()) {
            ConsultaInteligenteNlEnlace::query()->create([
                'token' => $token,
                'usuario_id' => $usuarioId,
                'titulo' => $payload['titulo'],
                'pregunta' => $payload['pregunta'],
                'entidad' => $payload['entidad'],
                'query_json' => $query,
            ]);
        } else {
            Cache::put(self::CACHE_PREFIX.$token, $payload, now()->addDays(self::TTL_DIAS));
        }

        return [
            'ok' => true,
            'token' => $token,
            'url' => route('enlace_consulta_inteligente', ['token' => $token]),
        ];
    }

    /**
     * @return array{titulo: string, pregunta: string, entidad: ?string, query: ?array, usuario_id: int}|null
     */
    public static function resolver(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^[a-z0-9]{20,64}$/i', $token)) {
            return null;
        }

        if (self::tablaLista()) {
            $row = ConsultaInteligenteNlEnlace::query()->where('token', $token)->first();
            if (! $row) {
                return null;
            }

            return [
                'titulo' => (string) $row->titulo,
                'pregunta' => (string) $row->pregunta,
                'entidad' => $row->entidad ? (string) $row->entidad : null,
                'query' => is_array($row->query_json) ? $row->query_json : null,
                'usuario_id' => (int) $row->usuario_id,
            ];
        }

        $raw = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($raw) || trim((string) ($raw['pregunta'] ?? '')) === '') {
            return null;
        }

        return [
            'titulo' => (string) ($raw['titulo'] ?? mb_substr((string) $raw['pregunta'], 0, 60)),
            'pregunta' => (string) $raw['pregunta'],
            'entidad' => ! empty($raw['entidad']) ? (string) $raw['entidad'] : null,
            'query' => is_array($raw['query_json'] ?? null) ? $raw['query_json'] : null,
            'usuario_id' => (int) ($raw['usuario_id'] ?? 0),
        ];
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_nl_enlace');
        } catch (\Throwable) {
            return false;
        }
    }
}
