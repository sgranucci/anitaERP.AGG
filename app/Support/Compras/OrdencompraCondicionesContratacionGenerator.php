<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;

/**
 * Texto legible para el cuadro "Condiciones de contratación" (persistible en ordencompra.condiciones_contratacion).
 * Implementación determinística a partir de comprobantes a venir y cuotas; se puede sustituir por llamada a IA externa.
 */
final class OrdencompraCondicionesContratacionGenerator
{
    public static function desdeModelo(Ordencompra $oc): string
    {
        $oc->loadMissing([
            'ordencompra_comprobantes.monedas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.monedas',
        ]);

        $bloques = [];
        foreach ($oc->ordencompra_comprobantes as $c) {
            $tipo = (string) $c->tipocomprobante;
            $mon = optional($c->monedas)->abreviatura ?? '';
            $monto = number_format((float) $c->monto, 2, ',', '.');
            $vto = $c->fechavencimiento ? date('d/m/Y', strtotime((string) $c->fechavencimiento)) : '—';
            $lineas = ["Comprobante a venir tipo «{$tipo}» por {$mon} {$monto}, vencimiento referencia {$vto}."];

            $cuotas = $c->ordencompra_comprobante_cuotas ?? [];
            if (count($cuotas) === 0) {
                $lineas[] = 'Sin cuotas detalladas cargadas para este comprobante.';
            } else {
                $lineas[] = 'Cuotas previstas:';
                $n = 1;
                foreach ($cuotas as $q) {
                    $fp = optional($q->formapagos)->nombre ?? '—';
                    $mq = number_format((float) $q->monto, 2, ',', '.');
                    $monq = optional($q->monedas)->abreviatura ?? $mon;
                    $fv = $q->fechavencimiento ? date('d/m/Y', strtotime((string) $q->fechavencimiento)) : '—';
                    $det = trim((string) ($q->detalle ?? ''));
                    $lineas[] = "  {$n}) Vto. {$fv}, {$monq} {$mq}, forma de pago: {$fp}".($det !== '' ? ". Detalle: {$det}" : '').'.';
                    $n++;
                }
            }

            $bloques[] = implode("\n", $lineas);
        }

        if ($bloques === []) {
            return 'No hay comprobantes a venir cargados. Cuando agregue facturas o notas previstas con sus cuotas, aquí se resumirán las condiciones de pago de forma clara para el proveedor y compras.';
        }

        return "Resumen de condiciones de contratación (pagos previstos)\n\n".implode("\n\n", $bloques);
    }
}
