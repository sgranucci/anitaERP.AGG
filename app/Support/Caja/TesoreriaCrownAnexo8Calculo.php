<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;

/**
 * Anexo VIII / l-estboveda.c: estado de bóveda al cierre del día.
 * Denominaciones con cantidad y monto, agrupadas por tipo de valor.
 */
final class TesoreriaCrownAnexo8Calculo
{
    /**
     * @return array{
     *     lugares: list<array{lugar: string, grupos: list<array{titulo: string, filas: list<array{denominacion: string, cantidad: float, monto: float}>, monto: float}>, total: float}>,
     *     total: float,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $lugares = TesoreriaCrownBovedaSupport::lugares((int) $jornada->sala_id)
            ->filter(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');

        $out = [];
        $totalGeneral = 0.0;
        foreach ($lugares as $lugar) {
            $gruposRaw = TesoreriaCrownBovedaSupport::saldoAgrupado(
                (int) $jornada->sala_id,
                TesoreriaCrownBovedaSupport::saldoCantidades((int) $lugar->id, $fecha)
            );
            $grupos = [];
            $totalLugar = 0.0;
            foreach ($gruposRaw as $grupo) {
                $filas = [];
                foreach ($grupo['filas'] as $fila) {
                    $filas[] = [
                        'denominacion' => trim((string) ($fila['valor']->descripcion ?? '').' '.(string) ($fila['valor']->denominacion ?? '')),
                        'cantidad' => (float) $fila['cantidad'],
                        'monto' => (float) $fila['monto'],
                    ];
                }
                $grupos[] = [
                    'titulo' => (string) $grupo['titulo'],
                    'filas' => $filas,
                    'monto' => (float) $grupo['monto'],
                ];
                $totalLugar = round($totalLugar + (float) $grupo['monto'], 2);
            }
            $out[] = [
                'lugar' => (string) $lugar->codigo.' — '.(string) $lugar->nombre,
                'grupos' => $grupos,
                'total' => $totalLugar,
            ];
            $totalGeneral = round($totalGeneral + $totalLugar, 2);
        }

        return [
            'lugares' => $out,
            'total' => $totalGeneral,
            'avisos' => $out === [] || $totalGeneral == 0.0
                ? ['No hay saldo de bóveda para esta fecha.']
                : [],
        ];
    }
}
