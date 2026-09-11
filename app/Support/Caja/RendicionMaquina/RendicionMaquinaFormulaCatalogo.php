<?php

namespace App\Support\Caja\RendicionMaquina;

/**
 * Catálogo canónico de fórmulas (traducción limpia del C, sin parches por fecha).
 *
 * Se usa como seed de BD y como fallback in-memory para tests / bootstrap.
 *
 * @phpstan-type FormulaDef array{
 *   codigo: string,
 *   destino: string,
 *   expresion: string,
 *   seccion: string,
 *   orden: int,
 *   activo: bool,
 *   solo_completo?: bool,
 *   detalle: string
 * }
 */
final class RendicionMaquinaFormulaCatalogo
{
    public const VERSION = 4;

    /**
     * @return list<FormulaDef>
     */
    public static function canonicos(): array
    {
        return [
            [
                'codigo' => 'B10',
                'destino' => 'calc.drop_bill_rodillo',
                // inputs.drop_billete ya viene neto (bruto WIGOS − impuesto); bruto en drop_billete_bruto
                'expresion' => 'inputs.drop_billete',
                'seccion' => 'prep',
                'orden' => 10,
                'activo' => true,
                'detalle' => 'Drop billetes rodillo neto (paridad Anita dr_bill_rod)',
            ],
            [
                'codigo' => 'B20',
                'destino' => 'calc.drop_bill_ruleta',
                'expresion' => 'inputs.drop_ruleta',
                'seccion' => 'prep',
                'orden' => 20,
                'activo' => true,
                'detalle' => 'Drop billetes ruleta',
            ],
            [
                'codigo' => 'B30',
                'destino' => 'calc.entrada_ruleta',
                'expresion' => 'inputs.venta_ruleta',
                'seccion' => 'prep',
                'orden' => 30,
                'activo' => true,
                'detalle' => 'Entrada / venta ruleta',
            ],
            [
                'codigo' => 'B40',
                'destino' => 'calc.venta_ficha',
                'expresion' => 'inputs.venta_ficha + inputs.hopper',
                'seccion' => 'prep',
                'orden' => 40,
                'activo' => true,
                'detalle' => 'Venta de fichas + hopper (paridad Anita)',
            ],
            [
                'codigo' => 'B50',
                'destino' => 'calc.sobrante_supervisor',
                'expresion' => 'inputs.sobrantes',
                'seccion' => 'prep',
                'orden' => 50,
                'activo' => true,
                'detalle' => 'Sobrante supervisor',
            ],
            [
                'codigo' => 'B60',
                'destino' => 'calc.fondo_fijo',
                'expresion' => 'inputs.fondo_inicial + calc.comprobante',
                'seccion' => 'fondo',
                'orden' => 60,
                'activo' => true,
                'detalle' => 'Fondo fijo tesoro',
            ],
            [
                'codigo' => 'C10',
                'destino' => 'calc.total_ingreso',
                'expresion' => 'calc.drop_bill_rodillo + calc.drop_bill_ruleta + inputs.dropem_rodillo + inputs.dropem_ruleta + inputs.dropqr_rodillo + inputs.dropqr_ruleta + calc.entrada_ruleta + calc.venta_ficha + calc.sobrante_supervisor',
                'seccion' => 'ingresos',
                'orden' => 100,
                'activo' => true,
                'detalle' => 'Total ingresos',
            ],
            [
                'codigo' => 'D10',
                'destino' => 'calc.tito_rodillo',
                'expresion' => 'inputs.tito',
                'seccion' => 'salidas',
                'orden' => 110,
                'activo' => true,
                'detalle' => 'Tito rodillos',
            ],
            [
                'codigo' => 'D20',
                'destino' => 'calc.tito_ruleta',
                'expresion' => 'inputs.tito_ruleta',
                'seccion' => 'salidas',
                'orden' => 120,
                'activo' => true,
                'detalle' => 'Tito ruletas',
            ],
            [
                // calcula_arqueo_maquina() en a-rendmaquina.c (sin sobrantes):
                // tot_valores(+cotiz divisa) + gastos + vta_ant_gastro
                // (vales/reintegros dejaron de cargarse a mano: van por Gastos)
                'codigo' => 'D25',
                'destino' => 'calc.deposito',
                'expresion' => 'valores.total + gastos.total + inputs.vta_ant_gastro',
                'seccion' => 'salidas',
                'orden' => 125,
                'activo' => true,
                'detalle' => 'Depósito calculado (valores en pesos: ME × cotización tesorería + gastos + vta ant. gastro)',
            ],
            [
                'codigo' => 'D30',
                'destino' => 'calc.deposito_efectivo',
                // El arqueo (calc.deposito) entra a salidas para que un ajuste WIGOS
                // (impuesto QR, drop, etc.) se cancele en la transferencia.
                'expresion' => 'calc.drop_bill_rodillo + calc.drop_bill_ruleta - calc.vale_rep_fondo + calc.deposito - inputs.sobrantes',
                'seccion' => 'salidas',
                'orden' => 130,
                'activo' => true,
                'detalle' => 'Depósito efectivo (drop − remesa + arqueo − sobrantes; también en C para recálculo con ajuste WIGOS)',
            ],
            [
                'codigo' => 'D40',
                'destino' => 'calc.deposito_pesos',
                'expresion' => 'valores.total_no_divisa',
                'seccion' => 'salidas',
                'orden' => 140,
                'activo' => true,
                'detalle' => 'Depósito pesos desde arqueo cuentacaja',
            ],
            [
                'codigo' => 'D50',
                'destino' => 'calc.total_salida',
                // Vale en D50 se cancela con −vale de D30; hace falta en C y en M/T/N
                // para que el arqueo ajustado (WIGOS) llegue a la transferencia.
                'expresion' => 'calc.tito_rodillo + calc.tito_ruleta + calc.vale_rep_fondo + inputs.salida_ruleta + inputs.pago_manual + calc.deposito_efectivo + inputs.hopper',
                'seccion' => 'salidas',
                'orden' => 150,
                'activo' => true,
                'detalle' => 'Total salidas (vale se cancela con D30; el arqueo entra por depósito efectivo)',
            ],
            [
                'codigo' => 'E10',
                'destino' => 'calc.resultado_turno',
                'expresion' => 'inputs.fondo_inicial + calc.comprobante + (inputs.variacion_ff > 0 ? inputs.variacion_ff : 0) + calc.total_ingreso - calc.total_salida - inputs.sobrantes',
                'seccion' => 'cierre',
                'orden' => 200,
                'activo' => true,
                'detalle' => 'Resultado del turno (recalcula con WIGOS/arqueo actuales, también en C)',
            ],
            [
                'codigo' => 'E20',
                'destino' => 'calc.comprobante_cierre',
                'expresion' => 'meta.es_maniana > 0 ? 0 : calc.comprobante',
                'seccion' => 'cierre',
                'orden' => 210,
                'activo' => true,
                'detalle' => 'Comprobante de cierre (0 en mañana)',
            ],
            [
                'codigo' => 'E30',
                'destino' => 'calc.fondo_cierre',
                'expresion' => 'calc.fondo_fijo + inputs.variacion_ff',
                'seccion' => 'cierre',
                'orden' => 220,
                'activo' => true,
                'detalle' => 'Fondo de cierre (fondo fijo + variación FF)',
            ],
            [
                'codigo' => 'E40',
                'destino' => 'calc.transferencia',
                // Semilla (0 en M/T/N; suma M+T+N en C) + residual con WIGOS/arqueo actuales.
                // Si el depósito acompaña el ajuste (p. ej. TotalCoin = drop QR + impuesto QR), el residual es 0.
                'expresion' => 'calc.transferencia + (calc.fondo_cierre - calc.resultado_turno - inputs.pago_diferido - inputs.impuesto_venta - inputs.impuesto_qr - inputs.impuesto_pago - (inputs.variacion_ff < 0 ? inputs.variacion_ff : 0))',
                'seccion' => 'cierre',
                'orden' => 230,
                'activo' => true,
                'detalle' => 'Transferencia M/T/N. En C el motor deja la semilla (suma M+T+N)',
            ],
            [
                'codigo' => 'E50',
                'destino' => 'calc.saldo_ingreso',
                'expresion' => 'inputs.fondo_inicial + calc.comprobante + (inputs.variacion_ff > 0 ? inputs.variacion_ff : 0) + calc.total_ingreso',
                'seccion' => 'cierre',
                'orden' => 240,
                'activo' => true,
                'detalle' => 'Saldo ingresos (display)',
            ],
            [
                'codigo' => 'F03',
                'destino' => 'calc.resultado_rodillo',
                'expresion' => 'inputs.venta_ficha - inputs.hopper + inputs.drop_bill_ant + inputs.billem_rodillo - inputs.tito - inputs.pago_manual',
                'seccion' => 'completo',
                'orden' => 300,
                'activo' => true,
                'solo_completo' => true,
                'detalle' => 'Resultado rodillo (turno C)',
            ],
            [
                'codigo' => 'F04',
                'destino' => 'calc.resultado_ruleta',
                'expresion' => 'inputs.venta_ruleta + inputs.drop_rul_ant - inputs.tito_ruleta - inputs.salida_ruleta + inputs.billem_ruleta',
                'seccion' => 'completo',
                'orden' => 310,
                'activo' => true,
                'solo_completo' => true,
                'detalle' => 'Resultado ruleta (turno C)',
            ],
            [
                'codigo' => 'F06',
                'destino' => 'calc.dif_caja',
                'expresion' => '(inputs.vale_anterior + calc.deposito - calc.transferencia) - (calc.resultado_rodillo + calc.resultado_ruleta) - prev.impuesto_drop_dia_ant + inputs.impuesto_drop',
                'seccion' => 'completo',
                'orden' => 320,
                'activo' => true,
                'solo_completo' => true,
                'detalle' => 'Diferencia de caja (turno C)',
            ],
        ];
    }
}
