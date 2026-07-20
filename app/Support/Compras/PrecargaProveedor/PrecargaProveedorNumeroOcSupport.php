<?php

namespace App\Support\Compras\PrecargaProveedor;

use RuntimeException;

/**
 * Número de OC Anita: siempre 6 dígitos (penmp_nro con sucursal 0).
 * Facilita detectarlo entre CUIT, fechas y números de factura en el PDF.
 */
final class PrecargaProveedorNumeroOcSupport
{
    public const LONGITUD = 6;

    /**
     * Normaliza a exactamente 6 dígitos (relleno con ceros a la izquierda).
     * Acepta "214482", "00214482", "0000-00214482", "X0000-00221480" o entero.
     * Con sucursal-número (guión), usa solo el número (parte derecha).
     */
    public function normalizar(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            throw new RuntimeException('Falta número de orden de compra.');
        }

        // "0000-00221480", "X0000-00221480" → 221480
        if (preg_match('/^[A-Za-z]*(\d+)-(\d+)$/', $texto, $matches)) {
            $nro = (int) $matches[2];
            if ($nro <= 0) {
                throw new RuntimeException('Número de OC inválido: '.$texto);
            }

            return str_pad((string) $nro, self::LONGITUD, '0', STR_PAD_LEFT);
        }

        $digitos = preg_replace('/\D/', '', $texto) ?? '';
        if ($digitos === '') {
            throw new RuntimeException('Número de OC inválido: '.$texto);
        }

        $nro = (int) $digitos;
        if ($nro <= 0 || $nro > 999999) {
            throw new RuntimeException(
                'El número de OC debe ser de '.self::LONGITUD.' dígitos (recibido: '.$texto.').'
            );
        }

        return str_pad((string) $nro, self::LONGITUD, '0', STR_PAD_LEFT);
    }

    /**
     * Valor entero para consultas Anita (penmp_nro).
     */
    public function paraConsultaAnita(string $numeroOcNormalizado): int
    {
        return (int) $this->normalizar($numeroOcNormalizado);
    }
}
