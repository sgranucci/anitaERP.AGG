<?php

namespace App\Support\Seguridad;

/**
 * Palabras reales del menú y de los manuales.
 * Completa el prefijo («cli» → clientes) y corrige contra esa lista («clinete» → clientes).
 */
class BuscadorDiccionarioSupport
{
    public const LIMITE_SUGERENCIAS = 5;

    /** @var list<string> */
    private const VACIAS = [
        'para', 'como', 'esta', 'este', 'entre', 'sobre', 'desde', 'hasta', 'donde',
        'cuando', 'porque', 'tambien', 'tiene', 'tienen', 'puede', 'pueden', 'cada',
        'mismo', 'misma', 'otros', 'otras', 'solo', 'toda', 'todo', 'todos', 'todas',
        'pero', 'bien', 'hace', 'hacia', 'segun', 'menos', 'mismo',
    ];

    /**
     * @param  array<string, int>  $frecuencias
     */
    public static function sumar(array &$frecuencias, string $texto, int $peso): void
    {
        if ($peso <= 0) {
            return;
        }

        $normalizado = self::normalizar($texto);
        if ($normalizado === '') {
            return;
        }

        foreach (preg_split('/\s+/', $normalizado) ?: [] as $palabra) {
            if (strlen($palabra) < 4 || isset(self::vacias()[$palabra]) || ctype_digit($palabra)) {
                continue;
            }
            $frecuencias[$palabra] = ($frecuencias[$palabra] ?? 0) + $peso;
        }
    }

    /**
     * @param  array<string, int>  $frecuencias
     * @return list<array{texto: string, tipo: string}>
     */
    public static function sugerir(string $consulta, array $frecuencias): array
    {
        $q = self::normalizar($consulta);
        if (mb_strlen($q) < 2 || $frecuencias === [] || str_contains($q, ' ')) {
            return [];
        }

        $completar = [];
        foreach ($frecuencias as $palabra => $frecuencia) {
            if ($palabra === $q || ! str_starts_with($palabra, $q)) {
                continue;
            }
            $completar[] = ['texto' => $palabra, 'frecuencia' => $frecuencia];
        }

        if ($completar !== []) {
            usort($completar, function (array $a, array $b): int {
                $porFrecuencia = $b['frecuencia'] <=> $a['frecuencia'];
                if ($porFrecuencia !== 0) {
                    return $porFrecuencia;
                }

                return strlen($a['texto']) <=> strlen($b['texto']);
            });

            return array_map(
                fn (array $fila): array => ['texto' => $fila['texto'], 'tipo' => 'completar'],
                array_slice($completar, 0, self::LIMITE_SUGERENCIAS)
            );
        }

        if (mb_strlen($q) < 5 || isset($frecuencias[$q])) {
            return [];
        }

        $mejor = null;
        $mejorDistancia = 99;
        $mejorFrecuencia = -1;
        foreach ($frecuencias as $palabra => $frecuencia) {
            $distancia = self::distanciaContraPalabra($q, $palabra);
            if ($distancia > 2) {
                continue;
            }
            if ($distancia < $mejorDistancia || ($distancia === $mejorDistancia && $frecuencia > $mejorFrecuencia)) {
                $mejor = $palabra;
                $mejorDistancia = $distancia;
                $mejorFrecuencia = $frecuencia;
            }
        }

        if ($mejor === null) {
            return [];
        }

        return [['texto' => $mejor, 'tipo' => 'correccion']];
    }

    private static function distanciaContraPalabra(string $consulta, string $palabra): int
    {
        $formasConsulta = array_unique([$consulta, self::raiz($consulta)]);
        $formasPalabra = array_unique([$palabra, self::raiz($palabra)]);
        $mejor = 99;
        foreach ($formasConsulta as $formaConsulta) {
            foreach ($formasPalabra as $formaPalabra) {
                $distancia = BuscadorParecidoSupport::distancia($formaConsulta, $formaPalabra);
                if ($distancia < $mejor) {
                    $mejor = $distancia;
                }
            }
        }

        return $mejor;
    }

    private static function raiz(string $token): string
    {
        if (strlen($token) > 4 && str_ends_with($token, 's')) {
            return substr($token, 0, -1);
        }

        return $token;
    }

    /**
     * @return array<string, true>
     */
    private static function vacias(): array
    {
        static $mapa = null;
        if ($mapa === null) {
            $mapa = array_fill_keys(self::VACIAS, true);
        }

        return $mapa;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/u', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }
}
