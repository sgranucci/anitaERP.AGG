<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

use App\Support\Compras\ProveedorExclusionAnitaSupport;

/**
 * Separa del mayor las líneas comparables con el archivo SICORE (generación/anulación
 * de retención en pago a proveedores) de pagos DDJJ, compensaciones y reclasificaciones.
 *
 * Comparable: OPP/AOP/OPA/OPV y devoluciones por cheque propio (CHP) que imputan la
 * cuenta de retención, con subd_emisor presente en el maestro de proveedores.
 */
final class SicoreMayorComparableSupport
{
    /** @var list<string> */
    private const TIPOS_PAGO_PROVEEDOR = ['OPP', 'AOP', 'OPA', 'OPV'];

    /**
     * Devolución de retención pagada con cheque (banco vs cuenta 214…), sin AOP en retmov.
     *
     * @var list<string>
     */
    private const TIPOS_DEVOLUCION_CHEQUE = ['CHP'];

    /**
     * Detalles del mayor que no representan retención generada en pago a proveedor.
     *
     * @var list<string>
     */
    private const PATRONES_EXCLUIDOS = [
        '/COMPENSACI[OÓ]N\s+SICORE/iu',
        '/\bSICORE\s*[12]Q\b/iu',
        '/\bSICORE\s+\d{1,2}[\/\-]\d{2,4}\b/iu',
        '/^SICORE[_\s]+SICORE/iu',
        '/\bPRESENTACI[OÓ]N\s+SICORE\b/iu',
        '/\bPAGO\s+SICORE\b/iu',
        '/\bDDJJ\s+SICORE\b/iu',
        // IIBB / ARBA: pago de liquidación quincenal (no es generación de retención).
        // Variantes reales Anita: "RETENCIONES IIBB ARBA 1Q…", "RETENC ARBA 2Q…",
        // "Rete Arba 1Q…", "Arba Ret IIBB 07.26 1Q rsa" (Rebisco).
        '/\bRETENC(?:ION(?:ES)?)?\s+ARBA\b/iu',
        '/\bPERCEPCIONES?\s+ARBA\b/iu',
        '/\bARBA\s+RET\b/iu',
        '/\bARBA\b.{0,40}\b[12]Q\b/iu',
        '/\bIIBB\b.{0,40}\b[12]Q\b/iu',
        '/\bPAGO\s+(DE\s+)?(RETENCIONES?\s+|PERCEPCIONES?\s+)?ARBA\b/iu',
        '/\bPRESENTACI[OÓ]N\s+(ARBA|IIBB)\b/iu',
        '/\bDDJJ\s+(ARBA|IIBB)\b/iu',
        // SUSS: pago de liquidación quincenal (detalle típico "RETENCIONES SUSS BSA 2Q 06.26").
        // La generación en pago a proveedor usa "Pago: … #OP", no este texto.
        '/\bRETENCIONES?\s+SUSS\b/iu',
        '/\bSUSS\s*[12]Q\b/iu',
        '/\bPAGO\s+(DE\s+)?RETENCIONES?\s+SUSS\b/iu',
        '/\bPRESENTACI[OÓ]N\s+SUSS\b/iu',
        '/\bDDJJ\s+SUSS\b/iu',
        '/^RECLA\b/iu',
        '/\bRECLASIF/iu',
    ];

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @param  array<string, true>|null  $emisoresProveedor  Códigos Anita de emisor que existen
     *                                                       en el maestro proveedor; si no es null,
     *                                                       solo quedan OPP/AOP/OPA/OPV con emisor válido.
     * @return array{
     *     comparables: list<array<string, mixed>>,
     *     excluidos: list<array<string, mixed>>,
     *     total_comparable: float,
     *     total_excluido: float
     * }
     */
    public static function particionar(array $movimientos, ?array $emisoresProveedor = null): array
    {
        $comparables = [];
        $excluidos = [];

        foreach ($movimientos as $mov) {
            $detalle = (string) ($mov['detalle'] ?? '');
            $motivo = self::motivoExclusion($detalle);

            if ($motivo === null && $emisoresProveedor !== null) {
                $motivo = self::motivoExclusionNoPagoProveedor($mov, $emisoresProveedor);
            }

            if ($motivo !== null) {
                $excluidos[] = array_merge($mov, [
                    'excluido_comparable' => true,
                    'motivo_exclusion' => $motivo,
                ]);
                continue;
            }

            $comparables[] = array_merge($mov, [
                'excluido_comparable' => false,
                'motivo_exclusion' => null,
            ]);
        }

        return [
            'comparables' => $comparables,
            'excluidos' => $excluidos,
            'total_comparable' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($comparables),
            'total_excluido' => SicoreConciliacionAuditoriaSupport::totalMayorNeto($excluidos),
        ];
    }

