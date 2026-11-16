<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Services\Contable\ConciliacionBancariaService;
use Carbon\Carbon;

/**
 * Forecast liviano desde uncleared (aging + buckets) para tesorería.
 */
final class ConciliacionBancariaForecastSupport
{
    /**
     * @return array{
     *   fecha_corte: string,
     *   ventanas: array<string, array{n: int, importe: float}>,
     *   total_importe: float,
     *   total_n: int
     * }
     */
    public static function desdeAging(
        ConciliacionBancariaService $service,
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
    ): array {
        $aging = $service->agingPendientes($empresaId, $cuentacajaId, $mes, $anio);
        $ventanas = [
            '0-7' => ['n' => 0, 'importe' => 0.0],
            '8-30' => ['n' => 0, 'importe' => 0.0],
            '31-60' => ['n' => 0, 'importe' => 0.0],
            '61+' => ['n' => 0, 'importe' => 0.0],
        ];
        $totalImp = 0.0;
        $totalN = 0;
        foreach ($aging['filas'] ?? [] as $fila) {
            // Solo banco uncleared impacta cash out/in inmediato; contable ya está en libros.
            if (strtolower((string) ($fila['lado'] ?? '')) !== 'banco') {
                continue;
            }
            $bucket = (string) ($fila['bucket'] ?? '61+');
            if (! isset($ventanas[$bucket])) {
                $bucket = '61+';
            }
            $imp = (float) ($fila['importe'] ?? 0);
            $ventanas[$bucket]['n']++;
            $ventanas[$bucket]['importe'] += $imp;
            $totalImp += $imp;
            $totalN++;
        }

        foreach ($ventanas as $k => $v) {
            $ventanas[$k]['importe'] = round($v['importe'], 2);
        }

        return [
            'fecha_corte' => Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString(),
            'ventanas' => $ventanas,
            'total_importe' => round($totalImp, 2),
            'total_n' => $totalN,
            'nota' => 'Uncleared banco: impacto estimado en posición (no es cashflow formal).',
        ];
    }
}
