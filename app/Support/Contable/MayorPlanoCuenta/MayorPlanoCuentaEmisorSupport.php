<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Qué entidad identifica el emisor de Anita (subd_emisor / subh_emisor).
 *
 * El campo es polimórfico: lo definen el subsistema (subd_sistema / subh_sistema /
 * ctav_sistema) y el tipo de comprobante. Tomarlo siempre como proveedor mostraba
 * datos falsos: en un ING/EGR/DEP de tesorería el emisor 00000226 es la cuenta de
 * caja BCO. MACRO GERLI, y el mayor imprimía el proveedor 226 con su CUIT.
 *
 * Mapeo verificado contra subdiario y subhist de AGG (2025-2026):
 *
 * | Sistema | Tipos                          | Emisor        |
 * |---------|--------------------------------|---------------|
 * | C       | COM, FGA, FIS, DEP, TRA…       | Proveedor     |
 * | T       | OPP, OPA, OPV, AOP, CHP, APA…  | Proveedor     |
 * | T       | COB, CHT                       | Cliente       |
 * | T       | ING, EGR, DEP, TRF             | Cuenta de caja|
 * | V       | FAE, FOV, NCE, NDB             | Cliente       |
 */
final class MayorPlanoCuentaEmisorSupport
{
    public const ENTIDAD_PROVEEDOR = 'proveedor';

    public const ENTIDAD_CLIENTE = 'cliente';

    public const ENTIDAD_CUENTACAJA = 'cuentacaja';

    private const SISTEMA_COMPRAS = 'C';

    private const SISTEMA_TESORERIA = 'T';

    private const SISTEMA_VENTAS = 'V';

    /** Cobranzas y cheques de terceros: el emisor es quien nos pagó. */
    private const TIPOS_CLIENTE = ['COB', 'CHT'];

    /** Ingresos y egresos de caja: solo existen en tesorería. */
    private const TIPOS_CUENTACAJA = ['ING', 'EGR'];

    /** En tesorería mueven cuentas entre sí; en compras identifican al proveedor. */
    private const TIPOS_CUENTACAJA_TESORERIA = ['DEP', 'TRF'];

    /** ctamov no trae emisor: se deduce del «003615 EL SOL» de la descripción. */
    private const TIPOS_EMISOR_EN_DESCRIPCION = ['COM', 'DEP'];

    public static function entidad(string $sistema, string $tipoComprobante): string
    {
        $sistema = strtoupper(trim($sistema));
        $tipo = strtoupper(trim($tipoComprobante));

        if ($sistema === self::SISTEMA_VENTAS) {
            return self::ENTIDAD_CLIENTE;
        }

        if ($sistema === self::SISTEMA_COMPRAS) {
            return self::ENTIDAD_PROVEEDOR;
        }

        if (in_array($tipo, self::TIPOS_CLIENTE, true)) {
            return self::ENTIDAD_CLIENTE;
        }

        if (in_array($tipo, self::TIPOS_CUENTACAJA, true)) {
            return self::ENTIDAD_CUENTACAJA;
        }

        if ($sistema === self::SISTEMA_TESORERIA
            && in_array($tipo, self::TIPOS_CUENTACAJA_TESORERIA, true)) {
            return self::ENTIDAD_CUENTACAJA;
        }

        return self::ENTIDAD_PROVEEDOR;
    }

    /**
     * @return array{codigo: string, entidad: string, deducido: bool}
     */
    public static function resolver(
        string $sistema,
        string $tipoComprobante,
        string $emisorOrigen,
        string $descripcionMovimiento = '',
    ): array {
        $entidad = self::entidad($sistema, $tipoComprobante);
        $emisor = self::normalizarCodigo($emisorOrigen);

        if ($emisor !== '') {
            return ['codigo' => $emisor, 'entidad' => $entidad, 'deducido' => false];
        }

        $codigoDescripcion = self::codigoEnDescripcion($entidad, $tipoComprobante, $descripcionMovimiento);
        if ($codigoDescripcion === '') {
            return ['codigo' => '', 'entidad' => $entidad, 'deducido' => false];
        }

        return ['codigo' => $codigoDescripcion, 'entidad' => $entidad, 'deducido' => true];
    }

    /**
     * Códigos en cero (`000000` de TRF o IEV sin contraparte) no identifican a nadie.
     * Los maestros del ERP guardan el código sin ceros a la izquierda.
     */
    public static function normalizarCodigo(string $codigo): string
    {
        $codigo = ltrim(trim($codigo), '0');

        return $codigo !== '' ? $codigo : '';
    }

    private static function codigoEnDescripcion(string $entidad, string $tipoComprobante, string $descripcion): string
    {
        if ($entidad !== self::ENTIDAD_PROVEEDOR) {
            return '';
        }

        if (! in_array(strtoupper(trim($tipoComprobante)), self::TIPOS_EMISOR_EN_DESCRIPCION, true)) {
            return '';
        }

        if (preg_match('/^\s*(\d+)/', $descripcion, $m) !== 1) {
            return '';
        }

        return self::normalizarCodigo($m[1]);
    }
}
