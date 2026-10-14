<?php

namespace App\Support\Ventas;

/**
 * El comprobante eventual guarda el comprador en la venta y el cliente maestro
 * es solo el shell contable (Factura A del POS). Mail, teléfono y expreso del
 * maestro no son del comprador.
 */
final class ReceptorEventualContactoSupport
{
    public static function identidadDistintaDelMaestro(
        ?string $nombreComprobante,
        ?string $documentoComprobante,
        ?string $nombreMaestro,
        ?string $documentoMaestro,
    ): bool {
        $nombreVenta = self::normalizarNombre($nombreComprobante);
        $nombreCliente = self::normalizarNombre($nombreMaestro);
        if ($nombreVenta !== '' && $nombreCliente !== '' && $nombreVenta !== $nombreCliente) {
            return true;
        }

        $docVenta = self::normalizarDocumento($documentoComprobante);
        $docCliente = self::normalizarDocumento($documentoMaestro);
        if ($docVenta !== '' && $docCliente !== '' && $docVenta !== $docCliente) {
            return true;
        }

        return false;
    }

    /**
     * Mails del comprobante que no son los del maestro shell.
     *
     * @return list<string>
     */
    public static function emailsPropiosDelComprobante(?string $emailComprobante, ?string $emailMaestro): array
    {
        $delComprobante = self::partirEmails($emailComprobante);
        if ($delComprobante === []) {
            return [];
        }

        $delMaestro = array_fill_keys(self::partirEmails($emailMaestro), true);
        $propios = [];
        foreach ($delComprobante as $email) {
            if (! isset($delMaestro[$email])) {
                $propios[] = $email;
            }
        }

        return $propios;
    }

    public static function normalizarNombre(?string $nombre): string
    {
        $texto = trim((string) $nombre);
        if ($texto === '') {
            return '';
        }
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return mb_strtoupper($texto, 'UTF-8');
    }

    public static function normalizarDocumento(?string $documento): string
    {
        return preg_replace('/\D/', '', (string) $documento) ?? '';
    }

    /**
     * @return list<string>
     */
    private static function partirEmails(?string $raw): array
    {
        $partes = preg_split('/[;,]+/', (string) $raw) ?: [];
        $out = [];
        foreach ($partes as $parte) {
            $email = strtolower(trim($parte));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
