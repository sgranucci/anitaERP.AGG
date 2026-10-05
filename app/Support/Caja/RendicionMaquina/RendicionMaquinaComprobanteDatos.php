<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\RendicionMaquina;

/**
 * Contenido del comprobante de una rendición de máquinas (PDF y Excel).
 */
final class RendicionMaquinaComprobanteDatos
{
    /**
     * @return array{
     *     codigo: string,
     *     nro_oper_anita: int|null,
     *     empresa: string,
     *     fecha: string,
     *     turno: string,
     *     turno_codigo: string,
     *     estado: string,
     *     supervisor: string,
     *     cajero: string,
     *     auxiliar: string,
     *     registro: string,
     *     totales: list<array{etiqueta: string, valor: float, destacar: bool}>,
     *     principales: list<array{etiqueta: string, valor: float}>,
     *     valores: list<array{codigo: string, cuenta: string, monto: float}>,
     *     total_valores: float,
     *     gastos: list<array{codigo: string, concepto: string, monto: float}>,
     *     total_gastos: float,
     *     observacion: string
     * }
     */
    public static function armar(RendicionMaquina $rendicion): array
    {
        $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
        $calcVars = is_array($rendicion->calc_json['variables'] ?? null) ? $rendicion->calc_json['variables'] : [];

        $inp = static function (string $k) use ($inputs): float {
            return (float) ($inputs[$k] ?? $inputs['inputs.'.$k] ?? 0);
        };
        $calc = static function (string $k) use ($calcVars): float {
            return (float) ($calcVars[$k] ?? $calcVars['calc.'.$k] ?? 0);
        };

        $fondoFijo = $calc('fondo_fijo');
        if (abs($fondoFijo) < 0.00001) {
            $fondoFijo = (float) $rendicion->fondo_inicial + $calc('comprobante');
        }

        $dropBruto = $inp('drop_billete_bruto');
        $dropRodilloBruto = $dropBruto ?: ($inp('drop_billete') + $inp('impuesto_drop'));
        $dropNetoCalc = $calc('drop_bill_rodillo');
        $dropRodilloNeto = $dropNetoCalc ?: $inp('drop_billete');

        $totales = [
            ['etiqueta' => 'Fondo inicial', 'valor' => (float) $rendicion->fondo_inicial, 'destacar' => false],
            ['etiqueta' => 'Comprobante', 'valor' => $calc('comprobante'), 'destacar' => false],
            ['etiqueta' => 'Fondo fijo tesoro', 'valor' => $fondoFijo, 'destacar' => false],
            ['etiqueta' => 'Drop rodillo bruto', 'valor' => (float) $dropRodilloBruto, 'destacar' => false],
            ['etiqueta' => 'Impuesto drop', 'valor' => $inp('impuesto_drop'), 'destacar' => false],
            ['etiqueta' => 'Drop rodillo neto', 'valor' => (float) $dropRodilloNeto, 'destacar' => false],
            ['etiqueta' => 'Total ingreso', 'valor' => (float) $rendicion->total_ingreso, 'destacar' => false],
            ['etiqueta' => 'Total salida', 'valor' => (float) $rendicion->total_salida, 'destacar' => false],
            ['etiqueta' => 'Resultado turno', 'valor' => (float) $rendicion->resultado_turno, 'destacar' => true],
            ['etiqueta' => 'Fondo cierre', 'valor' => (float) $rendicion->fondo_cierre, 'destacar' => false],
            ['etiqueta' => 'Transferencia', 'valor' => (float) $rendicion->transferencia, 'destacar' => true],
        ];

        if ((string) $rendicion->turno === 'C') {
            $totales[] = ['etiqueta' => 'Dif. caja', 'valor' => (float) $rendicion->dif_caja, 'destacar' => false];
        }

        $totales[] = ['etiqueta' => 'WIN', 'valor' => self::win($inp, $calc), 'destacar' => true];

        $principales = [];
        foreach (self::conceptosPrincipales() as $clave => $etiqueta) {
            $valor = match ($clave) {
                'vale_rep_fondo' => $calc('vale_rep_fondo'),
                'deposito' => $calc('deposito') ?: $inp('deposito'),
                default => $inp($clave),
            };
            if (abs($valor) < 0.005) {
                continue;
            }
            $principales[] = ['etiqueta' => $etiqueta, 'valor' => $valor];
        }

        $valores = [];
        $totalValores = 0.0;
        foreach ($rendicion->valores as $valor) {
            $monto = (float) $valor->monto;
            $totalValores += $monto;
            $valores[] = [
                'codigo' => (string) ($valor->cuentacaja?->codigo ?? ''),
                'cuenta' => (string) ($valor->cuentacaja?->etiquetaOperaciones() ?? ''),
                'monto' => $monto,
            ];
        }

        $gastos = [];
        $totalGastos = 0.0;
        foreach ($rendicion->gastos as $gasto) {
            $monto = (float) $gasto->monto;
            if (abs($monto) < 0.005) {
                continue;
            }
            $totalGastos += $monto;
            $gastos[] = [
                'codigo' => (string) ($gasto->aperturaGasto?->codigo ?? ''),
                'concepto' => (string) ($gasto->aperturaGasto?->nombre ?? ''),
                'monto' => $monto,
            ];
        }

        $nro = $rendicion->nro_oper_anita;
        $nro = $nro === null || $nro === '' ? null : (int) $nro;

        return [
            'codigo' => (string) ($rendicion->codigo ?? ''),
            'nro_oper_anita' => $nro,
            'empresa' => (string) ($rendicion->empresa?->nombre ?? ''),
            'fecha' => optional($rendicion->fecha)->format('d/m/Y') ?: '',
            'turno' => (string) ($rendicion->turno_label ?? ''),
            'turno_codigo' => (string) ($rendicion->turno ?? ''),
            'estado' => (string) ($rendicion->estado_label ?? ''),
            'supervisor' => (string) ($rendicion->supervisorUsuario?->nombre ?: '—'),
            'cajero' => (string) ($rendicion->cajeroUsuario?->nombre ?: '—'),
            'auxiliar' => (string) ($rendicion->auxiliarUsuario?->nombre ?: '—'),
            'registro' => (string) ($rendicion->creoUsuario?->nombre ?: '—'),
            'totales' => $totales,
            'principales' => $principales,
            'valores' => $valores,
            'total_valores' => round($totalValores, 2),
            'gastos' => $gastos,
            'total_gastos' => round($totalGastos, 2),
            'observacion' => trim((string) ($rendicion->observacion ?? '')),
        ];
    }

