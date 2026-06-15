<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Normaliza cantidades y precios leídos por OCR (formato AR o US).
 */
final class RecepcionProveedorOcrNumeroSupport
{
    public static function parsear(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $valor = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';
        if ($valor === '' || $valor === '-' || $valor === '.' || $valor === ',') {
            return null;
        }

        $tieneComa = str_contains($valor, ',');
        $tienePunto = str_contains($valor, '.');

        if ($tieneComa && $tienePunto) {
            $ultimaComa = strrpos($valor, ',');
            $ultimoPunto = strrpos($valor, '.');
            if ($ultimaComa > $ultimoPunto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($tieneComa) {
            $partes = explode(',', $valor);
            $valor = count($partes) === 2 && strlen($partes[1]) <= 2
                ? str_replace(',', '.', $valor)
                : str_replace(',', '', $valor);
        }

        if (! is_numeric($valor)) {
            return null;
        }

        return (float) $valor;
    }

    public static function normalizarSku(?string $sku): string
    {
        $sku = trim((string) $sku);

        return ltrim($sku, '0');
    }
}
