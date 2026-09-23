<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Incoterm;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Maestros de facturación Interforming alineados a Anita (b-fremito.c / a-comprob.c).
 *
 * | Anita                         | ERP                                      |
 * |-------------------------------|------------------------------------------|
 * | suc 4 suc_fiscal=X            | PV 00004 modo E + wsfex_v1               |
 * | suc 5 suc_fiscal=E            | PV 00005 modo C + wsfev1                 |
 * | tipo FAE + letra E            | tipotransaccion FAE codigo 019 (AFIP 19) |
 * | tipo FAC local                | tipotransaccion FAC codigo 001           |
 * | help_8 FOB..EXW               | incoterm abreviaturas                    |
 * | carga_pant4 bultos/peso/ley.  | modal export + comprob IF cols           |
 * | Fuente histórica REB→FAE      | ERP factura PED/PEX (sin REB/REX)        |
 */
final class InterformingFacturacionMaestrosSupport
{
    public const PV_EXPORTACION_CODIGO = '00004';

    public const PV_LOCAL_ELECTRONICA_CODIGO = '00005';

    public const TIPO_FAE = 'FAE';

    public const TIPO_FAC = 'FAC';

    public const TIPO_NCE_EXPORT = 'NCE';

    public const TIPO_NDE_EXPORT = 'NDE';

    /** @var list<array{abreviatura: string, nombre: string}> */
    public const INCOTERMS = [
        ['abreviatura' => 'FOB', 'nombre' => 'Free On Board'],
        ['abreviatura' => 'CIF', 'nombre' => 'Cost, Insurance and Freight'],
        ['abreviatura' => 'CFR', 'nombre' => 'Cost and Freight'],
        ['abreviatura' => 'FCA', 'nombre' => 'Free Carrier'],
        ['abreviatura' => 'CIP', 'nombre' => 'Carriage and Insurance Paid To'],
        ['abreviatura' => 'DDU', 'nombre' => 'Delivered Duty Unpaid'],
        ['abreviatura' => 'DDP', 'nombre' => 'Delivered Duty Paid'],
        ['abreviatura' => 'EXW', 'nombre' => 'Ex Works'],
    ];

    /** @var list<array{abreviatura: string, nombre: string}> */
    public const FORMASPAGO = [
        ['abreviatura' => 'CONT', 'nombre' => 'Contado'],
        ['abreviatura' => 'TRAN', 'nombre' => 'Transferencia bancaria'],
        ['abreviatura' => 'CCRE', 'nombre' => 'Carta de crédito'],
        ['abreviatura' => 'COBA', 'nombre' => 'Cobro anticipado'],
        ['abreviatura' => 'CUEN', 'nombre' => 'Cuenta corriente'],
    ];

    public static function idPuntoventaExportacion(): ?int
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return null;
        }

        $id = (int) (Puntoventa::query()
            ->where('codigo', self::PV_EXPORTACION_CODIGO)
            ->where('estado', 'A')
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function idPuntoventaLocalElectronica(): ?int
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return null;
        }

        $id = (int) (Puntoventa::query()
            ->where('codigo', self::PV_LOCAL_ELECTRONICA_CODIGO)
            ->where('estado', 'A')
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function idTipotransaccionPorAbreviatura(string $abreviatura): ?int
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return null;
        }

        $abrev = strtoupper(trim($abreviatura));
        if ($abrev === '') {
            return null;
        }

        $id = (int) (Tipotransaccion::query()
            ->where('abreviatura', $abrev)
            ->where('operacion', 'V')
            ->where('estado', 'A')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Defaults de modal: PEX / letra E → PV4 + FAE; resto → PV5 + FAC.
     *
     * @return array{puntoventa_id: int|null, tipotransaccion_id: int|null}
     */
    public static function defaultsParaPedido(?string $codigoPedido, ?string $letraCliente = null): array
    {
        $esExport = self::pedidoEsExportacion($codigoPedido, $letraCliente);

        return [
            'puntoventa_id' => $esExport
                ? self::idPuntoventaExportacion()
                : self::idPuntoventaLocalElectronica(),
            'tipotransaccion_id' => $esExport
                ? self::idTipotransaccionPorAbreviatura(self::TIPO_FAE)
                : self::idTipotransaccionPorAbreviatura(self::TIPO_FAC),
        ];
    }

    public static function pedidoEsExportacion(?string $codigoPedido, ?string $letraCliente = null): bool
    {
        $codigo = strtoupper(trim((string) $codigoPedido));
        if (str_starts_with($codigo, 'PEX') || str_contains($codigo, 'PEX')) {
            return true;
        }

        return strtoupper(trim((string) $letraCliente)) === 'E';
    }

    public static function idIncotermPorAbreviatura(string $abreviatura): ?int
    {
        $abrev = strtoupper(trim($abreviatura));
        if ($abrev === '') {
            return null;
        }

        $id = (int) (Incoterm::query()->where('abreviatura', $abrev)->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
