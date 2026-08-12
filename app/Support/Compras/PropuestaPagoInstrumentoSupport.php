<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Formapago;
use App\Models\Ventas\Formapago;

/**
 * Resuelve instrumento sugerido (CBU / cuenta proveedor) para ejecución de propuesta.
 */
class PropuestaPagoInstrumentoSupport
{
    /**
     * Cuenta bancaria del proveedor alineada a la forma de pago de la línea/OC.
     */
    public static function resolverFormapagoProveedor(int $proveedorId, ?int $formapagoId): ?Proveedor_Formapago
    {
        if ($proveedorId <= 0) {
            return null;
        }

        $base = Proveedor_Formapago::query()->where('proveedor_id', $proveedorId);

        if ($formapagoId && $formapagoId > 0) {
            $conForma = (clone $base)->where('formapago_id', $formapagoId)
                ->orderByRaw("CASE WHEN cbu IS NULL OR cbu = '' THEN 1 ELSE 0 END")
                ->orderBy('id')
                ->first();
            if ($conForma) {
                return $conForma;
            }
        }

        // Preferir transferencia con CBU
        $idsTransf = Formapago::idsTransferencia();
        if ($idsTransf !== []) {
            $transf = (clone $base)->whereIn('formapago_id', $idsTransf)
                ->whereNotNull('cbu')
                ->where('cbu', '!=', '')
                ->orderBy('id')
                ->first();
            if ($transf) {
                return $transf;
            }
        }

        return (clone $base)
            ->orderByRaw("CASE WHEN cbu IS NULL OR cbu = '' THEN 1 ELSE 0 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Texto para detalle OP / observación de cuenta caja.
     */
    public static function textoInstrumento(?Proveedor_Formapago $fp, string $medioEtiqueta = ''): string
    {
        if (! $fp) {
            return $medioEtiqueta !== '' ? $medioEtiqueta : '';
        }

        $partes = [];
        if ($medioEtiqueta !== '') {
            $partes[] = $medioEtiqueta;
        }
        if (trim((string) $fp->nombre) !== '') {
            $partes[] = trim((string) $fp->nombre);
        }
        if (trim((string) ($fp->alias_cbu ?? '')) !== '') {
            $partes[] = 'Alias '.$fp->alias_cbu;
        }
        if (trim((string) $fp->cbu) !== '') {
            $partes[] = 'CBU '.$fp->cbu;
        }
        if (trim((string) $fp->numerocuenta) !== '') {
            $partes[] = 'Cta '.$fp->numerocuenta;
        }

        return implode(' | ', $partes);
    }

    public static function esTransferencia(?int $formapagoId): bool
    {
        return Formapago::esTransferencia($formapagoId);
    }

    public static function esCheque(?int $formapagoId, string $medioEtiqueta = ''): bool
    {
        if ($medioEtiqueta !== '') {
            $u = mb_strtoupper($medioEtiqueta);
            if (str_contains($u, 'CHEQUE') || $u === 'C' || $u === 'CH' || str_contains($u, 'V.CHEQ')) {
                return true;
            }
        }
        if (! $formapagoId || $formapagoId <= 0) {
            return false;
        }
        $fp = Formapago::query()->find($formapagoId);
        if (! $fp) {
            return false;
        }
        $u = mb_strtoupper((string) ($fp->abreviatura.' '.$fp->nombre));

        return str_contains($u, 'CHEQUE') || $fp->abreviatura === 'C' || $fp->abreviatura === 'CH';
    }
}
