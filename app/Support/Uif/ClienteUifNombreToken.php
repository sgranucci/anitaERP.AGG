<?php

namespace App\Support\Uif;

/**
 * Normalización y tokens de nombre (alineado con arca-padron.js / inferencia de sexo).
 */
final class ClienteUifNombreToken
{
    public static function normalizar(string $texto): string
    {
        $v = mb_strtoupper(trim($texto), 'UTF-8');
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
        $v = preg_replace('/[^A-Z0-9 ]+/', ' ', $v) ?? $v;

        return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    }

    /**
     * Tokens candidatos a nombre propio (prioridad: último, primero, intermedios).
     *
     * @return list<string>
     */
    public static function candidatosDesdeNombreCompleto(string $nombre): array
    {
        $partes = array_values(array_filter(
            explode(' ', self::normalizar($nombre)),
            static fn (string $t): bool => strlen($t) > 1
        ));

        if ($partes === []) {
            return [];
        }

        if (count($partes) === 1) {
            return [$partes[0]];
        }

        $orden = [array_slice($partes, -1)[0], $partes[0]];
        if (count($partes) > 2) {
            foreach (array_slice($partes, 1, -1) as $t) {
                $orden[] = $t;
            }
        }

        return array_values(array_unique($orden));
    }

    /**
     * Todos los tokens del nombre (para aprendizaje al guardar).
     *
     * @return list<string>
     */
    public static function todosDesdeNombreCompleto(string $nombre): array
    {
        $partes = array_values(array_unique(array_filter(
            explode(' ', self::normalizar($nombre)),
            static fn (string $t): bool => strlen($t) > 1
        )));

        return $partes;
    }
}
