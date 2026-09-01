<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Ajustes ARCA para COMPRAS_CBTE / COMPRAS_ALICUOTAS.
 *
 * Tipos A/B/M deben informar al menos una alícuota (0003 si la operación es
 * toda exenta / no gravada). Solo tipos C (011-016) van con cantidad 0.
 * El punto de venta 0 no es válido salvo tipos 033/099/331/332.
 */
final class LibroIvaDigitalComprasAlicuotaSupport
{
    /** @var list<string> */
    public const TIPOS_PV_CERO = ['033', '099', '331', '332'];

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function asegurarRegistro(array $registro): array
    {
        $cabecera = $registro['cabecera'];
        $cabecera['numero_identificacion'] = LibroIvaDigitalComprasCuitSupport::resolver(
            (string) ($cabecera['numero_identificacion'] ?? ''),
            (string) ($cabecera['nombre_vendedor'] ?? ''),
        );
        $cabecera = LibroIvaDigitalIdentificacionSupport::aplicarACabecera($cabecera);
        $tipo = str_pad((string) ($cabecera['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT);
        $pv = (int) ($cabecera['punto_venta'] ?? 0);
        if ($pv < 1 && ! in_array($tipo, self::TIPOS_PV_CERO, true)) {
            $pv = 1;
            $cabecera['punto_venta'] = 1;
        }

        $alicuotas = [];
        foreach ($registro['alicuotas'] ?? [] as $fila) {
            $fila['punto_venta'] = $pv;
            $alicuotas[] = $fila;
        }

        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);

        $doc = (string) ($registro['cabecera']['codigo_documento'] ?? '80');
        $cuit = (string) ($registro['cabecera']['numero_identificacion'] ?? '0');
        foreach ($registro['alicuotas'] as $i => $fila) {
            $registro['alicuotas'][$i]['punto_venta'] = (int) $registro['cabecera']['punto_venta'];
            $registro['alicuotas'][$i]['codigo_documento'] = $doc;
            $registro['alicuotas'][$i]['numero_identificacion'] = $cuit;
        }

        $registro = LibroIvaDigitalComprasImportesSupport::equilibrarTipoC($registro);

        return LibroIvaDigitalComprasImportesSupport::reconciliarAlicuotasRedondeo($registro);
    }
}
