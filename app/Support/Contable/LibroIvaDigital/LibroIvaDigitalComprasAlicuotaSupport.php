<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Ajustes ARCA para COMPRAS_CBTE / COMPRAS_ALICUOTAS.
 *
 * Tipos A/M informan alícuotas (0003 si la operación es toda exenta / no gravada).
 * Tipos B (006-008) y C (011-016) van con cantidad 0: el Portal rechaza
 * «No se deben informar alícuotas para tipos de comprobante B o C».
 * El punto de venta 0 no es válido salvo tipos 033/099/331/332.
 */
final class LibroIvaDigitalComprasAlicuotaSupport
{
    /** @var list<string> */
    public const TIPOS_PV_CERO = ['033', '099', '331', '332'];

    /** Factura / ND / NC B. */
    public const TIPOS_B = ['006', '007', '008'];

    /**
     * Compras: B y C sin archivo de alícuotas.
     *
     * @var list<string>
     */
    public const TIPOS_SIN_ALICUOTA = [
        '006', '007', '008',
        '011', '012', '013', '014', '015', '016',
    ];

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

        if (in_array($tipo, self::TIPOS_SIN_ALICUOTA, true)) {
            $cabecera['cantidad_alicuotas'] = 0;
            if (! isset($cabecera['codigo_operacion']) || trim((string) $cabecera['codigo_operacion']) === '') {
                $cabecera['codigo_operacion'] = ' ';
            }
            $registro = [
                'cabecera' => $cabecera,
                'alicuotas' => [],
            ];

            return LibroIvaDigitalComprasImportesSupport::cerrarRegistro(
                LibroIvaDigitalComprasImportesSupport::equilibrarSinAlicuotas($registro),
            );
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

        return LibroIvaDigitalComprasImportesSupport::cerrarRegistro($registro);
    }
}
