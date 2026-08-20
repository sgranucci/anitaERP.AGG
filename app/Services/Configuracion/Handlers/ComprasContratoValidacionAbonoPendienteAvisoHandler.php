<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Contrato_Validacion_Abono;
use App\Support\Compras\ContratoPeriodoServicioSupport;

class ComprasContratoValidacionAbonoPendienteAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $val = Contrato_Validacion_Abono::query()->with('ordencompras')->find($entityId);
        $oc = $val?->ordencompras;

        return [
            'empresa_id' => $oc?->empresa_id ? (int) $oc->empresa_id : null,
            'centrocosto_id' => $oc?->centrocosto_id ? (int) $oc->centrocosto_id : null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $val = Contrato_Validacion_Abono::query()
            ->with(['ordencompras.proveedores', 'recepcion_proveedores', 'comprobante_proveedores'])
            ->find($entityId);
        if (! $val) {
            return [
                'numero_oc' => '—',
                'proveedor' => '—',
                'detalle' => '—',
                'periodo' => '—',
                'origen_etiqueta' => '—',
                'origen_numero' => '—',
                'estado' => '—',
            ];
        }
        $oc = $val?->ordencompras;
        $esRecepcion = (int) ($val->recepcion_proveedor_id ?? 0) > 0;
        $origenNumero = $esRecepcion
            ? (string) (optional($val->recepcion_proveedores)->numerorecepcion ?? $val->recepcion_proveedor_id)
            : (string) (optional($val->comprobante_proveedores)->numerocomprobante ?? $val->comprobante_proveedor_id);

        $periodo = '—';
        if ($val && $val->periodo_desde && $val->periodo_hasta) {
            $periodo = $val->periodo_desde->format('d/m/Y').' a '.$val->periodo_hasta->format('d/m/Y');
            if ($val->periodo_modalidad) {
                $periodo = ContratoPeriodoServicioSupport::etiqueta((string) $val->periodo_modalidad).' ('.$periodo.')';
            }
        }

        return [
            'numero_oc' => (string) (optional($oc)->numeroordencompra ?? '—'),
            'proveedor' => (string) (optional(optional($oc)->proveedores)->nombre ?? '—'),
            'detalle' => (string) (optional($oc)->detalle ?? '—'),
            'periodo' => $periodo,
            'origen_etiqueta' => $esRecepcion ? 'COM' : 'Factura',
            'origen_numero' => $origenNumero !== '' ? $origenNumero : '—',
            'estado' => (string) ($val->estado ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        $val = Contrato_Validacion_Abono::query()->find($entityId);
        if (! $val) {
            return null;
        }
        if ((int) ($val->recepcion_proveedor_id ?? 0) > 0) {
            return url('stock/recepcion-proveedor/'.$val->recepcion_proveedor_id.'/validacion-abono');
        }
        if ((int) ($val->comprobante_proveedor_id ?? 0) > 0) {
            return url('compras/comprobante-proveedor/'.$val->comprobante_proveedor_id.'/validacion-abono');
        }

        return null;
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }
}
