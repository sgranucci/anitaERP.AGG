<?php

namespace App\Support\Contable;

/**
 * Catálogo de claves de cuentas contables automáticas del sistema (patás fijas por proceso).
 */
final class CuentaAutomaticaClaves
{
    public const RECEPCION_PROVISION_FACTURAS = 'recepcion.provision_facturas';

    public const RECEPCION_FACTURA_ANTICIPADA = 'recepcion.factura_anticipada';

    public const RECEPCION_ANTICIPO_BIENES_USO = 'recepcion.anticipo_bienes_uso';

    public const RECEPCION_PROVEEDORES_INTANGIBLE = 'recepcion.proveedores_intangible';

    public const CIERRE_WAITRY_VENTAS = 'cierre_waitry.ventas';

    public const CIERRE_WAITRY_IVA = 'cierre_waitry.iva';

    public const CIERRE_WAITRY_VENTAS_KIOSCO = 'cierre_waitry.ventas_kiosco';

    public const CIERRE_WAITRY_FONDO_FIJO_MAQUINAS = 'cierre_waitry.fondo_fijo_maquinas';

    public const CIERRE_WAITRY_DIFERENCIA_CAJA = 'cierre_waitry.diferencia_caja';

    public const CIERRE_ESTACIONAMIENTO_VENTAS = 'cierre_estacionamiento.ventas';

    public const CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA = 'cierre_estacionamiento.diferencia_caja';

    public const CIERRE_VENDING_VENTAS = 'cierre_vending.ventas';

    public const CIERRE_VENDING_DIFERENCIA_CAJA = 'cierre_vending.diferencia_caja';

    public const CIERRE_BINGO_PREMIO53 = 'cierre_bingo.premio53';

    public const CIERRE_BINGO_EFECTIVO = 'cierre_bingo.efectivo';

    public const CIERRE_BINGO_POZO_BINGO = 'cierre_bingo.pozo_bingo';

    public const CIERRE_BINGO_PANTALLA = 'cierre_bingo.pantalla';

    public const CIERRE_BINGO_OTROS_PREMIOS = 'cierre_bingo.otros_premios';

    public const CIERRE_BINGO_DIFERENCIA_CAJA = 'cierre_bingo.diferencia_caja';

    public const CIERRE_BINGO_VENTAS = 'cierre_bingo.ventas';

    public const CIERRE_BINGO_POZO58 = 'cierre_bingo.pozo58';

    public const CIERRE_BINGO_PAGO_HOSPITAL = 'cierre_bingo.pago_hospital';

    public const CIERRE_BINGO_CONT_HOSPITAL = 'cierre_bingo.cont_hospital';

    public const CIERRE_MAQUINA_CAJA_PESOS = 'cierre_maquina.caja_pesos';

    public const CIERRE_MAQUINA_TARJETAS = 'cierre_maquina.tarjetas';

    public const CIERRE_MAQUINA_DOLARES = 'cierre_maquina.dolares';

    public const CIERRE_MAQUINA_EUROS = 'cierre_maquina.euros';

    public const CIERRE_MAQUINA_CAJA_TRANSITORIA = 'cierre_maquina.caja_transitoria';

    public const CIERRE_MAQUINA_DIFERENCIA_CAJA = 'cierre_maquina.diferencia_caja';

    public const CIERRE_MAQUINA_VENTAS_RULETA = 'cierre_maquina.ventas_ruleta';

    public const CIERRE_MAQUINA_CANON_LOTERIA = 'cierre_maquina.canon_loteria';

    public const CIERRE_MAQUINA_CONT_CANON_LOTERIA = 'cierre_maquina.cont_canon_loteria';

    public const CIERRE_MAQUINA_CANON_HOSPITAL = 'cierre_maquina.canon_hospital';

    public const CIERRE_MAQUINA_CONT_CANON_HOSPITAL = 'cierre_maquina.cont_canon_hospital';

    public const CIERRE_MAQUINA_TICKET_PROM_DEBE = 'cierre_maquina.ticket_prom_debe';

    public const CIERRE_MAQUINA_TICKET_PROM_HABER = 'cierre_maquina.ticket_prom_haber';

    public const CIERRE_MAQUINA_GASTOS = 'cierre_maquina.gastos';

    public const CIERRE_MAQUINA_VENTAS = 'cierre_maquina.ventas';

    public const CIERRE_MAQUINA_TICKET_GASTRO = 'cierre_maquina.ticket_gastro';

    public const CIERRE_MAQUINA_PODER_PUBLICO = 'cierre_maquina.poder_publico';

