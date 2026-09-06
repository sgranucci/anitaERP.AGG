<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Contrato de lectura del Mayor por concepto.
 *
 * Hoy la única implementación es MayorConceptoAnitaBridgeReader (Informix vía bridge HTTP).
 * La intención es poder agregar un lector que arme los mismos datos desde las tablas del ERP
 * en MySQL, para dejar de depender de Anita sin tocar MayorConceptoPeriodoProcesador.
 *
 * Todas las filas devueltas son objetos con los nombres de campo de Informix (subd_*, ctamov_*,
 * auxpag_*, etc.). Una implementación ERP debe respetar esos nombres: el motor los usa tal cual.
 *
 * IMPORTANTE sobre $errores: los métodos NO lanzan excepción cuando falla la lectura. Acumulan
 * el error en $errores y devuelven vacío. Por eso una lista vacía puede significar "no hay filas"
 * o "no se pudo leer"; hay que mirar $errores y fallosLectura() para distinguirlos.
 */
interface MayorConceptoLectorInterface
{
    /**
     * Precarga el período para varias empresas de una sola vez. Idempotente.
     *
     * @param  list<int>  $empresaIds
     */
    public function precargarPeriodoEmpresas(array $empresaIds, int $fechaDesde, int $fechaHasta): void;

    /**
     * Datos del período para una empresa.
     *
     * @return array{
     *   subdiario: list<object>,
     *   ctamov: list<object>,
     *   auxpag: list<object>,
     *   ctaconc: list<object>,
     *   promae: list<object>,
     *   errores: list<string>
     * }
     */
    public function cargarPeriodo(int $empresaId, int $fechaDesde, int $fechaHasta): array;

    /**
     * Aplicaciones históricas de una orden de pago (auxpag/axphist).
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarAuxpagHistorico(
        int $empresaId,
        string $tipo,
        int $rec,
        int $fecha,
        string $proveedor,
        int $sucursalOp,
        array &$errores,
    ): array;

    /**
     * Renglones COM (imputación de gasto) de una factura de compras.
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarComSubdiario(int $empresaId, string $tipo, string $letra, int $sucursal, int $nro, array &$errores): array;

    /**
     * Versión batch de cargarComSubdiario().
     *
     * @param  list<string>  $clavesCom  cada clave es "tipo|letra|sucursal|nro"
     * @param  list<string>  $errores
     * @return array<string, list<object>> la misma clave => renglones (vacío si no se encontró)
     */
    public function cargarComSubdiarioLote(int $empresaId, array $clavesCom, array &$errores): array;

    /**
     * Asiento completo de una factura de compras.
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarSubdiarioFacturaCompras(
        int $empresaId,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        int $nroInterno,
        string $proveedor,
        array &$errores,
    ): array;

    /**
     * Renglones de ctamov de un asiento puntual.
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarCtamovPorAsiento(int $empresaId, int $nroAsiento, array &$errores): array;

    /**
     * Relación factura -> orden de compra (aplicped).
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarAplicpedFactura(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
        array &$errores,
    ): array;

    /**
     * aplicped buscando por el comprobante referenciado en vez de por la factura.
     *
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarAplicpedPorReferencia(
        string $refTipo,
        string $refLetra,
        int $refSucursal,
        int $refNro,
        string $proveedor,
        array &$errores,
    ): array;

    /**
     * Versión batch de cargarAplicpedFactura().
     *
     * Ojo: cada factura es un array POSICIONAL [0=>proveedor, 1=>tipo, 2=>letra, 3=>sucursal, 4=>nro],
     * no asociativo. El motor puede mandar un 5º elemento (nro interno) que acá se ignora.
     *
     * @param  list<array<int, mixed>>  $facturas
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarAplicpedPorFacturas(array $facturas, array &$errores): array;

    /**
     * Ficha del proveedor (promae).
     *
     * @param  list<string>  $errores
     */
    public function cargarPromae(string $proveedor, array &$errores): ?object;

    /**
     * Versión batch de cargarPromae().
     *
     * @param  list<string>  $proveedores
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function cargarPromaePorProveedores(array $proveedores, array &$errores): array;

    /**
     * Concepto contable asociado a una orden de compra. 0 si no se pudo resolver.
     *
     * @param  list<string>  $errores
     */
    public function conceptoDesdeOrdenCompra(int $empresaId, int $nroOc, array &$errores): int;

    /**
     * Cantidad de lecturas que agotaron los reintentos. Si es > 0 el reporte quedó incompleto
     * y sus importes no son confiables.
     */
    public function fallosLectura(): int;
}
