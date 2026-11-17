<?php

namespace App\Support\ReglaNegocio;

/**
 * Evaluador de condiciones estructuradas del motor de reglas de negocio.
 *
 * Formato:
 * [
 *   'logic' => 'and'|'or',
 *   'rules' => [
 *     ['campo' => 'monto', 'op' => 'gte', 'valor' => 1000],
 *     ['logic' => 'or', 'rules' => [...]], // grupo anidado
 *   ],
 * ]
 *
 * Compatibilidad legacy (mapa plano):
 * ['empresa_id' => 1, 'tipo' => ['OC','RE']]
 */
final class ReglaNegocioCondicionSupport
{
    /**
     * @param  array<string, mixed>|null  $condiciones
     * @param  array<string, mixed>  $contexto
     */
    public static function cumple(?array $condiciones, array $contexto): bool
    {
        if ($condiciones === null || $condiciones === []) {
            return true;
        }

        if (isset($condiciones['rules']) && is_array($condiciones['rules'])) {
            return self::evaluarGrupo($condiciones, $contexto);
        }

        // Legacy: mapa campo => valor|lista
        foreach ($condiciones as $clave => $esperado) {
            if ($clave === '' || $clave === 'logic' || $clave === 'rules') {
                continue;
            }
            $actual = self::valorContexto($contexto, (string) $clave);
            if (is_array($esperado)) {
                if (! in_array($actual, $esperado, false) && ! in_array((string) $actual, array_map('strval', $esperado), true)) {
                    return false;
                }
                continue;
            }
            if ((string) $actual !== (string) $esperado) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $grupo
     * @param  array<string, mixed>  $contexto
     */
    private static function evaluarGrupo(array $grupo, array $contexto): bool
    {
        $logic = strtolower((string) ($grupo['logic'] ?? 'and'));
        $rules = $grupo['rules'] ?? [];
        if (! is_array($rules) || $rules === []) {
            return true;
        }

        $esOr = $logic === 'or';
        foreach ($rules as $regla) {
            if (! is_array($regla)) {
                continue;
            }
            if (isset($regla['rules']) && is_array($regla['rules'])) {
                $ok = self::evaluarGrupo($regla, $contexto);
            } else {
                $ok = self::evaluarAtomica($regla, $contexto);
            }
            if ($esOr && $ok) {
                return true;
            }
            if (! $esOr && ! $ok) {
                return false;
            }
        }

        return ! $esOr;
    }

    /**
     * @param  array<string, mixed>  $regla
     * @param  array<string, mixed>  $contexto
     */
    private static function evaluarAtomica(array $regla, array $contexto): bool
    {
        $campo = trim((string) ($regla['campo'] ?? ''));
        $op = strtolower(trim((string) ($regla['op'] ?? 'eq')));
        if ($campo === '') {
            return true;
        }
        $actual = self::valorContexto($contexto, $campo);
        $esperado = $regla['valor'] ?? null;

        return match ($op) {
            'empty' => $actual === null || $actual === '' || $actual === [],
            'not_empty' => ! ($actual === null || $actual === '' || $actual === []),
            'neq' => self::cmp($actual, $esperado) !== 0,
            'gt' => self::cmp($actual, $esperado) > 0,
            'gte' => self::cmp($actual, $esperado) >= 0,
            'lt' => self::cmp($actual, $esperado) < 0,
            'lte' => self::cmp($actual, $esperado) <= 0,
            'in' => in_array((string) $actual, self::lista($esperado), true),
            'not_in' => ! in_array((string) $actual, self::lista($esperado), true),
            'contains' => is_string($actual) && is_scalar($esperado)
                && mb_stripos($actual, (string) $esperado) !== false,
            default => self::cmp($actual, $esperado) === 0, // eq
        };
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private static function valorContexto(array $contexto, string $campo): mixed
    {
        if (array_key_exists($campo, $contexto)) {
            return $contexto[$campo];
        }
        // Dot path simple: meta.urgencia
        if (str_contains($campo, '.')) {
            $cur = $contexto;
            foreach (explode('.', $campo) as $part) {
                if (! is_array($cur) || ! array_key_exists($part, $cur)) {
                    return null;
                }
                $cur = $cur[$part];
            }

            return $cur;
        }

        return null;
    }

    private static function cmp(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return ((float) $a <=> (float) $b);
        }

        return strcmp((string) $a, (string) $b);
    }

    /**
     * @return list<string>
     */
    private static function lista(mixed $valor): array
    {
        if (is_array($valor)) {
            return array_values(array_map('strval', $valor));
        }
        $s = trim((string) $valor);
        if ($s === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $s)), fn ($x) => $x !== ''));
    }
}
