<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Nro. de cliente / medidor de una factura de proveedor tal como lo guarda Anita.
 *
 * En la tabla compra, `com_cliente` (char 30) lleva el nro. de cliente a la izquierda y,
 * en las cargas recientes, la hora de carga a la derecha: "25519-K                  14:56".
 * Las cargas hechas desde el ERP (hasta que se empezó a grabar servicio_cliente) solo
 * traen la hora: ahí no hay nro. de cliente que recuperar.
 *
 * Ferli y Crown no tienen la columna com_cliente (ver CompraCabeceraAnitaMapper).
 */
final class AnitaCompraClienteSupport
{
    public static function disponible(): bool
    {
        return ! EntornoEmpresaSupport::esFerli() && ! EntornoEmpresaSupport::esCrown();
    }

    /**
     * Saca la hora del final y devuelve el nro. de cliente ('' si solo había hora o espacios).
     */
    public static function extraerNroCliente(mixed $raw): string
    {
        $texto = trim((string) ($raw ?? ''));
        if ($texto === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $texto) === 1) {
            return '';
        }

        $sinHora = preg_replace('/\s+\d{1,2}:\d{2}$/', '', $texto);

        return trim((string) $sinHora);
    }
}
