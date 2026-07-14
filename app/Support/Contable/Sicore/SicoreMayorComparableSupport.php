<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Separa del mayor las líneas comparables con el archivo SICORE (generación de retención)
 * de pagos de la DDJJ, compensaciones y reclasificaciones que distorsionan el saldo.
 */
final class SicoreMayorComparableSupport
{
    /**
     * Detalles del mayor que no representan retención generada (no van al archivo).
     *
     * @var list<string>
     */
    private const PATRONES_EXCLUIDOS = [
        '/COMPENSACI[OÓ]N\s+SICORE/iu',
        '/\bSICORE\s*[12]Q\b/iu',
        '/\bSICORE\s+\d{1,2}[\/\-]\d{2,4}\b/iu',
        '/^SICORE[_\s]+SICORE/iu',
        '/\bPRESENTACI[OÓ]N\s+SICORE\b/iu',
        '/\bPAGO\s+SICORE\b/iu',
        '/\bDDJJ\s+SICORE\b/iu',
        '/^RECLA\b/iu',
        '/\bRECLASIF/iu',
    ];

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array{
     *     comparables: list<array<string, mixed>>,
     *     excluidos: list<array<string, mixed>>,
     *     total_comparable: float,
     *     total_excluido: float
     * }
     */
    public static function particionar(array $movimientos): array
    {
        $comparables = [];
        $excluidos = [];

        foreach ($movimientos as $mov) {
            $motivo = self::motivoExclusion((string) ($mov['detalle'] ?? ''));
            if ($motivo !== null) {
                $excluidos[] = array_merge($mov, [
                    'excluido_comparable' => true,
                    'motivo_exclusion' => $motivo,
                ]);
                continue;
            }

            $comparables[] = array_merge($mov, [
                'excluido_comparable' => false,
                'motivo_exclusion' => null,
            ]);
        }

        return [
            'comparables' => $comparables,
            'excluidos' => $excluidos,
            'total_comparable' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($comparables),
            'total_excluido' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($excluidos),
        ];
    }

    public static function motivoExclusion(string $detalle): ?string
    {
        $detalle = trim($detalle);
        if ($detalle === '') {
            return null;
        }

        foreach (self::PATRONES_EXCLUIDOS as $patron) {
            if (preg_match($patron, $detalle) === 1) {
                return self::etiquetaMotivo($patron, $detalle);
            }
        }

        return null;
    }

    public static function esComparable(string $detalle): bool
    {
        return self::motivoExclusion($detalle) === null;
    }

    private static function etiquetaMotivo(string $patron, string $detalle): string
    {
        $upper = mb_strtoupper($detalle, 'UTF-8');

        if (str_contains($patron, 'COMPENSACI')) {
            return 'compensacion_sicore';
        }
        if (str_contains($patron, '[12]Q') || str_contains($patron, 'PRESENTACI') || str_contains($patron, 'PAGO') || str_contains($patron, 'DDJJ')) {
            return 'pago_sicore';
        }
        if (str_contains($patron, 'RECLA')) {
            return 'reclasificacion';
        }
        if (str_contains($upper, 'SICORE')) {
            return 'pago_sicore';
        }

        return 'excluido';
    }
}
