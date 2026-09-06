<?php

namespace App\Support\Compras\Tracking;

/**
 * Familias del tracking (FC / NC / ND / RC) derivadas de tipotransaccion_compra.
 *
 * Se agrupa por `codigoafip` y no por abreviatura: hay ~30 abreviaturas en uso
 * (FGA, FIS, FIB, CIS, DIS, NDE…) y agruparlas a mano es frágil. En este ERP
 * `codigoafip` funciona como código de familia: 01 factura, 02 nota de débito,
 * 03 nota de crédito.
 *
 * Excepción: la abreviatura REC («Recibo») tiene codigoafip 01 porque hay
 * proveedores que en lugar de factura emiten un recibo-factura, pero el
 * repositorio de escaneo del Anita lo indexa bajo ctipo '05'
 * (ver a-compprov.c: `if REC then ax_tipo_comp = "05"`).
 */
final class TrackingComprobanteFamilia
{
    public const FACTURA = 'FC';

    public const NOTA_CREDITO = 'NC';

    public const NOTA_DEBITO = 'ND';

    public const RECIBO = 'RC';

    public const ORDEN_PAGO = 'OP';

    public const OTRO = 'OT';

    /** Abreviatura que el Anita indexa con un ctipo distinto al codigoafip. */
    public const ABREVIATURA_RECIBO = 'REC';

    /** ctipo con el que scanfactura indexa los recibos. */
    public const CTIPO_SCAN_RECIBO = '05';

    /** @var array<string, string> codigoafip => familia */
    private const POR_CODIGO_AFIP = [
        '01' => self::FACTURA,
        '02' => self::NOTA_DEBITO,
        '03' => self::NOTA_CREDITO,
        '05' => self::ORDEN_PAGO,
    ];

    /** @var array<string, string> */
    private const ETIQUETAS = [
        self::FACTURA => 'Factura',
        self::NOTA_CREDITO => 'Nota de crédito',
        self::NOTA_DEBITO => 'Nota de débito',
        self::RECIBO => 'Recibo',
        self::ORDEN_PAGO => 'Orden de pago',
        self::OTRO => 'Otro',
    ];

    /**
     * Familias que el usuario puede elegir en el filtro de la grilla.
     *
     * @return array<string, string>
     */
    public static function opcionesFiltro(): array
    {
        return [
            self::FACTURA => self::ETIQUETAS[self::FACTURA],
            self::NOTA_CREDITO => self::ETIQUETAS[self::NOTA_CREDITO],
            self::NOTA_DEBITO => self::ETIQUETAS[self::NOTA_DEBITO],
            self::RECIBO => self::ETIQUETAS[self::RECIBO],
            self::ORDEN_PAGO => self::ETIQUETAS[self::ORDEN_PAGO],
        ];
    }

    public static function esFamiliaValida(?string $familia): bool
    {
        return $familia !== null && isset(self::ETIQUETAS[strtoupper(trim($familia))]);
    }

    public static function desde(?string $codigoAfip, ?string $abreviatura = null): string
    {
        if (self::esRecibo($abreviatura)) {
            return self::RECIBO;
        }

        $codigo = self::normalizarCodigoAfip($codigoAfip);

        return self::POR_CODIGO_AFIP[$codigo] ?? self::OTRO;
    }

    public static function etiqueta(?string $familia): string
    {
        return self::ETIQUETAS[strtoupper(trim((string) $familia))] ?? self::ETIQUETAS[self::OTRO];
    }

    /**
     * Clase de badge Bootstrap para la grilla.
     */
    public static function clasePill(?string $familia): string
    {
        return match (strtoupper(trim((string) $familia))) {
            self::FACTURA => 'badge-primary',
            self::NOTA_CREDITO => 'badge-danger',
            self::NOTA_DEBITO => 'badge-warning',
            self::RECIBO => 'badge-success',
            self::ORDEN_PAGO => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Códigos AFIP que corresponden a una familia, para filtrar en SQL.
     *
     * Devuelve `[]` cuando la familia no se resuelve por código (recibo), en
     * cuyo caso hay que filtrar por abreviatura con {@see abreviaturasDeFamilia}.
     *
     * @return list<string>
     */
    public static function codigosAfipDeFamilia(string $familia): array
    {
        $familia = strtoupper(trim($familia));
        if ($familia === self::RECIBO) {
            return [];
        }

        $codigos = [];
        foreach (self::POR_CODIGO_AFIP as $codigo => $fam) {
            if ($fam === $familia) {
                $codigos[] = $codigo;
            }
        }

        return $codigos;
    }

    /**
     * Abreviaturas que se excluyen o incluyen aparte de la familia por código.
     *
     * @return list<string>
     */
    public static function abreviaturasDeFamilia(string $familia): array
    {
        return strtoupper(trim($familia)) === self::RECIBO ? [self::ABREVIATURA_RECIBO] : [];
    }

    /**
     * ctipo con el que `base_admin.scanfactura` indexa este comprobante.
     */
    public static function ctipoScan(?string $codigoAfip, ?string $abreviatura = null): string
    {
        if (self::esRecibo($abreviatura)) {
            return self::CTIPO_SCAN_RECIBO;
        }

        return self::normalizarCodigoAfip($codigoAfip);
    }

    private static function esRecibo(?string $abreviatura): bool
    {
        return strtoupper(trim((string) $abreviatura)) === self::ABREVIATURA_RECIBO;
    }

    /**
     * Deja el código en dos dígitos con cero a la izquierda ('1' y '002' → '01'
     * y '02'). Se interpreta como número y no se recortan caracteres, porque
     * `substr` sobre '002' devolvería '00'.
     */
    private static function normalizarCodigoAfip(?string $codigoAfip): string
    {
        $digitos = preg_replace('/\D/', '', (string) $codigoAfip) ?? '';
        if ($digitos === '') {
            return '';
        }

        return str_pad((string) (int) $digitos, 2, '0', STR_PAD_LEFT);
    }
}
