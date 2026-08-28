<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use DateTimeImmutable;

/**
 * Rangos Ymd para lecturas Anita por período (pocas pasadas al bridge).
 */
final class LibroIvaDigitalAnitaPeriodoSupport
{
    /**
     * @return list<array{0: int, 1: int}>
     */
    public static function partirRangoYmd(int $desde, int $hasta, int $dias): array
    {
        $inicio = DateTimeImmutable::createFromFormat('Ymd', sprintf('%08d', $desde));
        $fin = DateTimeImmutable::createFromFormat('Ymd', sprintf('%08d', $hasta));
        if ($inicio === false || $fin === false || $dias < 1) {
            return [[$desde, $hasta]];
        }

        $rangos = [];
        $cursor = $inicio;
        while ($cursor <= $fin) {
            $finLote = $cursor->modify('+'.($dias - 1).' days');
            if ($finLote > $fin) {
                $finLote = $fin;
            }
            $rangos[] = [(int) $cursor->format('Ymd'), (int) $finLote->format('Ymd')];
            $cursor = $finLote->modify('+1 day');
        }

        return $rangos === [] ? [[$desde, $hasta]] : $rangos;
    }
}