    /**
     * @param  callable(string): float  $inp
     * @param  callable(string): float  $calc
     */
    public static function win(callable $inp, callable $calc): float
    {
        // Ventas ya netas: no restar impuesto_venta (evitar doble descuento).
        return round(
            $calc('drop_bill_rodillo') + $calc('drop_bill_ruleta')
            + $inp('dropqr_rodillo') + $inp('dropqr_ruleta')
            + $inp('venta_ficha') + $inp('venta_ruleta')
            - $inp('pago_manual')
            - $inp('tito')
            - $inp('tito_ruleta'),
            2
        );
    }

    /**
     * @return array<string, string>
     */
    public static function conceptosPrincipales(): array
    {
        return [
            'drop_billete' => 'Drop billete (neto)',
            'drop_ruleta' => 'Drop ruleta',
            'drop_bill_ant' => 'Drop rodillo anterior',
            'drop_rul_ant' => 'Drop ruleta anterior',
            'venta_ficha' => 'Venta fichas (slots)',
            'venta_ruleta' => 'Venta ruletas',
            'tito' => 'Tito rodillos',
            'tito_ruleta' => 'Tito ruletas',
            'pago_manual' => 'Pago manual',
            'salida_ruleta' => 'Salidas ruleta',
            'vale_rep_fondo' => 'Vale rep. fondo',
            'deposito' => 'Depósito',
            'sobrantes' => 'Sobrantes',
            'ticket_prom' => 'Ticket promocionales',
            'variacion_ff' => 'Variación FF',
            'pago_diferido' => 'Pago diferido',
            'impuesto_venta' => 'Impuesto venta',
            'impuesto_qr' => 'Impuesto QR',
            'impuesto_pago' => 'Impuesto / canje gastro',
        ];
    }
}
