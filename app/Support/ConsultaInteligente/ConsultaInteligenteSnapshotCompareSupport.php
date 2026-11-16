<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteSnapshot;

/**
 * Compara dos snapshots del mismo usuario (mismas columnas preferidas).
 */
final class ConsultaInteligenteSnapshotCompareSupport
{
    /**
     * @return array{
     *   ok: bool,
     *   mensaje?: string,
     *   a?: ConsultaInteligenteSnapshot,
     *   b?: ConsultaInteligenteSnapshot,
     *   columnas: list<string>,
     *   labels: list<string>,
     *   solo_a: list<array<string, string>>,
     *   solo_b: list<array<string, string>>,
     *   cambiados: list<array{clave: string, a: array<string, string>, b: array<string, string>, campos: list<string>}>,
     *   iguales: int
     * }
     */
    public static function comparar(int $idA, int $idB, ?int $usuarioId = null): array
    {
        $a = ConsultaInteligenteSnapshotSupport::encontrar($idA, $usuarioId);
        $b = ConsultaInteligenteSnapshotSupport::encontrar($idB, $usuarioId);
        if (! $a || ! $b) {
            return [
                'ok' => false,
                'mensaje' => 'Uno o ambos snapshots no existen o vencieron.',
                'columnas' => [],
                'labels' => [],
                'solo_a' => [],
                'solo_b' => [],
                'cambiados' => [],
                'iguales' => 0,
            ];
        }

        $labelsA = is_array($a->columnas_json) ? array_values($a->columnas_json) : [];
        $labelsB = is_array($b->columnas_json) ? array_values($b->columnas_json) : [];
        $filasA = is_array($a->filas_json) ? $a->filas_json : [];
        $filasB = is_array($b->filas_json) ? $b->filas_json : [];

        $keysA = self::keysDesdeFilas($filasA);
        $keysB = self::keysDesdeFilas($filasB);
        $columnas = $keysA !== [] ? $keysA : $keysB;
        if ($columnas === []) {
            return [
                'ok' => false,
                'mensaje' => 'Los snapshots no tienen filas para comparar.',
                'columnas' => [],
                'labels' => [],
                'solo_a' => [],
                'solo_b' => [],
                'cambiados' => [],
                'iguales' => 0,
            ];
        }

        $labels = [];
        foreach ($columnas as $i => $k) {
            $labels[] = (string) ($labelsA[$i] ?? $labelsB[$i] ?? $k);
        }

        $mapA = self::indexar($filasA, $columnas);
        $mapB = self::indexar($filasB, $columnas);

        $soloA = [];
        $soloB = [];
        $cambiados = [];
        $iguales = 0;

        foreach ($mapA as $clave => $fila) {
            if (! isset($mapB[$clave])) {
                $soloA[] = $fila;
                continue;
            }
            $diff = self::diffCampos($fila, $mapB[$clave], $columnas);
            if ($diff === []) {
                $iguales++;
            } else {
                $cambiados[] = [
                    'clave' => $clave,
                    'a' => $fila,
                    'b' => $mapB[$clave],
                    'campos' => $diff,
                ];
            }
        }
        foreach ($mapB as $clave => $fila) {
            if (! isset($mapA[$clave])) {
                $soloB[] = $fila;
            }
        }

        return [
            'ok' => true,
            'a' => $a,
            'b' => $b,
            'columnas' => $columnas,
            'labels' => $labels,
            'solo_a' => $soloA,
            'solo_b' => $soloB,
            'cambiados' => $cambiados,
            'iguales' => $iguales,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<string>
     */
    private static function keysDesdeFilas(array $filas): array
    {
        $first = $filas[0] ?? null;
        if (! is_array($first)) {
            return [];
        }

        return array_map('strval', array_keys($first));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $columnas
     * @return array<string, array<string, string>>
     */
    private static function indexar(array $filas, array $columnas): array
    {
        $out = [];
        foreach ($filas as $i => $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $norm = [];
            foreach ($columnas as $c) {
                $norm[$c] = trim((string) ($fila[$c] ?? ''));
            }
            $clave = self::claveFila($norm, $columnas, (int) $i);
            $out[$clave] = $norm;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $fila
     * @param  list<string>  $columnas
     */
    private static function claveFila(array $fila, array $columnas, int $fallbackIndex): string
    {
        foreach (['id', 'codigo', 'numero', 'legajo', 'sku'] as $pref) {
            foreach ($columnas as $c) {
                if (mb_strtolower($c) === $pref && ($fila[$c] ?? '') !== '') {
                    return $pref.':'.$fila[$c];
                }
            }
        }
        // Firma estable de la fila completa.
        return 'row:'.md5(json_encode($fila) ?: (string) $fallbackIndex);
    }

    /**
     * @param  array<string, string>  $a
     * @param  array<string, string>  $b
     * @param  list<string>  $columnas
     * @return list<string>
     */
    private static function diffCampos(array $a, array $b, array $columnas): array
    {
        $diff = [];
        foreach ($columnas as $c) {
            if (($a[$c] ?? '') !== ($b[$c] ?? '')) {
                $diff[] = $c;
            }
        }

        return $diff;
    }
}
