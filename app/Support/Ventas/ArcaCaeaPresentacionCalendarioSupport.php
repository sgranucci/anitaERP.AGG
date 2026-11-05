<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Configuracion\Feriado;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Día de la presentación automática: el primer día hábil después de que cierra la quincena.
 * La quincena 1 cierra el 15. La quincena 2 cierra el último día del mes.
 * Hábil: lunes a viernes que no esté en la tabla feriado.
 */
final class ArcaCaeaPresentacionCalendarioSupport
{
    /**
     * @param  list<string>|null  $feriadosYmd  Y-m-d. null lee la tabla feriado.
     * @return list<array{periodo: int, orden: int}>
     */
    public static function quincenasDelDia(CarbonInterface $hoy, ?array $feriadosYmd = null): array
    {
        $hoyYmd = Carbon::parse($hoy)->toDateString();

        return array_values(array_map(
            static fn (array $q): array => ['periodo' => $q['periodo'], 'orden' => $q['orden']],
            array_filter(
                self::quincenasParaPresentar($hoy, $feriadosYmd),
                static fn (array $q): bool => $q['dia'] === $hoyYmd,
            ),
        ));
    }

    /**
     * Quincenas de este mes y el anterior cuyo día de presentación ya llegó.
     * La de hoy y las que se pasaron (el cron no corrió, o el día era feriado y nadie reintentó).
     * La quincena todavía abierta no entra: su día hábil es posterior a hoy.
     *
     * @param  list<string>|null  $feriadosYmd  Y-m-d. null lee la tabla feriado.
     * @return list<array{periodo: int, orden: int, dia: string}>
     */
    public static function quincenasParaPresentar(CarbonInterface $hoy, ?array $feriadosYmd = null): array
    {
        $hoy = Carbon::parse($hoy)->startOfDay();
        $feriados = $feriadosYmd ?? self::feriadosEntre($hoy->copy()->subDays(70), $hoy->copy()->addDay());

        $out = [];
        foreach ([-1, 0] as $offsetMes) {
            $ref = $hoy->copy()->addMonths($offsetMes);
            $periodo = (int) $ref->format('Ym');
            foreach ([1, 2] as $orden) {
                $fin = CaeaQuincenaSupport::fechasQuincena($periodo, $orden)['hasta']->copy()->startOfDay();
                $dia = self::primerDiaHabilSiguiente($fin, $feriados);
                if ($dia->greaterThan($hoy)) {
                    continue;
                }
                $out[] = [
                    'periodo' => $periodo,
                    'orden' => $orden,
                    'dia' => $dia->toDateString(),
                ];
            }
        }

        usort(
            $out,
            static fn (array $a, array $b): int => [$a['dia'], $a['periodo'], $a['orden']]
                <=> [$b['dia'], $b['periodo'], $b['orden']],
        );

        return $out;
    }

    /**
     * @param  list<string>  $feriadosYmd
     */
    public static function primerDiaHabilSiguiente(CarbonInterface $fin, array $feriadosYmd = []): Carbon
    {
        $marca = [];
        foreach ($feriadosYmd as $fecha) {
            $marca[(string) $fecha] = true;
        }

        $dia = Carbon::parse($fin)->startOfDay()->addDay();
        while ($dia->isWeekend() || isset($marca[$dia->toDateString()])) {
            $dia->addDay();
        }

        return $dia;
    }

    /**
     * @return list<string>
     */
    private static function feriadosEntre(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return Feriado::query()
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->pluck('fecha')
            ->map(static function ($fecha): string {
                return Carbon::parse($fecha)->toDateString();
            })
            ->all();
    }
}
