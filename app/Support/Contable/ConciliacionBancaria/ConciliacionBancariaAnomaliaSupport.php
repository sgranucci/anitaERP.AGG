<?php

namespace App\Support\Contable\ConciliacionBancaria;

/**
 * Detecta anomalías y score de una corrida de conciliación bancaria (pares + pendientes).
 */
final class ConciliacionBancariaAnomaliaSupport
{
    /**
     * @param  array{
     *   pares_nuevos?: list<array{contable?: array<string,mixed>, banco?: array<string,mixed>, score?: int}>,
     *   pendientes_contables?: list<array<string,mixed>>,
     *   pendientes_banco?: list<array<string,mixed>>,
     *   diferencia?: float|int|string|null,
     *   suma_pendientes_contables?: float|int|null,
     *   suma_pendientes_banco?: float|int|null
     * }  $resultado
     * @return array{
     *   score: float,
     *   anomalias: list<array{codigo: string, severidad: string, mensaje: string, detalle?: array<string,mixed>}>,
     *   resumen: array<string, int|float>
     * }
     */
    public static function evaluar(array $resultado): array
    {
        $pares = is_array($resultado['pares_nuevos'] ?? null) ? $resultado['pares_nuevos'] : [];
        $pendC = is_array($resultado['pendientes_contables'] ?? null) ? $resultado['pendientes_contables'] : [];
        $pendOtros = is_array($resultado['pendientes_contables_otros'] ?? null)
            ? $resultado['pendientes_contables_otros']
            : [];
        $pendB = is_array($resultado['pendientes_banco'] ?? null) ? $resultado['pendientes_banco'] : [];
        $diferencia = abs((float) ($resultado['diferencia'] ?? 0));
        $anomalias = [];

        $scoresBajos = 0;
        foreach ($pares as $par) {
            $scorePar = (int) ($par['score'] ?? 0);
            if ($scorePar > 0 && $scorePar < 25) {
                $scoresBajos++;
                if (count($anomalias) < 25) {
                    $anomalias[] = [
                        'codigo' => 'par_score_bajo',
                        'severidad' => 'media',
                        'mensaje' => 'Par emparejado con score bajo ('.$scorePar.'): revisar referencia/fecha.',
                        'detalle' => [
                            'score' => $scorePar,
                            'hash_contable' => $par['contable']['hash'] ?? null,
                            'hash_banco' => $par['banco']['hash'] ?? null,
                        ],
                    ];
                }
            }
        }

        $umbralGrande = (float) config('conciliacion_bancaria.anomalia_importe_grande', 50000);
        foreach ($pendC as $mov) {
            $imp = abs(ConciliacionBancariaHashSupport::importeFirmadoContable($mov));
            if ($imp >= $umbralGrande) {
                $anomalias[] = [
                    'codigo' => 'pendiente_contable_grande',
                    'severidad' => 'alta',
                    'mensaje' => 'Pendiente contable de monto alto ('.number_format($imp, 2, ',', '.').').',
                    'detalle' => [
                        'importe' => $imp,
                        'fecha' => $mov['fecha'] ?? null,
                        'hash' => $mov['hash'] ?? null,
                    ],
                ];
            }
        }
        foreach ($pendB as $mov) {
            $imp = abs(ConciliacionBancariaHashSupport::importeFirmadoBanco($mov));
            if ($imp >= $umbralGrande) {
                $anomalias[] = [
                    'codigo' => 'pendiente_banco_grande',
                    'severidad' => 'alta',
                    'mensaje' => 'Pendiente banco de monto alto ('.number_format($imp, 2, ',', '.').').',
                    'detalle' => [
                        'importe' => $imp,
                        'fecha' => $mov['process_date'] ?? null,
                        'hash' => $mov['hash'] ?? null,
                    ],
                ];
            }
        }

        $near = self::paresCandidatosCercanos($pendC, $pendB);
        foreach ($near as $candidato) {
            $anomalias[] = [
                'codigo' => 'candidato_cercano',
                'severidad' => 'media',
                'mensaje' => $candidato['mensaje'],
                'detalle' => $candidato['detalle'],
            ];
        }

        if ($diferencia >= (float) config('conciliacion_bancaria.anomalia_diferencia_saldo', 1.0)) {
            $anomalias[] = [
                'codigo' => 'diferencia_saldo',
                'severidad' => $diferencia >= 1000 ? 'alta' : 'media',
                'mensaje' => 'Diferencia de saldo ajustado: '.number_format($diferencia, 2, ',', '.').'.',
                'detalle' => ['diferencia' => $diferencia],
            ];
        }

        $sumaOtros = abs((float) ($resultado['suma_pendientes_contables_otros'] ?? 0));
        if (count($pendOtros) > 0 && $sumaOtros >= (float) config('conciliacion_bancaria.anomalia_importe_grande', 50000)) {
            $anomalias[] = [
                'codigo' => 'pendientes_contables_sin_cobertura_ib',
                'severidad' => 'media',
                'mensaje' => count($pendOtros).' pendientes contables no-cheque (posible sin cobertura Interbanking o match N:1): '
                    .number_format($sumaOtros, 2, ',', '.').'.',
                'detalle' => [
                    'cantidad' => count($pendOtros),
                    'suma' => $sumaOtros,
                ],
            ];
        }

        $excelCmp = is_array($resultado['excel_comparacion'] ?? null) ? $resultado['excel_comparacion'] : null;
        if ($excelCmp !== null && empty($excelCmp['ok'])) {
            $deltas = [];
            foreach ($excelCmp['filas'] ?? [] as $fila) {
                if (! empty($fila['ok'])) {
                    continue;
                }
                $deltas[] = ($fila['concepto'] ?? '?').' Δ '
                    .number_format((float) ($fila['delta'] ?? 0), 2, ',', '.');
            }
            $anomalias[] = [
                'codigo' => 'desvio_vs_excel_contaduria',
                'severidad' => 'alta',
                'mensaje' => 'Carátula ERP desvío vs Excel Contaduría: '.implode('; ', array_slice($deltas, 0, 4)),
                'detalle' => ['filas' => $excelCmp['filas'] ?? []],
            ];
        }

        $anomalias = array_slice($anomalias, 0, 40);

        $totalMov = max(1, count($pares) + count($pendC) + count($pendB));
        $ratioPares = count($pares) / $totalMov;
        $penalAnom = min(0.45, count($anomalias) * 0.04);
        $penalDiff = $diferencia >= 1 ? min(0.25, log10($diferencia + 1) / 10) : 0;
        $score = max(0.0, min(1.0, round(($ratioPares * 0.75) + (0.25 - $penalAnom - $penalDiff), 4)));

        return [
            'score' => $score,
            'anomalias' => $anomalias,
            'resumen' => [
                'pares_nuevos' => count($pares),
                'pares_score_bajo' => $scoresBajos,
                'pendientes_contables' => count($pendC),
                'pendientes_contables_otros' => count($pendOtros),
                'pendientes_banco' => count($pendB),
                'candidatos_cercanos' => count($near),
                'diferencia' => $diferencia,
                'anomalias' => count($anomalias),
            ],
        ];
    }

