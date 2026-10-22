<?php

namespace App\Support\Solicitudpago;

use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use App\Support\Database\EloquentAuditDeleteSupport;

/**
 * Copia el snapshot de retenciones de la SP a la OPA y numera el certificado.
 */
final class SolicitudpagoRetencionOpaSupport
{
    public static function volcar(Pagoproveedor $pago, Solicitudpago $sp, int $monedaId, float $cotizacion): void
    {
        EloquentAuditDeleteSupport::each(
            Pagoproveedor_Retencion::query()->where('pagoproveedor_id', $pago->id)
        );

        if (SolicitudpagoRetencionCalculoSupport::totalImportes($sp) < 0.01) {
            return;
        }

        $sp->loadMissing('retenciones');
        $empresaId = (int) $pago->empresa_id;
        $cotizacion = $cotizacion > 0 ? $cotizacion : 1.0;
        $monedaId = $monedaId > 0 ? $monedaId : (int) ($pago->moneda_id ?: 1);

        $nroIva = null;
        $nroSuss = null;
        $nroIibb = null;
        $provinciaIibb = null;

        foreach ($sp->retenciones as $ret) {
            $importe = round((float) ($ret->importe ?? 0), 2);
            if ($importe < 0.01) {
                continue;
            }
            $tipo = (string) $ret->tiporetencion;
            $nro = null;
            if ($tipo === Pagoproveedor_Retencion::TIPO_GANANCIAS) {
                $nro = PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock($tipo, $empresaId);
            } elseif ($tipo === Pagoproveedor_Retencion::TIPO_IVA) {
                $nroIva ??= PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock($tipo, $empresaId);
                $nro = $nroIva;
            } elseif ($tipo === Pagoproveedor_Retencion::TIPO_SUSS) {
                $nroSuss ??= PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock($tipo, $empresaId);
                $nro = $nroSuss;
            } elseif ($tipo === Pagoproveedor_Retencion::TIPO_IIBB) {
                $provinciaId = (int) ($ret->provincia_id ?? 0);
                if ($nroIibb === null || $provinciaId !== $provinciaIibb) {
                    $nroIibb = PagoproveedorAnitaRetencionNumeracionSupport::siguienteNumeroConLock($tipo, $empresaId);
                    $provinciaIibb = $provinciaId;
                }
                $nro = $nroIibb;
            }

            Pagoproveedor_Retencion::query()->create([
                'pagoproveedor_id' => $pago->id,
                'tiporetencion' => $tipo,
                'retencionganancia_id' => $ret->retencionganancia_id,
                'retencioniva_id' => $ret->retencioniva_id,
                'retencionsuss_id' => $ret->retencionsuss_id,
                'provincia_id' => $ret->provincia_id,
                'codigo_regimen' => $ret->codigo_regimen,
                'codigo_retencion' => $ret->codigo_retencion,
                'base_calculo' => $ret->base_calculo,
                'alicuota' => $ret->alicuota,
                'importe' => $importe,
                'nro_certificado' => $nro !== null ? (string) $nro : null,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'detalle_calculo' => $ret->detalle_calculo,
                'motivo' => $ret->motivo,
            ]);
        }
    }
}
