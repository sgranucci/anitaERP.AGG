<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use DateTimeInterface;

/**
 * Lote de proveedor que Anita lee en etiquetas Zebra (a-stock.c):
 * recv_lote_proveed (14), recv_fecha_vto (AAAAMMDD) y recv_nro_interno
 * (numabm shared, programa a-stock.c, referencia 2).
 *
 * Un nulo en recv_nro_interno hace que ldlong devuelva -2147483648
 * y la pantalla lo muestre como -2147483.
 */
final class RecepcionProveedorAnitaLoteSupport
{
    /** strncmp / ststring del C de etiquetas: 14 caracteres. */
    public const LOTE_MAX = 14;

    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    public static function fechaAnita(mixed $fecha): int
    {
        if ($fecha instanceof DateTimeInterface) {
            return (int) $fecha->format('Ymd');
        }

        $texto = trim((string) $fecha);
        if ($texto === '') {
            return 0;
        }

        $ymd = substr($texto, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return 0;
        }

        return (int) str_replace('-', '', $ymd);
    }

    public static function loteTexto(mixed $lote): string
    {
        $texto = trim((string) $lote);

        return $texto === '' ? '' : mb_substr($texto, 0, self::LOTE_MAX);
    }

    /**
     * Línea que entra a recepmov y necesita lote + vencimiento.
     */
    public static function lineaRequiereLote(float $cantidad, float $cantidadRechazada): bool
    {
        return abs($cantidad) > 0.000001 || abs($cantidadRechazada) > 0.000001;
    }

    public static function mensajeSiFaltaEnLinea(Recepcion_Proveedor_Articulo $linea): ?string
    {
        if (! self::aplica()) {
            return null;
        }

        if (! self::lineaRequiereLote(
            (float) ($linea->cantidad ?? 0),
            (float) ($linea->cantidad_rechazada ?? 0)
        )) {
            return null;
        }

        // Recepciones ya enviadas sin lote: el reenvío conserva el número asignado.
        if ((int) ($linea->anita_nro_lote ?? 0) > 0) {
            return null;
        }

        $nro = (int) ($linea->orden ?? 0);
        $sku = trim((string) optional($linea->articulos)->sku);
        $ref = 'Línea '.($nro > 0 ? $nro : '?').($sku !== '' ? ' ('.$sku.')' : '');

        if (self::loteTexto($linea->lote_proveedor ?? '') === '') {
            return $ref.': indique el lote del proveedor. Anita lo usa para emitir la etiqueta.';
        }

        if (self::fechaAnita($linea->fecha_vto ?? null) <= 0) {
            return $ref.': indique el vencimiento del lote.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function mensajeSiFaltaEnItem(array $item, int $nroLinea): ?string
    {
        if (! self::aplica()) {
            return null;
        }

        if (! self::lineaRequiereLote(
            (float) ($item['cantidad'] ?? 0),
            (float) ($item['cantidad_rechazada'] ?? 0)
        )) {
            return null;
        }

        $ref = 'Línea '.$nroLinea;
        if (self::loteTexto($item['lote_proveedor'] ?? '') === '') {
            return $ref.': indique el lote del proveedor.';
        }

        if (self::fechaAnita($item['fecha_vto'] ?? null) <= 0) {
            return $ref.': indique el vencimiento del lote.';
        }

        return null;
    }
}