    public const CIERRE_MAQUINA_IMPUESTO_ESP = 'cierre_maquina.impuesto_esp';

    public const CIERRE_MAQUINA_FF_MAQUINA = 'cierre_maquina.ff_maquina';

    public const CIERRE_MAQUINA_PARTIDA_PENDIENTE = 'cierre_maquina.partida_pendiente';

    public const CIERRE_MAQUINA_CRIPTO = 'cierre_maquina.cripto';

    public const CIERRE_MAQUINA_TOTALCOIN = 'cierre_maquina.totalcoin';

    public const CIERRE_MAQUINA_MEP = 'cierre_maquina.mep';

    public const CIERRE_MAQUINA_PAGO24 = 'cierre_maquina.pago24';

    /** IVA fiscal general (todas las ventas / cierres que lo consumen). */
    public const VENTAS_IVA_DEBITO_FISCAL = 'ventas.iva_debito_fiscal';

    public const VENTAS_IVA_CREDITO_FISCAL = 'ventas.iva_credito_fiscal';

    /** Cheques propios posdatados (haber al emitir; reclasifica agente diario). */
    public const CAJA_CHEQUES_DIFERIDOS = 'caja.cheques_diferidos';

    /** Cheques recibidos de terceros en ingreso/egreso y cobranzas. */
    public const CAJA_VALORES_A_DEPOSITAR = 'caja.valores_a_depositar';

    /** Cuentas compra «Otros activos» (u homologables) en transferencias contables (TRCONT). */
    public const STOCK_TRANSFERENCIA_OTROS_ACTIVOS = 'stock.transferencia_otros_activos';

    /**
     * Anticipos a proveedores (OP adelantada / pago a cuenta).
     * Vacía = el anticipo queda en la cuenta de proveedores y no hay nada que reclasificar al aplicarlo.
     */
    public const PAGO_ANTICIPO_PROVEEDOR = 'pago.anticipo_proveedor';

    /** Pasivo laboral: haber del asiento de devengamiento; debe del asiento de pago. */
    public const SUELDOS_A_PAGAR = 'sueldos.a_pagar';

    public const SUELDOS_GASTO_REMUNERATIVO = 'sueldos.gasto_remunerativo';

    public const SUELDOS_GASTO_NO_REMUNERATIVO = 'sueldos.gasto_no_remunerativo';

    public const SUELDOS_GASTO_CONTRIBUCION = 'sueldos.gasto_contribucion';

    public const SUELDOS_PASIVO_RETENCION = 'sueldos.pasivo_retencion';

    public const SUELDOS_PASIVO_CONTRIBUCION = 'sueldos.pasivo_contribucion';

    /** Haber del asiento de pago de haberes (fase 3). */
    public const SUELDOS_BANCO_PAGO = 'sueldos.banco_pago';

