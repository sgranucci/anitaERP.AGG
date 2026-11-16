<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Contexto multi-turno NL: stack de refinamientos en sesión.
 */
final class ConsultaInteligenteNlContextoSupport
{
    private const SESSION_KEY = 'ci_nl_contexto';

    public const MAX_TURNOS = 5;

    /**
     * @param  array<string, mixed>  $resultado
     */
    public static function recordar(string $pregunta, array $resultado): void
    {
        $pregunta = trim($pregunta);
        if ($pregunta === '' || empty($resultado['ok'])) {
            return;
        }

        $turnos = self::turnos();
        $turnos[] = [
            'pregunta' => mb_substr($pregunta, 0, 500),
            'entidad' => (string) ($resultado['entidad'] ?? ''),
            'explicacion' => mb_substr((string) ($resultado['explicacion'] ?? ''), 0, 200),
            'url' => (string) ($resultado['url'] ?? ''),
        ];
        if (count($turnos) > self::MAX_TURNOS) {
            $turnos = array_values(array_slice($turnos, -self::MAX_TURNOS));
        }
        session([self::SESSION_KEY => ['turnos' => $turnos]]);
    }

    /**
     * Último turno (compat UI).
     *
     * @return array{pregunta: string, entidad: string, explicacion: string, url: string}|null
     */
    public static function actual(): ?array
    {
        $turnos = self::turnos();
        if ($turnos === []) {
            return null;
        }

        return $turnos[array_key_last($turnos)];
    }

    /**
     * @return list<array{pregunta: string, entidad: string, explicacion: string, url: string}>
     */
    public static function turnos(): array
    {
        $ctx = session(self::SESSION_KEY);
        if (! is_array($ctx)) {
            return [];
        }
        // Compat: formato viejo de un solo slot.
        if (isset($ctx['pregunta']) && ! isset($ctx['turnos'])) {
            $pregunta = trim((string) $ctx['pregunta']);
            if ($pregunta === '') {
                return [];
            }

            return [[
                'pregunta' => $pregunta,
                'entidad' => (string) ($ctx['entidad'] ?? ''),
                'explicacion' => (string) ($ctx['explicacion'] ?? ''),
                'url' => (string) ($ctx['url'] ?? ''),
            ]];
        }
        $turnos = $ctx['turnos'] ?? [];
        if (! is_array($turnos)) {
            return [];
        }
        $out = [];
        foreach ($turnos as $t) {
            if (! is_array($t) || trim((string) ($t['pregunta'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'pregunta' => (string) $t['pregunta'],
                'entidad' => (string) ($t['entidad'] ?? ''),
                'explicacion' => (string) ($t['explicacion'] ?? ''),
                'url' => (string) ($t['url'] ?? ''),
            ];
        }

        return $out;
    }

    /** Cadena legible para preview (explicaciones o preguntas). */
    public static function cadenaLegible(): string
    {
        $parts = [];
        foreach (self::turnos() as $t) {
            $parts[] = $t['explicacion'] !== '' ? $t['explicacion'] : $t['pregunta'];
        }

        return implode(' → ', $parts);
    }

    public static function deshacerUltimo(): bool
    {
        $turnos = self::turnos();
        if ($turnos === []) {
            return false;
        }
        array_pop($turnos);
        if ($turnos === []) {
            self::limpiar();

            return true;
        }
        session([self::SESSION_KEY => ['turnos' => array_values($turnos)]]);

        return true;
    }

    public static function limpiar(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Combina el refinamiento con la pregunta base (sin inventar SQL).
     */
    public static function componer(string $base, string $refino): string
    {
        $base = trim($base);
        $refino = trim($refino);
        if ($base === '') {
            return $refino;
        }
        if ($refino === '') {
            return $base;
        }
        if (mb_stripos($refino, $base) !== false || mb_strlen($refino) > 80) {
            return $refino;
        }

        return mb_substr($base.' · '.$refino, 0, 500);
    }
}