    /**
     * Busca pendientes contable↔banco con mismo importe (tolerancia) pero no emparejados.
     *
     * @param  list<array<string,mixed>>  $pendC
     * @param  list<array<string,mixed>>  $pendB
     * @return list<array{mensaje: string, detalle: array<string,mixed>}>
     */
    private static function paresCandidatosCercanos(array $pendC, array $pendB): array
    {
        $tol = (float) config('conciliacion_bancaria.tolerancia_importe', 0.05);
        $out = [];
        $usadosB = [];

        foreach ($pendC as $c) {
            $impC = ConciliacionBancariaHashSupport::importeFirmadoContable($c);
            if (abs($impC) < 0.005) {
                continue;
            }
            foreach ($pendB as $ib => $b) {
                if (isset($usadosB[$ib])) {
                    continue;
                }
                $impB = ConciliacionBancariaHashSupport::importeFirmadoBanco($b);
                if (abs($impC - $impB) > $tol) {
                    continue;
                }
                $usadosB[$ib] = true;
                $out[] = [
                    'mensaje' => 'Candidato no emparejado: mismo importe ('.number_format(abs($impC), 2, ',', '.').') en contable y banco.',
                    'detalle' => [
                        'importe' => abs($impC),
                        'hash_contable' => $c['hash'] ?? null,
                        'hash_banco' => $b['hash'] ?? null,
                        'fecha_contable' => $c['fecha'] ?? null,
                        'fecha_banco' => $b['process_date'] ?? null,
                    ],
                ];
                if (count($out) >= 15) {
                    return $out;
                }
                break;
            }
        }

        return $out;
    }
}
