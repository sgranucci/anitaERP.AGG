<?php

namespace App\Support\Stock;

/**
 * Clasifica abreviaturas de tipotransaccion_stock para el reporte de control de inventario.
 *
 * Las cantidades en articulo_movimiento ya vienen con signo; se suman tal cual.
 */
final class ControlInventarioItemsClasificacionSupport
{
    public const BUCKET_RECEPCIONES = 'recepciones';

    public const BUCKET_DEVOLUCIONES = 'devoluciones';

    public const BUCKET_AJUSTES = 'ajustes';

    public const BUCKET_CONSUMOS = 'consumos';

    public const BUCKET_OTROS = 'otros';

    /**
     * @var array<string, list<string>>
     */
    private const BUCKETS = [
        self::BUCKET_RECEPCIONES => ['RCING'],
        self::BUCKET_DEVOLUCIONES => ['RCDEV'],
        self::BUCKET_AJUSTES => [
            'RCAJP', 'RCAJN', 'RCAJR',
            'AJE', 'AJS',
            'ENT', 'SAL',
            'NPUAL', 'NPUBJ',
            'ROT', 'SVTO', 'IRE', 'CANJE',
        ],
        self::BUCKET_CONSUMOS => [
            'AJCON', 'SVIAN', 'EIND', 'SAS',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function buckets(): array
    {
        return [
            self::BUCKET_RECEPCIONES,
            self::BUCKET_DEVOLUCIONES,
            self::BUCKET_AJUSTES,
            self::BUCKET_CONSUMOS,
            self::BUCKET_OTROS,
        ];
    }

    public static function bucketDeAbreviatura(?string $abreviatura): string
    {
        $abrev = strtoupper(trim((string) $abreviatura));
        if ($abrev === '') {
            return self::BUCKET_OTROS;
        }

        foreach (self::BUCKETS as $bucket => $abrevs) {
            if (in_array($abrev, $abrevs, true)) {
                return $bucket;
            }
        }

        return self::BUCKET_OTROS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function mapaBuckets(): array
    {
        return self::BUCKETS;
    }

    /**
     * @param  list<string>  $abreviaturasEncontradas
     * @return list<string>
     */
    public static function abreviaturasEnOtros(array $abreviaturasEncontradas): array
    {
        $conocidas = [];
        foreach (self::BUCKETS as $abrevs) {
            foreach ($abrevs as $a) {
                $conocidas[$a] = true;
            }
        }

        $out = [];
        foreach ($abreviaturasEncontradas as $abrev) {
            $abrev = strtoupper(trim($abrev));
            if ($abrev === '' || isset($conocidas[$abrev])) {
                continue;
            }
            $out[] = $abrev;
        }

        sort($out);

        return array_values(array_unique($out));
    }
}
