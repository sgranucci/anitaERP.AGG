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

    /** Cheques propios posdatados (haber al emitir; reclasifica agente diario). */
    public const CAJA_CHEQUES_DIFERIDOS = 'caja.cheques_diferidos';

    /** Cheques recibidos de terceros en ingreso/egreso y cobranzas. */
    public const CAJA_VALORES_A_DEPOSITAR = 'caja.valores_a_depositar';

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
        ];
    }

    /** @return list<string> */
    public static function todasLasClaves(): array
    {
        return array_keys(self::catalogo());
    }
}
