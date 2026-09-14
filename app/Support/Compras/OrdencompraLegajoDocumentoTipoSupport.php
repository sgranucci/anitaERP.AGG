<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use App\Support\Compras\Tracking\TrackingComprobanteFamilia;

/**
 * Tipo genérico del documento del legajo (FC / NC / ND) para UI y gates.
 */
final class OrdencompraLegajoDocumentoTipoSupport
{
    /** Informe de recepción / cobranzas: empiezan con C pero no son nota de crédito. */
    private const ABREV_C_NO_NC = ['COM', 'COV'];

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
            // Anita t_comp: F**=factura, C**=crédito (CIS/CGA/CNS), D**=débito.
            if (strlen($abrev) >= 3) {
                $inicial = $abrev[0];
                if ($inicial === 'C' && ! in_array($abrev, self::ABREV_C_NO_NC, true)) {
                    return 'NC';
                }
                if ($inicial === 'D') {
                    return 'ND';
                }
            }

            $porTexto = PrecargaProveedorTipoComprobanteSupport::normalizar($abrev);
            if (in_array($porTexto, ['NC', 'ND', 'REC'], true)) {
                return $porTexto;
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

        if ($codigoAfip !== null && trim((string) $codigoAfip) !== '') {
            $porCodigo = PrecargaProveedorTipoComprobanteSupport::normalizar((string) $codigoAfip);
            if (in_array($porCodigo, ['NC', 'ND', 'REC'], true)) {
                return $porCodigo;
            }
        }

        return 'FC';
    }

    public static function desdeTipotransaccion(?Tipotransaccion_Compra $tipo): string
    {
        if ($tipo === null) {
            return 'FC';
        }

        $abrev = (string) ($tipo->abreviatura ?? '');
        $codigo = $tipo->codigoafip !== null ? (string) $tipo->codigoafip : null;
        $desdeAbrev = self::desdeAbreviatura($abrev, $codigo);
        if ($desdeAbrev !== 'FC') {
            return $desdeAbrev;
        }

        $porNombre = PrecargaProveedorTipoComprobanteSupport::normalizar((string) ($tipo->nombre ?? ''));
        if (in_array($porNombre, ['NC', 'ND', 'REC'], true)) {
            return $porNombre;
        }

        // Signo Resta (NC) aunque la abreviatura no siga la convención C**.
        if ((string) ($tipo->signo ?? 'S') === 'R') {
            return 'NC';
        }

        return $desdeAbrev;
    }

    public static function desdePrecarga(Precarga_Comprobante_Proveedor $pre): string
    {
        $pre->loadMissing('tipotransaccion_compras:id,abreviatura,codigoafip,signo,nombre');

        return self::desdeTipotransaccion($pre->tipotransaccion_compras);
    }

    public static function desdeComprobante(?Comprobante_Proveedor $comprobante): string
    {
        if (! $comprobante) {
            return 'FC';
        }
        if ($comprobante->relationLoaded('tipotransaccion_compras') && $comprobante->tipotransaccion_compras) {
            return self::desdeTipotransaccion($comprobante->tipotransaccion_compras);
        }

        return self::desdeTipotransaccionId(
            (int) ($comprobante->tipotransaccion_compra_id ?? 0)
        );
    }

    public static function desdeTipotransaccionId(?int $tipoId): string
    {
        if (! $tipoId || $tipoId <= 0) {
            return 'FC';
        }
        $tipo = Tipotransaccion_Compra::query()->whereKey($tipoId)->first(['abreviatura', 'codigoafip', 'signo', 'nombre']);

        return self::desdeTipotransaccion($tipo);
    }

    public static function exigeCom(string $tipoGenerico): bool
    {
        $t = self::desdeAbreviatura($tipoGenerico, $tipoGenerico);

        // NC y ND no exigen recepción; REC tampoco.
        return ! in_array($t, ['NC', 'ND', 'REC'], true);
    }

    public static function mensajeSinRecepcionPorTipo(?string $tipoGenerico): string
    {
        return match (self::etiquetaCorta((string) $tipoGenerico)) {
            'NC' => 'Las notas de crédito no requieren recepción COM.',
            'ND' => 'Las notas de débito no requieren recepción COM.',
            'REC' => 'Los recibos no requieren recepción COM.',
            default => 'Este tipo de comprobante no requiere recepción COM.',
        };
    }

    public static function etiquetaCorta(string $tipoGenerico): string
    {
        $t = self::desdeAbreviatura($tipoGenerico, $tipoGenerico);
        $porTexto = PrecargaProveedorTipoComprobanteSupport::normalizar($tipoGenerico);

        return match ($t !== 'FC' ? $t : $porTexto) {
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
        // Evitar "FC FGA A …" / "NC CIS A …" si el número ya trae abreviatura de tipo.
        if (preg_match('/^(FC|NC|ND|REC|REM|F[A-Z]{2}|C[A-Z]{2}|N[CD][A-Z]|D[A-Z]{2})\b/i', $numero)) {
            return $numero;
        }

        return $corta.' '.$numero;
    }
}
