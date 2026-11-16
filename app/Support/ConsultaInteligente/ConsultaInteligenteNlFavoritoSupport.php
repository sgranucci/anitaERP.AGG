<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteNlFavorito;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Preguntas NL favoritas del usuario (persistidas o cache si falta la tabla).
 */
final class ConsultaInteligenteNlFavoritoSupport
{
    public const MAX = 20;

    private const CACHE_PREFIX = 'ci_nl_favoritos_u';

    /**
     * @return list<array{id: string, titulo: string, pregunta: string}>
     */
    public static function listar(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1) {
            return [];
        }

        if (self::tablaLista()) {
            return ConsultaInteligenteNlFavorito::query()
                ->where('usuario_id', $usuarioId)
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(static fn (ConsultaInteligenteNlFavorito $f) => [
                    'id' => (string) $f->id,
                    'titulo' => (string) $f->titulo,
                    'pregunta' => (string) $f->pregunta,
                ])
                ->all();
        }

        return self::desdeCache($usuarioId);
    }

    public static function guardar(string $pregunta, ?string $titulo = null, ?int $usuarioId = null): bool
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $pregunta = trim(preg_replace('/\s+/u', ' ', $pregunta) ?? $pregunta);
        if ($usuarioId < 1 || $pregunta === '') {
            return false;
        }
        $titulo = trim((string) $titulo);
        if ($titulo === '') {
            $titulo = mb_substr($pregunta, 0, 60);
        }
        $titulo = mb_substr($titulo, 0, 80);
        $pregunta = mb_substr($pregunta, 0, 500);

        $actuales = self::listar($usuarioId);
        foreach ($actuales as $f) {
            if (mb_strtolower($f['pregunta']) === mb_strtolower($pregunta)) {
                return true;
            }
        }
        if (count($actuales) >= self::MAX) {
            return false;
        }

        if (self::tablaLista()) {
            $orden = (int) (ConsultaInteligenteNlFavorito::query()
                ->where('usuario_id', $usuarioId)
                ->max('orden') ?? 0) + 1;
            ConsultaInteligenteNlFavorito::query()->create([
                'usuario_id' => $usuarioId,
                'titulo' => $titulo,
                'pregunta' => $pregunta,
                'orden' => $orden,
            ]);

            return true;
        }

        $lista = self::desdeCache($usuarioId);
        $lista[] = [
            'id' => (string) Str::uuid(),
            'titulo' => $titulo,
            'pregunta' => $pregunta,
        ];
        Cache::forever(self::claveCache($usuarioId), $lista);

        return true;
    }

    public static function eliminar(string $id, ?int $usuarioId = null): bool
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || $id === '') {
            return false;
        }

        if (self::tablaLista()) {
            $row = ConsultaInteligenteNlFavorito::query()
                ->where('usuario_id', $usuarioId)
                ->where('id', (int) $id)
                ->first();
            if (! $row) {
                return false;
            }
            $row->delete();

            return true;
        }

        $lista = array_values(array_filter(
            self::desdeCache($usuarioId),
            static fn (array $f) => ($f['id'] ?? '') !== $id
        ));
        Cache::forever(self::claveCache($usuarioId), $lista);

        return true;
    }

    /**
     * Últimas preguntas NL del usuario (desde auditoría).
     *
     * @return list<array{pregunta: string, entidad: ?string, ok: bool, cuando: string}>
     */
    public static function historialReciente(int $limite = 8, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || ! Schema::hasTable('consulta_inteligente_auditoria')) {
            return [];
        }

        try {
            $filas = \App\Models\Direccion\ConsultaInteligenteAuditoria::query()
                ->where('usuario_id', $usuarioId)
                ->where('accion', ConsultaInteligenteAuditoriaSupport::ACCION_NL)
                ->orderByDesc('id')
                ->limit(max(1, min(30, $limite * 3)))
                ->get(['detalle', 'entidad', 'meta_json', 'created_at']);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        $vistos = [];
        foreach ($filas as $f) {
            $pregunta = trim((string) ($f->detalle ?? ''));
            if ($pregunta === '') {
                continue;
            }
            $key = mb_strtolower($pregunta);
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            $meta = is_array($f->meta_json) ? $f->meta_json : [];
            $out[] = [
                'pregunta' => mb_substr($pregunta, 0, 200),
                'entidad' => $f->entidad ? (string) $f->entidad : null,
                'ok' => (bool) ($meta['ok'] ?? false),
                'cuando' => $f->created_at?->format('d/m H:i') ?? '',
            ];
            if (count($out) >= $limite) {
                break;
            }
        }

        return $out;
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_nl_favorito');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{id: string, titulo: string, pregunta: string}>
     */
    private static function desdeCache(int $usuarioId): array
    {
        $raw = Cache::get(self::claveCache($usuarioId), []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $f) {
            if (! is_array($f) || trim((string) ($f['pregunta'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'id' => (string) ($f['id'] ?? Str::uuid()),
                'titulo' => (string) ($f['titulo'] ?? mb_substr((string) $f['pregunta'], 0, 60)),
                'pregunta' => (string) $f['pregunta'],
            ];
        }

        return $out;
    }

    private static function claveCache(int $usuarioId): string
    {
        return self::CACHE_PREFIX.$usuarioId;
    }
}
