<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;

/**
 * Leyendas de asiento comprobante proveedor (ERP y ctamov Anita).
 *
 * Patrón a-compprov.c (asiento_contable / COMPROBANTE):
 *   sprintf(str, "%6.6s %30.30s", prom_proveedor, leyenda_o_nombre);
 *   ststring(str, SUBD_desc_mov, 30);
 *
 * En ERP no se trunca a 30: el límite aplica solo a ctav_desc_mov.
 */
final class ComprobanteProveedorAsientoDescripcionSupport
{
    /** ctav_desc_mov / subd_desc_mov en Informix. */
    public const LONGITUD_CTAV_DESC_MOV = 30;

    /**
     * Cabecera asiento ERP (no va a ctamov).
     */
    public static function descripcionAsientoErp(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);

        $tipo = substr((string) ($comprobante->tipotransaccion_compras?->abreviatura ?? 'FAC'), 0, 3);
        $letra = strtoupper(substr((string) ($comprobante->letra ?? ''), 0, 1));
        $suc = (int) ($comprobante->sucursal ?? 0);
        $nro = (int) ($comprobante->numerocomprobante ?? 0);
        $nombre = self::textoLeyendaONombre($comprobante);

        return trim(sprintf(
            'Comprobante proveedor %s %s-%d-%d %s',
            $tipo,
            $letra !== '' ? $letra : '-',
            $suc,
            $nro,
            $nombre
        ));
    }

    /**
     * Línea asiento_movimiento ERP: patrón Anita sin truncar a 30.
     */
    public static function descripcionLineaErp(
        Comprobante_Proveedor $comprobante,
        string $variante = 'normal',
    ): string {
        return trim(self::baseDescripcion($comprobante, $variante));
    }

    /**
     * Línea ctamov Anita: mismo patrón, sanitizado y máximo 30 caracteres.
     */
    public static function descripcionLineaCtamov(
        Comprobante_Proveedor $comprobante,
        string $variante = 'normal',
    ): string {
        return self::sanitizarCtamov(self::baseDescripcion($comprobante, $variante));
    }

    /**
     * Recorta una observación ERP ya armada al formato ctav_desc_mov (30).
     */
    public static function aCtamovDesdeErp(string $observacionErp): string
    {
        return self::sanitizarCtamov($observacionErp);
    }

    public static function sanitizarCtamov(string $texto): string
    {
        // Mismo filtro que AsientoRepository::guardarAnita (solo A-Z a-z 0-9 y espacio).
        $sanitizado = preg_replace('/([^A-Za-z0-9 ])/', '', $texto) ?? '';

        return substr(trim($sanitizado), 0, self::LONGITUD_CTAV_DESC_MOV);
    }

    private static function baseDescripcion(
        Comprobante_Proveedor $comprobante,
        string $variante,
    ): string {
        $comprobante->loadMissing(['proveedores', 'comprobante_proveedor_recepciones.recepcion_proveedores']);

        $codigoProv = self::codigoProveedor($comprobante);
        $texto = self::textoLeyendaONombre($comprobante);

        if ($variante === 'diferencia') {
            // a-compprov.c: "Dif. %6.6s %30.30s"
            return sprintf('Dif. %s %s', $codigoProv, $texto);
        }

        if ($variante === 'com' || $variante === 'car') {
            $refer = self::numeroRecepcionReferencia($comprobante);
            if ($refer > 0) {
                // Prefijo COM (nro recepción); alias 'car' por compatibilidad.
                return sprintf('COM: %d %s %s', $refer, $codigoProv, $texto);
            }
        }

        // a-compprov.c: "%6.6s %30.30s"
        return sprintf('%s %s', $codigoProv, $texto);
    }

    private static function codigoProveedor(Comprobante_Proveedor $comprobante): string
    {
        $codigo = (string) ($comprobante->proveedores?->codigo ?? '0');

        return str_pad(substr($codigo, 0, 6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * a-compprov: usa com_leyenda si tiene contenido; si no, prom_nombre.
     */
    private static function textoLeyendaONombre(Comprobante_Proveedor $comprobante): string
    {
        $leyenda = trim((string) ($comprobante->leyenda ?? ''));
        if ($leyenda !== '') {
            return $leyenda;
        }

        return trim((string) ($comprobante->proveedor_nombre_eventual
            ?: $comprobante->proveedores?->nombre
            ?: ''));
    }

    private static function numeroRecepcionReferencia(Comprobante_Proveedor $comprobante): int
    {
        $comprobante->loadMissing('comprobante_proveedor_recepciones.recepcion_proveedores');

        foreach ($comprobante->comprobante_proveedor_recepciones ?? [] as $vinculo) {
            $recepcion = $vinculo->recepcion_proveedores;
            $nro = (int) ($recepcion->numerorecepcion ?? 0);
            if ($nro > 0) {
                return $nro;
            }
        }

        return 0;
    }
}
