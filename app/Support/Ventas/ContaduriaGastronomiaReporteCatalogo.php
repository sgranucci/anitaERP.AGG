<?php

namespace App\Support\Ventas;

/**
 * Cortes que contaduría pidió sobre el reporte de descuentos de gastronomía.
 *
 * Canje, staff, managers y comedor se agrupan por cliente interno.
 * Invitaciones no PF se agrupan por código de descuento.
 * El Excel de referencia anotó el comedor como 1154; en el maestro es el cliente 1554 DESC PERSONAL INTERNO.
 */
final class ContaduriaGastronomiaReporteCatalogo
{
    /** @var list<string> */
    public const CANJE_CODIGOS = ['500', '1500', '1501'];

    /** @var list<string> */
    public const INVITACION_CODIGOS = ['20', '30', '40', '50', '60', '70', '120', '140'];

    /** @var list<string> */
    public const MANAGER_CODIGOS = [
        '3003', '4502', '4504', '4517', '4518', '4520', '4521', '4522',
        '7000', '7001', '7002', '7003', '7004', '7005', '7006', '7007',
        '7008', '7009', '7010', '7011', '7012', '7013', '7014', '7015',
    ];

    public const STAFF_CODIGO_DESDE = 80;

    public const STAFF_CODIGO_HASTA = 105;

    public const COMEDOR_CODIGO = '1554';

    /**
     * @param  list<string>  $codigos
     * @return list<string>
     */
    public static function normalizarCodigos(array $codigos): array
    {
        $out = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo !== '' && ! in_array($codigo, $out, true)) {
                $out[] = $codigo;
            }
        }

        return $out;
    }
}
