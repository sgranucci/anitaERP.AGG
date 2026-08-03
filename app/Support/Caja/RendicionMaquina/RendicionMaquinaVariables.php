<?php

namespace App\Support\Caja\RendicionMaquina;

/**
 * Contrato de variables del motor de cálculo.
 *
 * Namespaces:
 * - meta.*     cabecera / flags
 * - inputs.*   WIGOS + manuales (ya normalizados a pesos)
 * - valores.*  arqueo cuentacaja (uso rendición)
 * - gastos.*   apertura de gastos
 * - prev.*     lecturas de turnos / remesas previas
 * - calc.*     resultados del pipeline (también sembrados por orquestador)
 */
final class RendicionMaquinaVariables
{
    public const USO_CUENTACAJA_NOMBRE = 'Rendición de máquinas';

    /** @var list<string> */
    public const META = [
        'meta.turno',
        'meta.es_maniana',
        'meta.es_tarde',
        'meta.es_noche',
        'meta.es_completo',
        'meta.empresa_id',
        'meta.fecha_ymd',
        'meta.modo_wigos',
    ];

    /** @var list<string> */
    public const INPUTS = [
        'inputs.fondo_inicial',
        'inputs.drop_billete',
        'inputs.drop_billete_bruto',
        'inputs.drop_ruleta',
        'inputs.drop_bill_ant',
        'inputs.drop_rul_ant',
        'inputs.dropem_rodillo',
        'inputs.dropem_ruleta',
        'inputs.dropqr_rodillo',
        'inputs.dropqr_ruleta',
        'inputs.venta_ficha',
        'inputs.venta_ruleta',
        'inputs.tito',
        'inputs.tito_ruleta',
        'inputs.hopper',
        'inputs.salida_ruleta',
        'inputs.pago_manual',
        'inputs.sobrantes',
        // Compat: el depósito real es calc.deposito (D25); inputs.deposito se espeja al guardar
        'inputs.deposito',
        'inputs.vales',
        'inputs.reintegros',
        'inputs.vta_ant_gastro',
        'inputs.variacion_ff',
        'inputs.pago_diferido',
        'inputs.impuesto_drop',
        'inputs.impuesto_venta',
        'inputs.impuesto_qr',
        'inputs.impuesto_pago',
        'inputs.vale_anterior',
        'inputs.billem_rodillo',
        'inputs.billem_ruleta',
    ];

    /** @var list<string> */
    public const VALORES = [
        'valores.total',
        'valores.total_efectivo',
        'valores.total_qr',
        'valores.total_divisa',
        'valores.total_no_divisa',
    ];

    /** @var list<string> */
    public const GASTOS = [
        'gastos.total',
    ];

    /** @var list<string> */
    public const PREV = [
        'prev.fondo_cierre',
        'prev.transferencia',
        'prev.impuesto_drop_dia_ant',
    ];

    /** Sembrados por orquestador antes del AST (no son fórmulas puras). */
    /** @var list<string> */
    public const CALC_ORQUESTADOR = [
        'calc.comprobante',
        'calc.vale_rep_fondo',
    ];

    /** Destinos del pipeline AST (seed canónico). */
    /** @var list<string> */
    public const CALC_PIPELINE = [
        'calc.drop_bill_rodillo',
        'calc.drop_bill_ruleta',
        'calc.entrada_ruleta',
        'calc.venta_ficha',
        'calc.sobrante_supervisor',
        'calc.fondo_fijo',
        'calc.total_ingreso',
        'calc.tito_rodillo',
        'calc.tito_ruleta',
        'calc.deposito',
        'calc.deposito_efectivo',
        'calc.deposito_pesos',
        'calc.total_salida',
        'calc.resultado_turno',
        'calc.comprobante_cierre',
        'calc.fondo_cierre',
        'calc.transferencia',
        'calc.saldo_ingreso',
        'calc.resultado_rodillo',
        'calc.resultado_ruleta',
        'calc.dif_caja',
    ];

    /**
     * @return list<string>
     */
    public static function todas(): array
    {
        return array_values(array_unique(array_merge(
            self::META,
            self::INPUTS,
            self::VALORES,
            self::GASTOS,
            self::PREV,
            self::CALC_ORQUESTADOR,
            self::CALC_PIPELINE,
        )));
    }

    /**
     * Snapshot vacío con ceros / defaults seguros.
     *
     * @return array<string, float|int|string>
     */
    public static function defaultsVacios(string $turno = RendicionMaquinaTurno::MANIANA): array
    {
        $turno = RendicionMaquinaTurno::normalizar($turno);
        $vars = [];
        foreach (self::todas() as $ruta) {
            $vars[$ruta] = 0.0;
        }
        $vars['meta.turno'] = $turno;
        $vars['meta.es_maniana'] = RendicionMaquinaTurno::esManiana($turno) ? 1 : 0;
        $vars['meta.es_tarde'] = $turno === RendicionMaquinaTurno::TARDE ? 1 : 0;
        $vars['meta.es_noche'] = $turno === RendicionMaquinaTurno::NOCHE ? 1 : 0;
        $vars['meta.es_completo'] = RendicionMaquinaTurno::esCompleto($turno) ? 1 : 0;
        $vars['meta.modo_wigos'] = RendicionMaquinaTurno::modoWigos($turno);
        $vars['meta.fecha_ymd'] = '';
        $vars['meta.empresa_id'] = 0;

        return $vars;
    }
}
