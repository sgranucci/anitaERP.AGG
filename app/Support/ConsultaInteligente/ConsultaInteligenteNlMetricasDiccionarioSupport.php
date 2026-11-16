<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteNlSinonimo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sinónimos NL → métrica (aging, suma, promedio, conteo).
 * Combina catálogo fijo + filas editables del diccionario (columna metrica).
 */
final class ConsultaInteligenteNlMetricasDiccionarioSupport
{
    public const METRICAS = ['conteo', 'suma', 'promedio', 'aging'];

    /**
     * @return list<string>
     */
    public static function frasesFijas(string $metrica): array
    {
        return match ($metrica) {
            'aging' => [
                'aging', 'antiguedad', 'antigüedad', 'por vencer', 'vencimiento',
                'vencidos', 'buckets de vencimiento', 'cartera aging',
            ],
            'suma' => ['suma', 'importe total', 'monto total', 'total importe', 'sumatoria'],
            'promedio' => ['promedio', 'media', 'importe promedio', 'monto promedio'],
            'conteo' => ['conteo', 'cantidad', 'cuantos', 'cuántos', 'cuantas', 'cuántas'],
            default => [],
        };
    }

    /**
     * Detecta métrica pedida en texto ya normalizado (sin tildes preferible).
     */
    public static function detectar(string $textoNormalizado): ?string
    {
        $t = mb_strtolower($textoNormalizado);
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        $mejor = null;
        $mejorLen = 0;
        foreach (self::METRICAS as $metrica) {
            foreach (self::frasesFijas($metrica) as $frase) {
                $f = strtr(mb_strtolower($frase), [
                    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                ]);
                if ($f === '' || ! str_contains($t, $f)) {
                    continue;
                }
                $len = mb_strlen($f);
                if ($len > $mejorLen) {
                    $mejor = $metrica;
                    $mejorLen = $len;
                }
            }
        }

        foreach (self::editables() as $row) {
            $frase = (string) ($row['frase'] ?? '');
            $metrica = (string) ($row['metrica'] ?? '');
            if ($frase === '' || $metrica === '' || ! in_array($metrica, self::METRICAS, true)) {
                continue;
            }
            if (! str_contains($t, $frase)) {
                continue;
            }
            $len = mb_strlen($frase);
            if ($len > $mejorLen) {
                $mejor = $metrica;
                $mejorLen = $len;
            }
        }

        return $mejor;
    }

    /**
     * @return list<array{frase: string, metrica: string}>
     */
    public static function editables(): array
    {
        if (! self::tieneColumnaMetrica()) {
            return [];
        }

        return Cache::remember(self::claveCache(), 600, static function (): array {
            return ConsultaInteligenteNlSinonimo::query()
                ->where('activo', true)
                ->whereNotNull('metrica')
                ->where('metrica', '!=', '')
                ->orderBy('prioridad')
                ->get(['frase', 'metrica'])
                ->map(static fn (ConsultaInteligenteNlSinonimo $s) => [
                    'frase' => mb_strtolower(trim((string) $s->frase)),
                    'metrica' => (string) $s->metrica,
                ])
                ->filter(static fn (array $r) => $r['frase'] !== '' && $r['metrica'] !== '')
                ->values()
                ->all();
        });
    }

    public static function olvidarCache(): void
    {
        Cache::forget(self::claveCache());
    }

    public static function tieneColumnaMetrica(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_nl_sinonimo')
                && Schema::hasColumn('consulta_inteligente_nl_sinonimo', 'metrica');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function claveCache(): string
    {
        return generaKey('ci-nl-sinonimos-metrica');
    }
}
