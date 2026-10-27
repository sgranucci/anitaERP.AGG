<?php

namespace App\Support\Seguridad;

/**
 * Parecido de una palabra mal escrita: letra de más, de menos, o dos letras cambiadas de lugar.
 * Una sola letra distinta con el mismo largo no alcanza (precios no es premios).
 */
class BuscadorParecidoSupport
{
    /**
     * 0 si no se parece. Más alto si el parecido está en el título.
     */
    public static function puntaje(string $consulta, string $titulo, string $contexto): int
    {
        $tokens = self::tokensLargos($consulta);
        if ($tokens === []) {
            return 0;
        }

        $palabrasTitulo = preg_split('/\s+/', $titulo) ?: [];
        $palabrasContexto = preg_split('/\s+/', $contexto) ?: [];
        $mejorTitulo = 99;
        $mejorContexto = 99;

        foreach ($tokens as $token) {
            $enTitulo = self::mejorDistancia($token, $palabrasTitulo);
            $enContexto = self::mejorDistancia($token, $palabrasContexto);
            if ($enTitulo > 2 && $enContexto > 2) {
                return 0;
            }
            $mejorTitulo = min($mejorTitulo, $enTitulo);
            $mejorContexto = min($mejorContexto, $enContexto);
        }

        if ($mejorTitulo <= 1) {
            return 50;
        }
        if ($mejorTitulo === 2) {
            return 44;
        }
        if ($mejorContexto <= 2) {
            return 40;
        }

        return 0;
    }

    /**
     * Formas para un LIKE de nombre: transposiciones y singular/plural.
     * Solo consultas de una palabra; si no, el LIKE de la frase alcanza.
     *
     * @return list<string>
     */
    public static function variantes(string $consulta): array
    {
        $q = trim($consulta);
        if (str_contains($q, ' ') || mb_strlen($q) < 5 || mb_strlen($q) > 18) {
            return [];
        }

        $formas = [$q];
        $raiz = self::raiz($q);
        if ($raiz !== $q) {
            $formas[] = $raiz;
        } else {
            $formas[] = $q.'s';
        }

        $chars = str_split($q);
        $n = count($chars);
        for ($i = 0; $i < $n - 1; $i++) {
            $swap = $chars;
            [$swap[$i], $swap[$i + 1]] = [$swap[$i + 1], $swap[$i]];
            $formas[] = implode('', $swap);
        }

        $unicas = [];
        foreach ($formas as $forma) {
            if ($forma === $q || mb_strlen($forma) < 4 || isset($unicas[$forma])) {
                continue;
            }
            $unicas[$forma] = $forma;
            if (count($unicas) >= 10) {
                break;
            }
        }

        return array_values($unicas);
    }

    /**
     * La consulta con un hueco de una letra (clinte → cli_nte, que toma cliente).
     *
     * @return list<string>
     */
    public static function patronesLetraFaltante(string $consulta): array
    {
        $q = trim($consulta);
        $n = mb_strlen($q);
        if (str_contains($q, ' ') || $n < 5 || $n > 14) {
            return [];
        }

        $chars = str_split($q);
        $patrones = [];
        for ($i = 0; $i <= $n; $i++) {
            $antes = implode('', array_slice($chars, 0, $i));
            $despues = implode('', array_slice($chars, $i));
            $patrones[] = self::escapeLike($antes).'_'.self::escapeLike($despues);
        }

        return $patrones;
    }

    /**
     * @param  list<string>  $palabras
     */
    private static function mejorDistancia(string $token, array $palabras): int
    {
        $mejor = 99;
        $formasToken = array_unique([$token, self::raiz($token)]);

        foreach ($palabras as $palabra) {
            if (mb_strlen($palabra) < 4) {
                continue;
            }
            foreach ($formasToken as $formaToken) {
                foreach (array_unique([$palabra, self::raiz($palabra)]) as $formaPalabra) {
                    $dist = self::distanciaAceptada($formaToken, $formaPalabra);
                    if ($dist < $mejor) {
                        $mejor = $dist;
                    }
                }
            }
        }

        return $mejor;
    }

    public static function distancia(string $a, string $b): int
    {
        return self::distanciaAceptada($a, $b);
    }

    private static function distanciaAceptada(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 99;
        }
        if ($a === $b) {
            return 0;
        }
        if (abs(strlen($a) - strlen($b)) > 2) {
            return 99;
        }
        if (strlen($a) < 3 || strlen($b) < 3 || substr($a, 0, 3) !== substr($b, 0, 3)) {
            return 99;
        }
        if (self::esTransposicion($a, $b)) {
            return 1;
        }

        $dist = levenshtein($a, $b);
        if ($dist > 2) {
            return 99;
        }
        if (substr($a, 0, 4) !== substr($b, 0, 4)) {
            if ($dist >= 2 || strlen($a) === strlen($b)) {
                return 99;
            }
        }

        return $dist;
    }

    private static function esTransposicion(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b) || $a === $b) {
            return false;
        }

        $diffs = [];
        $n = strlen($a);
        for ($i = 0; $i < $n; $i++) {
            if ($a[$i] !== $b[$i]) {
                $diffs[] = $i;
                if (count($diffs) > 2) {
                    return false;
                }
            }
        }

        return count($diffs) === 2
            && $diffs[1] === $diffs[0] + 1
            && $a[$diffs[0]] === $b[$diffs[1]]
            && $a[$diffs[1]] === $b[$diffs[0]];
    }

    /**
     * @return list<string>
     */
    private static function tokensLargos(string $consulta): array
    {
        $tokens = [];
        foreach (preg_split('/\s+/', trim($consulta)) ?: [] as $token) {
            if (mb_strlen($token) >= 5) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private static function raiz(string $token): string
    {
        if (strlen($token) > 4 && str_ends_with($token, 's')) {
            return substr($token, 0, -1);
        }

        return $token;
    }

    private static function escapeLike(string $valor): string
    {
        return addcslashes($valor, '%_\\');
    }
}
