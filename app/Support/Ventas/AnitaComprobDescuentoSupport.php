<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Descuento de cabecera como a-comprob.c calcula() (~4227–4289).
 *
 * Aplica en facturación de administración (todas las empresas):
 * mostrador, pedido y remito.
 *
 * No aplica en POS gastronomía / estacionamiento / facturación local:
 * pasar {@see self::FLAG_OMITIR_CIRCUITO_POS} en datosCliente.
 *
 * 1) Se calcula sobre el neto (tot_dto = tot_neto × % / 100) y se netea gravado/exento.
 * 2) Si la letra no es A, se expresa el importe del descuento con IVA incluido:
 *    tot_dto *= (1 + tasa_iva/100)  — equivalente a aplicar el % sobre el bruto.
 *
 * El gravado/IVA fiscal siguen siendo sobre neto; solo cambia el importe de
 * "Descuento Gral." / ven_monto_desc (y con eso el pie visible en B/C/…).
 */
final class AnitaComprobDescuentoSupport
{
    /**
     * Si está en datosCliente, no se expresa el dto sobre bruto (POS gastro/estacionamiento/locales).
     */
    public const FLAG_OMITIR_CIRCUITO_POS = 'omitir_descuento_sobre_bruto_pos';

    /**
     * @param  array<string, mixed>  $dataCliente
     */
    public static function debeExpresarSobreBruto(array $dataCliente, string $letra): bool
    {
        if (! empty($dataCliente[self::FLAG_OMITIR_CIRCUITO_POS])) {
            return false;
        }

        return strtoupper(trim($letra)) !== 'A';
    }

    /**
     * @param  array<string|int, float|int|string>  $descuentoNetoPorTasa  tasa IVA => importe dto sobre neto
     */
    public static function expresarParaLetra(string $letra, array $descuentoNetoPorTasa): float
    {
        $letra = strtoupper(trim($letra));
        $total = 0.0;

        foreach ($descuentoNetoPorTasa as $tasa => $importeNeto) {
            $neto = (float) $importeNeto;
            if (abs($neto) < 0.00001) {
                continue;
            }
            $tasaIva = (float) $tasa;
            if ($letra !== 'A' && $tasaIva > 0.00001) {
                $total += $neto * (1.0 + $tasaIva / 100.0);
            } else {
                $total += $neto;
            }
        }

        return VentaImporteDosDecimalesSupport::redondear($total);
    }

    /**
     * Atajo: un solo tramo de alícuota (caso típico Factura B 21 %).
     */
    public static function expresarImporte(string $letra, float $descuentoNeto, float $tasaIva): float
    {
        return self::expresarParaLetra($letra, [(string) $tasaIva => $descuentoNeto]);
    }
}
