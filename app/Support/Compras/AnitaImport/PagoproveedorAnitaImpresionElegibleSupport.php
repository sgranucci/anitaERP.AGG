<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaImport;

use App\Models\Compras\Pagoproveedor;
use App\Support\Compras\PagoproveedorImputacionApSupport;

/**
 * Solo cabeceras importadas desde Anita (documento sin CC). Nunca OP emitidas en ERP.
 */
final class PagoproveedorAnitaImpresionElegibleSupport
{
    public const ORIGEN_RETENCION = 'anita_impresion';

    public const MOTIVO_RETENCION = 'Importado desde Anita (impresión)';

    public static function esElegible(Pagoproveedor $pago): bool
    {
        // Contabilidad / tesorería ERP = OP nativa: no tocar.
        if ((int) ($pago->asiento_id ?? 0) > 0) {
            return false;
        }
        if ((int) ($pago->caja_movimiento_id ?? 0) > 0) {
            return false;
        }

        if (! self::tieneMarcaImportAnita($pago)) {
            return false;
        }

        // Medios reales ERP (aunque falte asiento): no pisar.
        if ($pago->relationLoaded('caja_movimientos')) {
            if ($pago->caja_movimientos->isNotEmpty()) {
                return false;
            }
        } elseif ($pago->caja_movimientos()->exists()) {
            return false;
        }

        if ($pago->relationLoaded('cheques')) {
            if ($pago->cheques->isNotEmpty()) {
                return false;
            }
        } elseif ($pago->cheques()->exists()) {
            return false;
        }

        return true;
    }

    public static function tieneMarcaImportAnita(Pagoproveedor $pago): bool
    {
        if (PagoproveedorImputacionApSupport::marcaImportAnitaSinCc($pago->detalle)) {
            return true;
        }

        $pago->loadMissing('pagoproveedor_estados');
        foreach ($pago->pagoproveedor_estados as $estado) {
            if (PagoproveedorImputacionApSupport::marcaImportAnitaSinCc($estado->observacion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $detalleCalculo
     */
    public static function esRetencionImportImpresion(?array $detalleCalculo, ?string $motivo = null): bool
    {
        if (is_array($detalleCalculo) && ($detalleCalculo['origen'] ?? '') === self::ORIGEN_RETENCION) {
            return true;
        }

        return trim((string) $motivo) === self::MOTIVO_RETENCION;
    }
}
