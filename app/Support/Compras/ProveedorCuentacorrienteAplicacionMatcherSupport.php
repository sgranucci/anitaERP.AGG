<?php

namespace App\Support\Compras;

/**
 * Motor de sugerencia y validación de aplicaciones CC proveedor (sin I/O).
 *
 * Créditos = NC / pagos a cuenta (saldo &gt; 0 ya en valor absoluto).
 * Deudas   = facturas / ND con saldo pendiente.
 */
final class ProveedorCuentacorrienteAplicacionMatcherSupport
{
    public const TOLERANCIA = 0.01;

    /**
     * FIFO por moneda: créditos más viejos contra deudas más vencidas.
     *
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $creditos
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $deudas
     * @return list<array{credito_id:int,deuda_id:int,monto:float}>
     */
    public static function sugerirFifo(array $creditos, array $deudas): array
    {
        $creditos = self::ordenarCreditos($creditos);
        $deudas = self::ordenarDeudas($deudas);

        $restanteCredito = [];
        foreach ($creditos as $c) {
            $restanteCredito[(int) $c['id']] = round(abs((float) $c['saldo']), 4);
        }
        $restanteDeuda = [];
        foreach ($deudas as $d) {
            $restanteDeuda[(int) $d['id']] = round(abs((float) $d['saldo']), 4);
        }

        $out = [];
        foreach ($creditos as $c) {
            $cid = (int) $c['id'];
            $moneda = (int) $c['moneda_id'];
            foreach ($deudas as $d) {
                if ((int) $d['moneda_id'] !== $moneda) {
                    continue;
                }
                if (isset($c['empresa_id'], $d['empresa_id'])
                    && (int) $c['empresa_id'] > 0
                    && (int) $d['empresa_id'] > 0
                    && (int) $c['empresa_id'] !== (int) $d['empresa_id']) {
                    continue;
                }
                $did = (int) $d['id'];
                $dispC = $restanteCredito[$cid] ?? 0.0;
                $dispD = $restanteDeuda[$did] ?? 0.0;
                if ($dispC < self::TOLERANCIA || $dispD < self::TOLERANCIA) {
                    continue;
                }
                $monto = round(min($dispC, $dispD), 4);
                if ($monto < self::TOLERANCIA) {
                    continue;
                }
                $out[] = [
                    'credito_id' => $cid,
                    'deuda_id' => $did,
                    'monto' => $monto,
                ];
                $restanteCredito[$cid] = round($dispC - $monto, 4);
                $restanteDeuda[$did] = round($dispD - $monto, 4);
                if (($restanteCredito[$cid] ?? 0) < self::TOLERANCIA) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Pareo 1:1 por importe exacto (misma moneda), sin fraccionar.
     *
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $creditos
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $deudas
     * @return list<array{credito_id:int,deuda_id:int,monto:float}>
     */
    public static function sugerirParearImportes(array $creditos, array $deudas): array
    {
        $creditos = self::ordenarCreditos($creditos);
        $deudas = self::ordenarDeudas($deudas);
        $usadosDeuda = [];
        $out = [];

        foreach ($creditos as $c) {
            $saldoC = round(abs((float) $c['saldo']), 4);
            if ($saldoC < self::TOLERANCIA) {
                continue;
            }
            foreach ($deudas as $d) {
                $did = (int) $d['id'];
                if (isset($usadosDeuda[$did])) {
                    continue;
                }
                if ((int) $d['moneda_id'] !== (int) $c['moneda_id']) {
                    continue;
                }
                if (isset($c['empresa_id'], $d['empresa_id'])
                    && (int) $c['empresa_id'] > 0
                    && (int) $d['empresa_id'] > 0
                    && (int) $c['empresa_id'] !== (int) $d['empresa_id']) {
                    continue;
                }
                $saldoD = round(abs((float) $d['saldo']), 4);
                if (abs($saldoC - $saldoD) >= self::TOLERANCIA) {
                    continue;
                }
                $out[] = [
                    'credito_id' => (int) $c['id'],
                    'deuda_id' => $did,
                    'monto' => $saldoC,
                ];
                $usadosDeuda[$did] = true;
                break;
            }
        }

        return $out;
    }

    /**
     * FIFO sobre el saldo que queda después de reservas manuales y omisiones.
     *
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string,empresa_id?:int}>  $creditos
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string,empresa_id?:int}>  $deudas
     * @param  list<array{credito_id:int,deuda_id:int,monto:float}>  $reservas
     * @param  list<int>  $omitirDeudaIds
     * @param  list<int>  $omitirCreditoIds
     * @return list<array{credito_id:int,deuda_id:int,monto:float}>
     */
    public static function sugerirFifoRestante(
        array $creditos,
        array $deudas,
        array $reservas = [],
        array $omitirDeudaIds = [],
        array $omitirCreditoIds = [],
    ): array {
        $omitirD = array_fill_keys(array_map('intval', $omitirDeudaIds), true);
        $omitirC = array_fill_keys(array_map('intval', $omitirCreditoIds), true);

        $consumoC = [];
        $consumoD = [];
        foreach ($reservas as $r) {
            $cid = (int) ($r['credito_id'] ?? 0);
            $did = (int) ($r['deuda_id'] ?? 0);
            $monto = round(abs((float) ($r['monto'] ?? 0)), 4);
            if ($cid > 0) {
                $consumoC[$cid] = round(($consumoC[$cid] ?? 0) + $monto, 4);
            }
            if ($did > 0) {
                $consumoD[$did] = round(($consumoD[$did] ?? 0) + $monto, 4);
            }
        }

        $creditosAdj = [];
        foreach ($creditos as $c) {
            $id = (int) $c['id'];
            if (isset($omitirC[$id])) {
                continue;
            }
            $saldo = round(abs((float) $c['saldo']) - ($consumoC[$id] ?? 0), 4);
            if ($saldo < self::TOLERANCIA) {
                continue;
            }
            $fila = $c;
            $fila['saldo'] = $saldo;
            $creditosAdj[] = $fila;
        }

        $deudasAdj = [];
        foreach ($deudas as $d) {
            $id = (int) $d['id'];
            if (isset($omitirD[$id])) {
                continue;
            }
            $saldo = round(abs((float) $d['saldo']) - ($consumoD[$id] ?? 0), 4);
            if ($saldo < self::TOLERANCIA) {
                continue;
            }
            $fila = $d;
            $fila['saldo'] = $saldo;
            $deudasAdj[] = $fila;
        }

        return self::sugerirFifo($creditosAdj, $deudasAdj);
    }

    /**
     * @param  array<int, array{id:int,saldo:float,moneda_id:int,empresa_id?:int,proveedor_id?:int,fecha:?string}>  $creditosById
     * @param  array<int, array{id:int,saldo:float,moneda_id:int,empresa_id?:int,proveedor_id?:int,fecha:?string}>  $deudasById
     * @param  list<array{credito_id:int,deuda_id:int,monto:float}>  $lineas
     * @return list<string>
     */
    public static function validarLineas(array $creditosById, array $deudasById, array $lineas, ?string $fechaAplicacion = null): array
    {
        $errores = [];
        if ($lineas === []) {
            return ['Indique al menos una línea a aplicar.'];
        }

        $consumoCredito = [];
        $consumoDeuda = [];

        foreach ($lineas as $i => $linea) {
            $n = $i + 1;
            $cid = (int) ($linea['credito_id'] ?? 0);
            $did = (int) ($linea['deuda_id'] ?? 0);
            $monto = round(abs((float) ($linea['monto'] ?? 0)), 4);

            if ($cid <= 0 || $did <= 0) {
                $errores[] = "Línea {$n}: crédito y deuda son obligatorios.";

                continue;
            }
            if ($cid === $did) {
                $errores[] = "Línea {$n}: no se puede aplicar un movimiento contra sí mismo.";

                continue;
            }
            if ($monto < self::TOLERANCIA) {
                $errores[] = "Línea {$n}: el monto a aplicar debe ser mayor a cero.";

                continue;
            }

            $credito = $creditosById[$cid] ?? null;
            $deuda = $deudasById[$did] ?? null;
            if ($credito === null) {
                $errores[] = "Línea {$n}: crédito #{$cid} no encontrado o sin saldo.";

                continue;
            }
            if ($deuda === null) {
                $errores[] = "Línea {$n}: deuda #{$did} no encontrada o sin saldo.";

                continue;
            }
            if ((int) ($credito['moneda_id'] ?? 0) !== (int) ($deuda['moneda_id'] ?? 0)) {
                $errores[] = "Línea {$n}: crédito y deuda deben estar en la misma moneda.";
            }
            if (isset($credito['empresa_id'], $deuda['empresa_id'])
                && (int) $credito['empresa_id'] !== (int) $deuda['empresa_id']) {
                $errores[] = "Línea {$n}: crédito y deuda deben ser de la misma empresa.";
            }
            if (isset($credito['proveedor_id'], $deuda['proveedor_id'])
                && (int) $credito['proveedor_id'] !== (int) $deuda['proveedor_id']) {
                $errores[] = "Línea {$n}: crédito y deuda deben ser del mismo proveedor.";
            }
            if ($fechaAplicacion) {
                $minFecha = self::fechaMaxima($credito['fecha'] ?? null, $deuda['fecha'] ?? null);
                if ($minFecha !== null && $fechaAplicacion < $minFecha) {
                    $errores[] = "Línea {$n}: la fecha de aplicación no puede ser anterior a los comprobantes ({$minFecha}).";
                }
            }

            $consumoCredito[$cid] = round(($consumoCredito[$cid] ?? 0) + $monto, 4);
            $consumoDeuda[$did] = round(($consumoDeuda[$did] ?? 0) + $monto, 4);
        }

        foreach ($consumoCredito as $cid => $usado) {
            $saldo = round(abs((float) ($creditosById[$cid]['saldo'] ?? 0)), 4);
            if ($usado - $saldo >= self::TOLERANCIA) {
                $errores[] = 'El crédito #'.$cid.' se aplica por '.$usado.' y solo tiene saldo '.$saldo.'.';
            }
        }
        foreach ($consumoDeuda as $did => $usado) {
            $saldo = round(abs((float) ($deudasById[$did]['saldo'] ?? 0)), 4);
            if ($usado - $saldo >= self::TOLERANCIA) {
                $errores[] = 'La deuda #'.$did.' se aplica por '.$usado.' y solo tiene saldo '.$saldo.'.';
            }
        }

        return $errores;
    }

    /**
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $filas
     * @return list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>
     */
    public static function ordenarCreditos(array $filas): array
    {
        usort($filas, static function ($a, $b) {
            $fa = (string) ($a['fecha'] ?? '');
            $fb = (string) ($b['fecha'] ?? '');
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        return $filas;
    }

    /**
     * @param  list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>  $filas
     * @return list<array{id:int,saldo:float,moneda_id:int,fecha:?string,vencimiento:?string}>
     */
    public static function ordenarDeudas(array $filas): array
    {
        usort($filas, static function ($a, $b) {
            $va = (string) ($a['vencimiento'] ?? $a['fecha'] ?? '9999-12-31');
            $vb = (string) ($b['vencimiento'] ?? $b['fecha'] ?? '9999-12-31');
            if ($va !== $vb) {
                return $va <=> $vb;
            }
            $fa = (string) ($a['fecha'] ?? '');
            $fb = (string) ($b['fecha'] ?? '');
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        return $filas;
    }

    private static function fechaMaxima(?string $a, ?string $b): ?string
    {
        $a = $a !== null && $a !== '' ? substr($a, 0, 10) : null;
        $b = $b !== null && $b !== '' ? substr($b, 0, 10) : null;
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return $a >= $b ? $a : $b;
    }
}
