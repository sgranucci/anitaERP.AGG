<?php

namespace App\Support\Caja;

/**
 * Anexo I como l-anexo1new.c: Concepto | Fecha | Débito | Crédito | Saldo.
 *
 * Parte del mismo cálculo que l-anexo1.c (día / mes / anual) y lo arma
 * como libro de caja del día: ingresos en débito, egresos en crédito,
 * con saldo corrido.
 */
final class TesoreriaCrownAnexo1NewCalculo
{
    /**
     * @return array{
     *     filas: list<array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>,
     *     variante: string
     * }
     */
    public static function delDia(string $fechaYmd): array
    {
        $clasico = TesoreriaCrownAnexo1Calculo::delDia($fechaYmd);
        $fechaVisible = substr($fechaYmd, 8, 2).'/'.substr($fechaYmd, 5, 2).'/'.substr($fechaYmd, 0, 4);
        $saldo = 0.0;
        $seccion = '';
        $filas = [];

        foreach ($clasico['filas'] as $fila) {
            if ($fila['seccion']) {
                $seccion = mb_strtoupper(trim((string) $fila['concepto']));
                $filas[] = [
                    'concepto' => $fila['concepto'],
                    'fecha' => null,
                    'debito' => null,
                    'credito' => null,
                    'saldo' => null,
                    'total' => false,
                    'seccion' => true,
                ];

                continue;
            }

            $importe = $fila['dia'];
            if ($importe === null || $importe === '') {
                continue;
            }
            $monto = round((float) $importe, 2);
            $esEgreso = $seccion === 'EGRESOS'
                || str_contains(mb_strtolower((string) $fila['concepto']), 'diferencia')
                || (
                    $seccion === 'CAJA'
                    && str_contains(mb_strtolower((string) $fila['concepto']), 'caja final')
                    && ! str_contains(mb_strtolower((string) $fila['concepto']), 'inicial')
                );

            if ($fila['total'] && $seccion !== 'CAJA') {
                $filas[] = [
                    'concepto' => $fila['concepto'],
                    'fecha' => $fechaVisible,
                    'debito' => $esEgreso ? null : $monto,
                    'credito' => $esEgreso ? $monto : null,
                    'saldo' => $saldo,
                    'total' => true,
                    'seccion' => false,
                ];

                continue;
            }

            if ($esEgreso) {
                $saldo -= $monto;
                $filas[] = [
                    'concepto' => $fila['concepto'],
                    'fecha' => $fechaVisible,
                    'debito' => null,
                    'credito' => $monto,
                    'saldo' => round($saldo, 2),
                    'total' => (bool) $fila['total'],
                    'seccion' => false,
                ];
            } else {
                $saldo += $monto;
                $filas[] = [
                    'concepto' => $fila['concepto'],
                    'fecha' => $fechaVisible,
                    'debito' => $monto,
                    'credito' => null,
                    'saldo' => round($saldo, 2),
                    'total' => (bool) $fila['total'],
                    'seccion' => false,
                ];
            }
        }

        return [
            'filas' => $filas,
            'cerrado' => (bool) $clasico['cerrado'],
            'avisos' => $clasico['avisos'],
            'variante' => 'nuevo',
        ];
    }
}