    /**
     * @return array<string, array{
     *   grupo: string,
     *   descripcion: string,
     *   modulo_tabla: ?string,
     *   modulo_columna: ?string,
     *   env_config: ?string,
     *   multiple?: bool
     * }>
     */
    public static function catalogo(): array
    {
        return [
            self::RECEPCION_PROVISION_FACTURAS => [
                'grupo' => 'Recepción proveedores',
                'descripcion' => 'Provisión de facturas a recibir (haber en recepción normal)',
                'modulo_tabla' => 'configuracion_recepcion_proveedor',
                'modulo_columna' => 'cuentacontable_provision_facturas_id',
                'env_config' => null,
            ],
            self::RECEPCION_FACTURA_ANTICIPADA => [
                'grupo' => 'Recepción proveedores',
                'descripcion' => 'Factura anticipada (cierre OC anticipada)',
                'modulo_tabla' => 'configuracion_recepcion_proveedor',
                'modulo_columna' => 'cuentacontable_factura_anticipada_id',
                'env_config' => null,
            ],
            self::RECEPCION_ANTICIPO_BIENES_USO => [
                'grupo' => 'Recepción proveedores',
                'descripcion' => 'Anticipo bienes de uso (cierre OC anticipada)',
                'modulo_tabla' => 'configuracion_recepcion_proveedor',
                'modulo_columna' => 'cuentacontable_anticipo_bienes_uso_id',
                'env_config' => null,
            ],
            self::RECEPCION_PROVEEDORES_INTANGIBLE => [
                'grupo' => 'Recepción proveedores',
                'descripcion' => 'Proveedores intangible (cierre OC anticipada)',
                'modulo_tabla' => 'configuracion_recepcion_proveedor',
                'modulo_columna' => 'cuentacontable_proveedores_intangible_id',
                'env_config' => null,
            ],
            self::CIERRE_WAITRY_VENTAS => [
                'grupo' => 'Cierre jornada Waitry',
                'descripcion' => 'Ventas mostrador (haber asientos del proceso)',
                'modulo_tabla' => 'gastronomia_cierre_jornada_config',
                'modulo_columna' => 'cuenta_ventas_id',
                'env_config' => 'gastronomia.cierre_jornada_cuenta_ventas_id',
            ],
            self::CIERRE_WAITRY_IVA => [
                'grupo' => 'Cierre jornada Waitry',
                'descripcion' => 'IVA débito fiscal (haber asientos del proceso)',
                'modulo_tabla' => 'gastronomia_cierre_jornada_config',
                'modulo_columna' => 'cuenta_iva_id',
                'env_config' => 'gastronomia.cierre_jornada_cuenta_iva_id',
            ],
            self::CIERRE_WAITRY_VENTAS_KIOSCO => [
                'grupo' => 'Cierre jornada Waitry',
                'descripcion' => 'Ventas kiosco / cigarrillos (gravado + imp. interno)',
                'modulo_tabla' => 'gastronomia_cierre_jornada_config',
                'modulo_columna' => 'cuenta_ventas_kiosco_id',
                'env_config' => 'gastronomia.cierre_jornada_cuenta_ventas_kiosco_id',
            ],
            self::CIERRE_WAITRY_FONDO_FIJO_MAQUINAS => [
                'grupo' => 'Cierre jornada Waitry',
                'descripcion' => 'Fondo fijo máquinas',
                'modulo_tabla' => 'gastronomia_cierre_jornada_config',
                'modulo_columna' => 'cuenta_fondo_fijo_maquinas_id',
                'env_config' => 'gastronomia.cierre_jornada_cuenta_fondo_fijo_maquinas_id',
            ],
            self::CIERRE_WAITRY_DIFERENCIA_CAJA => [
                'grupo' => 'Cierre jornada Waitry',
                'descripcion' => 'Diferencia de caja / invitaciones cortesía $0,01',
                'modulo_tabla' => 'gastronomia_cierre_jornada_config',
                'modulo_columna' => 'cuenta_diferencia_caja_id',
                'env_config' => 'gastronomia.cierre_jornada_cuenta_diferencia_caja_id',
            ],
            self::CIERRE_ESTACIONAMIENTO_VENTAS => [
                'grupo' => 'Cierre rendiciones estacionamiento',
                'descripcion' => 'Ventas estacionamiento (haber asiento cierre rendición)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA => [
                'grupo' => 'Cierre rendiciones estacionamiento',
                'descripcion' => 'Diferencia de caja / redondeos / sobrantes y faltantes',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_VENDING_VENTAS => [
                'grupo' => 'Cierre rendiciones vending',
                'descripcion' => 'Ventas vending (haber asiento cierre rendición)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_VENDING_DIFERENCIA_CAJA => [
                'grupo' => 'Cierre rendiciones vending',
                'descripcion' => 'Diferencia de caja / redondeos / sobrantes y faltantes',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_PREMIO53 => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Premio 53% (impcont 451 → 521050001)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_EFECTIVO => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Efectivo / caja (impcont 452 → 111010001)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_POZO_BINGO => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Pozo bingo a pagar (impcont 453 → 211010006)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_PANTALLA => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Premio pantalla (impcont 454 → 521040006)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_OTROS_PREMIOS => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Otros premios / dif. última bola (impcont 455 → 521040001)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_DIFERENCIA_CAJA => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Diferencia de caja / refuerzo (impcont 456 → 521280004)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_VENTAS => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Deudores por venta bingo (impcont 457 → 411010001)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_POZO58 => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Pozo bingo 58% / devengamiento (impcont 458 → 521030001)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_PAGO_HOSPITAL => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Cuenta pago hospital (impcont 459 → 521020002)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_BINGO_CONT_HOSPITAL => [
                'grupo' => 'Cierre rendiciones bingo',
                'descripcion' => 'Contribución hospital (impcont 460 → 215010003)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CAJA_PESOS => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Caja pesos / efectivo (impcont 473)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_TARJETAS => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Tarjetas a cobrar (impcont 474)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_DOLARES => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Dólares en pesos (impcont 475 → 111010002 Caja dólar)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_EUROS => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Euros en pesos (impcont 476)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CAJA_TRANSITORIA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Caja transitoria (impcont 477)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_DIFERENCIA_CAJA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Diferencia de caja (impcont 478)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_VENTAS_RULETA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Venta ruletas (impcont 479)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CANON_LOTERIA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Canon lotería 34% (impcont 480)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CONT_CANON_LOTERIA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Contrapartida canon lotería (impcont 481)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CANON_HOSPITAL => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Canon hospital 1% (impcont 482)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CONT_CANON_HOSPITAL => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Contrapartida canon hospital (impcont 483)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_TICKET_PROM_DEBE => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Tickets promocionales debe (impcont 484 → 521040005 Obsequios; CC 96)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_TICKET_PROM_HABER => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Tickets promocionales haber (impcont 485 → 211010009 Moneda poder público)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_GASTOS => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Vales / reintegros (impcont 492)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_VENTAS => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Venta máquinas online (impcont 493)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_TICKET_GASTRO => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Ticket gastronomía (impcont 494)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_PODER_PUBLICO => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Poder público / pago diferido (impcont 495)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_IMPUESTO_ESP => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Impuesto específico (impcont 467)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_FF_MAQUINA => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Fondo fijo máquinas / variación FF (impcont 461)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_PARTIDA_PENDIENTE => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Partida pendiente de cuadre (impcont 466)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_CRIPTO => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Cripto en pesos (impcont 943)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_TOTALCOIN => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Totalcoin máquina (impcont 950)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_MEP => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'MEP (impcont 940)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CIERRE_MAQUINA_PAGO24 => [
                'grupo' => 'Cierre rendiciones máquinas',
                'descripcion' => 'Pago 24 / venta ant. gastro (impcont 499)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::VENTAS_IVA_DEBITO_FISCAL => [
                'grupo' => 'Ventas — IVA fiscal',
                'descripcion' => 'IVA débito fiscal (haber en ventas gravadas)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                // No usar iva_ventas.conciliacion.*: son maps de códigos, no cuentacontable_id.
                'env_config' => null,
            ],
            self::VENTAS_IVA_CREDITO_FISCAL => [
                'grupo' => 'Ventas — IVA fiscal',
                'descripcion' => 'IVA crédito fiscal (debe en NC, ej. 114010011)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::CAJA_CHEQUES_DIFERIDOS => [
                'grupo' => 'Caja — cheques',
                'descripcion' => 'Cheques propios posdatados (211010-013 CHEQUES DIFERIDOS)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => 'caja.cheques_diferidos_cuenta_id',
            ],
            self::CAJA_VALORES_A_DEPOSITAR => [
                'grupo' => 'Caja — cheques',
                'descripcion' => 'Cheques recibidos de terceros (valores a depositar)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => 'caja.valores_a_depositar_cuenta_id',
            ],
            self::STOCK_TRANSFERENCIA_OTROS_ACTIVOS => [
                'grupo' => 'Stock — transferencias',
                'descripcion' => 'Cuentas de compra que habilitan TRCONT (Otros activos / homologables)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
                'multiple' => true,
            ],
            self::PAGO_ANTICIPO_PROVEEDOR => [
                'grupo' => 'Pagos a proveedores',
                'descripcion' => 'Anticipos a proveedores (OP adelantada). Vacía = el anticipo queda en la cuenta de proveedores',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_A_PAGAR => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Sueldos a pagar (haber devengamiento; debe del pago de haberes)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_GASTO_REMUNERATIVO => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Gasto sueldos remunerativos (fallback tipo remunerativo / asignación)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_GASTO_NO_REMUNERATIVO => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Gasto beneficios no remunerativos (fallback tipo no remunerativo)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_GASTO_CONTRIBUCION => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Gasto cargas sociales empleador (fallback contribuciones sin rubro)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_PASIVO_RETENCION => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Retenciones / aportes del trabajador a depositar (fallback descuentos)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_PASIVO_CONTRIBUCION => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Contribuciones patronales a pagar (fallback haber contribuciones)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
            self::SUELDOS_BANCO_PAGO => [
                'grupo' => 'Sueldos',
                'descripcion' => 'Banco / caja del pago de haberes (asiento 2; se usa en fase 3)',
                'modulo_tabla' => null,
                'modulo_columna' => null,
                'env_config' => null,
            ],
        ];
    }

    /** @return list<string> */
    public static function todasLasClaves(): array
    {
        return array_keys(self::catalogo());
    }

    public static function esMultiple(string $clave): bool
    {
        return (bool) (self::catalogo()[$clave]['multiple'] ?? false);
    }

    /** @return list<string> */
    public static function clavesMultiples(): array
    {
        $out = [];
        foreach (self::catalogo() as $clave => $meta) {
            if (! empty($meta['multiple'])) {
                $out[] = $clave;
            }
        }

        return $out;
    }
}