    public static function motivoExclusion(string $detalle): ?string
    {
        $detalle = trim($detalle);
        if ($detalle === '') {
            return null;
        }

        foreach (self::PATRONES_EXCLUIDOS as $patron) {
            if (preg_match($patron, $detalle) === 1) {
                return self::etiquetaMotivo($patron, $detalle);
            }
        }

        return null;
    }

    /**
     * OPP/AOP/OPA/OPV o CHP (devolución) con emisor presente en maestro de proveedores.
     *
     * @param  array<string, mixed>  $mov
     * @param  array<string, true>  $emisoresProveedor
     */
    public static function esLineaPagoProveedor(array $mov, array $emisoresProveedor): bool
    {
        $tipo = strtoupper(trim((string) ($mov['subd_tipo'] ?? $mov['tipo_comp'] ?? '')));
        if (! self::esTipoComparableRetencion($tipo)) {
            return false;
        }

        $emisor = self::normalizarEmisor((string) ($mov['subd_emisor'] ?? $mov['emisor'] ?? ''));
        if ($emisor === '') {
            return false;
        }

        return isset($emisoresProveedor[$emisor]);
    }

    public static function esTipoComparableRetencion(string $tipo): bool
    {
        $tipo = strtoupper(trim($tipo));

        return in_array($tipo, self::TIPOS_PAGO_PROVEEDOR, true)
            || in_array($tipo, self::TIPOS_DEVOLUCION_CHEQUE, true);
    }

    public static function esTipoDevolucionCheque(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), self::TIPOS_DEVOLUCION_CHEQUE, true);
    }

    /**
     * @param  array<string, mixed>  $mov
     * @param  array<string, true>  $emisoresProveedor
     */
    public static function motivoExclusionNoPagoProveedor(array $mov, array $emisoresProveedor): ?string
    {
        if (self::esLineaPagoProveedor($mov, $emisoresProveedor)) {
            return null;
        }

        $tipo = strtoupper(trim((string) ($mov['subd_tipo'] ?? $mov['tipo_comp'] ?? '')));
        // CHP con proveedor válido ya pasó esLineaPagoProveedor (devolución comparable).
        // Otros cheques (sin emisor proveedor) no entran al SICORE.
        if ($tipo === 'CHP' || str_starts_with($tipo, 'CH')) {
            return 'cheque_no_pago_proveedor';
        }

        return 'no_pago_proveedor';
    }

    /**
     * Extrae el nº de OP/comprobante tipográfico del detalle Anita (#123066 / OP 123066).
     */
    public static function extraerNroCompDesdeDetalle(string $detalle): int
    {
        if (preg_match('/(?:#|\bOP\s*)(\d+)\b/u', $detalle, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    public static function normalizarEmisor(string $emisor): string
    {
        $emisor = trim($emisor);
        if ($emisor === '') {
            return '';
        }

        return ProveedorExclusionAnitaSupport::codigoAnitaParaBridge($emisor);
    }

    public static function esComparable(string $detalle): bool
    {
        return self::motivoExclusion($detalle) === null;
    }

    private static function etiquetaMotivo(string $patron, string $detalle): string
    {
        $upper = mb_strtoupper($detalle, 'UTF-8');

        if (str_contains($patron, 'COMPENSACI')) {
            return 'compensacion_sicore';
        }
        if (str_contains($patron, 'ARBA') || str_contains($patron, 'IIBB')) {
            return 'pago_arba';
        }
        if (str_contains($patron, 'SUSS')) {
            return 'pago_suss';
        }
        if (str_contains($patron, '[12]Q') || str_contains($patron, 'PRESENTACI') || str_contains($patron, 'PAGO') || str_contains($patron, 'DDJJ')) {
            return 'pago_sicore';
        }
        if (str_contains($patron, 'RECLA')) {
            return 'reclasificacion';
        }
        if (str_contains($upper, 'SICORE')) {
            return 'pago_sicore';
        }
        if (str_contains($upper, 'ARBA') || str_contains($upper, 'IIBB')) {
            return 'pago_arba';
        }

        return 'excluido';
    }
}
