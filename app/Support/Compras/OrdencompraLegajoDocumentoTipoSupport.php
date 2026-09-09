<?php

namespace App\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use App\Support\Compras\Tracking\TrackingComprobanteFamilia;

/**
 * Tipo genérico del documento del legajo (FC / NC / ND) para UI y gates.
 */
final class OrdencompraLegajoDocumentoTipoSupport
{
    public static function desdeAbreviatura(?string $abreviatura, ?string $codigoAfip = null): string
    {
        $abrev = strtoupper(trim((string) $abreviatura));
        if ($abrev !== '') {
            if (str_starts_with($abrev, 'NC')) {
                return PrecargaProveedorTipoComprobanteSupport::normalizar('NC');
            }
            if (str_starts_with($abrev, 'ND')) {
                return PrecargaProveedorTipoComprobanteSupport::normalizar('ND');
            }
            if (str_starts_with($abrev, 'REC')) {
                return 'REC';
            }
        }

        $familia = TrackingComprobanteFamilia::desde($codigoAfip, $abreviatura);
        if ($familia === TrackingComprobanteFamilia::NOTA_CREDITO) {
            return 'NC';
        }
        if ($familia === TrackingComprobanteFamilia::NOTA_DEBITO) {
            return 'ND';
        }
        if ($familia === TrackingComprobanteFamilia::RECIBO) {
            return 'REC';
        }

        return 'FC';
    }

    public static function desdePrecarga(Precarga_Comprobante_Proveedor $pre): string
    {
        $pre->loadMissing('tipotransaccion_compras:id,abreviatura,codigoafip');
        $tipo = $pre->tipotransaccion_compras;

        return self::desdeAbreviatura(
            $tipo?->abreviatura,
            $tipo?->codigoafip !== null ? (string) $tipo->codigoafip : null
        );
    }

    public static function desdeTipotransaccionId(?int $tipoId): string
    {
        if (! $tipoId || $tipoId <= 0) {
            return 'FC';
        }
        $tipo = Tipotransaccion_Compra::query()->whereKey($tipoId)->first(['abreviatura', 'codigoafip']);

        return self::desdeAbreviatura(
            $tipo?->abreviatura,
            $tipo?->codigoafip !== null ? (string) $tipo->codigoafip : null
        );
    }

    public static function exigeCom(string $tipoGenerico): bool
    {
        $t = PrecargaProveedorTipoComprobanteSupport::normalizar($tipoGenerico);

        // NC y ND no exigen recepción; REC tampoco.
        return ! in_array($t, ['NC', 'ND', 'REC'], true);
    }

    public static function etiquetaCorta(string $tipoGenerico): string
    {
        return match (PrecargaProveedorTipoComprobanteSupport::normalizar($tipoGenerico)) {
            'NC' => 'NC',
            'ND' => 'ND',
            'REC' => 'REC',
            'REM' => 'REM',
            default => 'FC',
        };
    }

    /** Prioridad de carga: FC/ND antes que NC. */
    public static function prioridadCarga(string $tipoGenerico): int
    {
        return match (PrecargaProveedorTipoComprobanteSupport::normalizar($tipoGenerico)) {
            'FC', 'ND', 'REM' => 0,
            'REC' => 1,
            'NC' => 2,
            default => 0,
        };
    }

    public static function numeroConTipo(string $tipoGenerico, string $numero): string
    {
        $numero = trim($numero);
        $corta = self::etiquetaCorta($tipoGenerico);
        if ($numero === '') {
            return $corta;
        }
        // Evitar "FC FGA A …" / "FC FIS A …" si el número ya trae abreviatura de tipo.
        if (preg_match('/^(FC|NC|ND|REC|REM|F[A-Z]{2}|N[CD][A-Z]|D[A-Z]{2})\b/i', $numero)) {
            return $numero;
        }

        return $corta.' '.$numero;
    }
}
