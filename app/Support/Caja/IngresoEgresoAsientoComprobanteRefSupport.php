<?php

namespace App\Support\Caja;

/**
 * Referencia fila del asiento ↔ factura del I/E.
 *
 * Cada línea del asiento que sale de los conceptos de una factura viaja por la grilla con
 * la referencia de esa factura (`comprobante_ref`), sin importar la cuenta contable que
 * tenga: el operador puede cambiarla y la línea sigue siendo de ese comprobante.
 *
 * - Factura ya grabada: `id:{comprobante_proveedor_id}`.
 * - Factura nueva (todavía sin id): `n:{token}` que arma el formulario al agregarla.
 *
 * Al guardar, la referencia se traduce al `comprobante_proveedor_id` real
 * (IngresoEgresoComprobanteIvaAsientoVinculoService).
 */
final class IngresoEgresoAsientoComprobanteRefSupport
{
    public const PREFIJO_ID = 'id:';

    public const PREFIJO_NUEVO = 'n:';

    /**
     * @param  array<string, mixed>  $comprobante  Payload de la grilla de comprobantes del I/E.
     */
    public static function refDesdePayload(array $comprobante): string
    {
        $id = (int) ($comprobante['id'] ?? 0);
        if ($id > 0) {
            return self::PREFIJO_ID.$id;
        }

        return self::normalizar($comprobante['ref'] ?? '');
    }

    public static function normalizar(mixed $ref): string
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return '';
        }
        if (str_starts_with($ref, self::PREFIJO_ID)) {
            return (int) substr($ref, strlen(self::PREFIJO_ID)) > 0
                ? self::PREFIJO_ID.(int) substr($ref, strlen(self::PREFIJO_ID))
                : '';
        }
        if (str_starts_with($ref, self::PREFIJO_NUEVO) && strlen($ref) > strlen(self::PREFIJO_NUEVO)) {
            return $ref;
        }

        return '';
    }

    /**
     * Referencias posteadas por la grilla del asiento, una por fila y en el mismo orden que
     * `cuentacontable_ids[]`. Si no coinciden en cantidad (asiento rearmado en servidor, p. ej.
     * pago desde solicitud de pago) no hay forma segura de empatar filas: se ignoran.
     *
     * @param  array<int|string, mixed>  $refs
     * @return array<int, string> índice de fila → referencia normalizada ('' = sin factura)
     */
    public static function listaAlineada(mixed $refs, int $cantidadFilas): array
    {
        if (! is_array($refs) || $cantidadFilas <= 0 || count($refs) !== $cantidadFilas) {
            return [];
        }

        $out = [];
        foreach (array_values($refs) as $i => $ref) {
            $out[$i] = self::normalizar($ref);
        }

        return $out;
    }
}
