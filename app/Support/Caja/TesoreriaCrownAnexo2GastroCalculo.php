<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;

/**
 * Orquesta GASTRO/HOTEL del Anexo II:
 * - Solo jornadas Crown ERP (rendiciones + cierres fiscales)
 * - Si faltan días del mes → aviso para abrir/importar (sin Anita live)
 * - Anulados = tot_cred del cierre (p-cierrefact acumula NC* de venta)
 */
final class TesoreriaCrownAnexo2GastroCalculo
{
    /**
     * @param  list<string>  $avisos
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float>,
     *     fuente: string
     * }
     */
    public static function seccion(TesoreriaCrownJornada $jornada, array &$avisos = []): array
    {
        $fechaYmd = $jornada->fecha->format('Y-m-d');
        $desdeYmd = $jornada->fecha->copy()->startOfMonth()->format('Y-m-d');

        $jornadasMes = TesoreriaCrownJornada::query()
            ->where('sala_id', (int) $jornada->sala_id)
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $fechaYmd)
            ->orderBy('fecha')
            ->get();

        $fechasErp = [];
        foreach ($jornadasMes as $j) {
            $fechasErp[] = (int) $j->fecha->format('Ymd');
        }
        $fechasErp = array_values(array_unique($fechasErp));
        $diasMes = (int) $jornada->fecha->day;
        if (count($fechasErp) < $diasMes) {
            $avisos[] = 'Gastronomía/hotel MTD incompleto: hay '.count($fechasErp)
                .' jornada(s) ERP de '.$diasMes.' día(s) del mes. Abrí o importá los días faltantes.';
        }

        $buckets = TesoreriaCrownAnexo2GastroErp::buckets($jornadasMes, $fechaYmd);
        $armado = TesoreriaCrownAnexo2GastroErp::armarFilas($buckets);

        if ($armado['filas'] === []) {
            $avisos[] = 'Sin movimiento de gastronomía/hotel para la fecha.';
        }

        return [
            'filas' => $armado['filas'],
            'totales' => $armado['totales'],
            'fuente' => 'erp',
        ];
    }
}
