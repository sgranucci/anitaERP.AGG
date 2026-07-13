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

    /** IVA fiscal general (todas las ventas / cierres que lo consumen). */
    public const VENTAS_IVA_DEBITO_FISCAL = 'ventas.iva_debito_fiscal';

    public const VENTAS_IVA_CREDITO_FISCAL = 'ventas.iva_credito_fiscal';

    /** Cheques propios posdatados (haber al emitir; reclasifica agente diario). */
    public const CAJA_CHEQUES_DIFERIDOS = 'caja.cheques_diferidos';

    /** Cheques recibidos de terceros en ingreso/egreso y cobranzas. */
    public const CAJA_VALORES_A_DEPOSITAR = 'caja.valores_a_depositar';

    /** Cuenta compra «Otros activos» en transferencias contables (TRCONT). */
    public const STOCK_TRANSFERENCIA_OTROS_ACTIVOS = 'stock.transferencia_otros_activos';

    /**
     * @return array<string, array{
     *   grupo: string,
     *   descripcion: string,
     *   modulo_tabla: ?string,
     *   modulo_columna: ?string,
     *   env_config: ?string
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
                'descripcion' => 'Otros activos (cuenta compra en TRCONT, ej. 117010001)',
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
}
