<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

/**
 * Recorre el Flash del período: 1% máquinas (solo Win > 0) y bingo según empresa.
 * Totales = suma de días (nunca un total transcripto).
 */
final class CanonEntidadesCalculoSupport
{
    /**
     * @param  list<array<string, mixed>>  $dias
     * @return array<string, mixed>
     */
    public static function calcular(array $dias, string $regla): array
    {
        $filas = [];
        $canonMaq = 0.0;
        $canonBin = 0.0;
        $baseMaq = 0.0;
        $baseBingo = 0.0;
        $diasExcluidos = 0;
        $diasConFlash = 0;
        $pisoRestante = CanonEntidadesReglasSupport::PISO_BINGO_BIYEMAS;
        $escalonado = CanonEntidadesReglasSupport::esBingoEscalonado($regla);

        foreach ($dias as $dia) {
            $win = round((float) ($dia['win_electronico'] ?? 0), 2);
            $bingo = round((float) ($dia['ventas_bingo'] ?? 0), 2);
            $tieneFlash = ! empty($dia['tiene_flash']);
            if ($tieneFlash) {
                $diasConFlash++;
            }

            $excluidoMaq = ! $tieneFlash || $win <= 0;
            $canonMaqDia = 0.0;
            if (! $excluidoMaq) {
                $canonMaqDia = round($win * CanonEntidadesReglasSupport::ALICUOTA_MAQUINAS, 2);
                $canonMaq += $canonMaqDia;
                $baseMaq += $win;
            } elseif ($tieneFlash) {
                $diasExcluidos++;
            }

            $tramo2 = 0.0;
            $tramo325 = 0.0;
            if ($escalonado) {
                $tramo2 = min($bingo, max($pisoRestante, 0.0));
                $tramo325 = max($bingo - $tramo2, 0.0);
                $canonBinDia = round(
                    $tramo2 * CanonEntidadesReglasSupport::ALICUOTA_BINGO_BIYEMAS_TRAMO1
                    + $tramo325 * CanonEntidadesReglasSupport::ALICUOTA_BINGO_BIYEMAS_TRAMO2,
                    2
                );
                $pisoRestante = round($pisoRestante - $tramo2, 2);
            } else {
                $canonBinDia = round($bingo * CanonEntidadesReglasSupport::ALICUOTA_BINGO_PLANA, 2);
            }

            $canonBin += $canonBinDia;
            $baseBingo += $bingo;

            $fechaIso = (string) ($dia['fecha_iso'] ?? '');
            $filas[] = [
                'fecha' => (string) ($dia['fecha'] ?? self::isoADma($fechaIso)),
                'fecha_iso' => $fechaIso,
                'win_electronico' => $win,
                'ventas_bingo' => $bingo,
                'canon_maq' => $canonMaqDia,
                'canon_bin' => $canonBinDia,
                'canon_total' => round($canonMaqDia + $canonBinDia, 2),
                'excluido_maq' => $excluidoMaq,
                'tiene_flash' => $tieneFlash,
                'bingo_tramo_2' => round($tramo2, 2),
                'bingo_tramo_325' => round($tramo325, 2),
            ];
        }

        $canonMaq = round($canonMaq, 2);
        $canonBin = round($canonBin, 2);

        return [
            'filas' => $filas,
            'totales' => [
                'base_maq' => round($baseMaq, 2),
                'base_bingo' => round($baseBingo, 2),
                'canon_maq' => $canonMaq,
                'canon_bin' => $canonBin,
                'canon_total' => round($canonMaq + $canonBin, 2),
                'dias_rango' => count($filas),
                'dias_con_flash' => $diasConFlash,
                'dias_excluidos_maq' => $diasExcluidos,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public static function anexarHaberDiario(array $filas, array $movimientos): array
    {
        $porFecha = [];
        foreach ($movimientos as $mov) {
            $iso = (string) ($mov['fecha'] ?? '');
            if (strlen($iso) > 10) {
                $iso = substr($iso, 0, 10);
            }
            if ($iso === '') {
                continue;
            }
            if (! isset($porFecha[$iso])) {
                $porFecha[$iso] = ['maq' => 0.0, 'bin' => 0.0];
            }
            $tipo = strtoupper(trim((string) ($mov['tipo'] ?? '')));
            $haber = (float) ($mov['haber'] ?? 0);
            if ($tipo === 'MAQ') {
                $porFecha[$iso]['maq'] += $haber;
            } elseif ($tipo === 'BIN') {
                $porFecha[$iso]['bin'] += $haber;
            }
        }

        foreach ($filas as &$fila) {
            $iso = (string) ($fila['fecha_iso'] ?? '');
            $haberMaq = round((float) ($porFecha[$iso]['maq'] ?? 0), 2);
            $haberBin = round((float) ($porFecha[$iso]['bin'] ?? 0), 2);
            $fila['haber_maq'] = $haberMaq;
            $fila['haber_bin'] = $haberBin;
            $fila['haber_total'] = round($haberMaq + $haberBin, 2);
            $fila['dif_dia'] = round((float) ($fila['canon_total'] ?? 0) - $haberMaq - $haberBin, 2);
        }
        unset($fila);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filasFlash
     * @return list<array<string, mixed>>
     */
    public static function diasDesdeFlashContable(array $filasFlash, int $empresaId): array
    {
        $dias = [];
        foreach ($filasFlash as $fila) {
            $m = $fila['empresas'][$empresaId] ?? [];
            $dias[] = [
                'fecha' => (string) ($fila['fecha'] ?? ''),
                'fecha_iso' => (string) ($fila['fecha_iso'] ?? ''),
                'win_electronico' => (float) ($m['win_electronico'] ?? 0),
                'ventas_bingo' => (float) ($m['ventas_bingo'] ?? 0),
                'tiene_flash' => ! empty($m['tiene_flash']),
            ];
        }

        return $dias;
    }

    private static function isoADma(string $iso): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return $iso;
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
