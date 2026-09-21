<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\VentaNotaCreditoPrecioLiteralSupport;

/**
 * Facturación Local (Ferli): las listas / FAC B usan precio final (IVA incluido, incluye=1).
 *
 * Una FAC recuperada desde ARCA con ImpNeto + incluye=2 deja el PDF con
 * «PRECIO UNITARIO / IMPORTE BRUTO» ≠ total. La NC de mostrador debe normalizar
 * a la misma convención Local, sin tocar AGG / El Bierzo / gastronomía.
 */
final class FacturacionLocalNcPrecioSupport
{
    /**
     * En calculaFacturaGeneral: si la NC apunta a FAC Local con precio neto, pasa a final + incluye=1.
     *
     * @param  array<string, mixed>  $data
     */
    public static function normalizarPayloadNc(array &$data): void
    {
        $ventaFacId = (int) ($data['venta_id'] ?? 0);
        $tipoId = (int) ($data['tipotransaccion_id'] ?? 0);
        if ($ventaFacId <= 0 || $tipoId <= 0) {
            return;
        }

        $tipo = Tipotransaccion::query()->find($tipoId);
        if (! $tipo || ! $tipo->esNotaCredito()) {
            return;
        }

        if (! FacturacionLocalEmision::query()->where('venta_id', $ventaFacId)->exists()) {
            return;
        }

        if (! isset($data['precios']) || ! is_array($data['precios']) || $data['precios'] === []) {
            return;
        }

        $origenes = Venta_Emision::query()
            ->where('venta_id', $ventaFacId)
            ->orderBy('id')
            ->get(['id', 'articulo_id', 'precio', 'incluyeimpuesto', 'impuesto_id', 'cantidad']);
        if ($origenes->isEmpty()) {
            return;
        }

        if (! isset($data['incluyeimpuestos']) || ! is_array($data['incluyeimpuestos'])) {
            $data['incluyeimpuestos'] = [];
        }

        $porIndice = $origenes->values();
        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];

        foreach ($data['precios'] as $i => $precioRaw) {
            $em = null;
            $emisionId = (int) ($ids[$i] ?? 0);
            if ($emisionId > 0) {
                $em = $origenes->firstWhere('id', $emisionId);
            }
            if ($em === null) {
                $em = $porIndice->get($i);
            }
            if ($em === null) {
                continue;
            }

            $inclOrigen = (string) ($data['incluyeimpuestos'][$i] ?? $em->incluyeimpuesto ?? '');
            if (! FacturacionLocalPrecioIvaSupport::esNeto($inclOrigen)) {
                $data['incluyeimpuestos'][$i] = FacturacionLocalPrecioIvaSupport::INCLUYE_SI;
                continue;
            }

            $precioNeto = (float) str_replace([' ', ','], '', (string) $precioRaw);
            if ($precioNeto <= 0.) {
                $precioNeto = (float) $em->precio;
            }
            $tasa = self::tasaIva((int) ($em->impuesto_id ?? 0));
            $precioFinal = $tasa > 0.
                ? round($precioNeto * (1. + ($tasa / 100.)), 4)
                : $precioNeto;

            $data['precios'][$i] = VentaNotaCreditoPrecioLiteralSupport::formatLiteral($precioFinal);
            $data['incluyeimpuestos'][$i] = FacturacionLocalPrecioIvaSupport::INCLUYE_SI;
        }
    }

    /**
     * En el form de NC: muestra precio final en renglones de FAC Local guardada como neto.
     */
    public static function normalizarEmisionesVistaParaNc(Venta $ventaFac): void
    {
        if (! FacturacionLocalEmision::query()->where('venta_id', (int) $ventaFac->id)->exists()) {
            return;
        }

        $ventaFac->loadMissing('venta_emisiones');
        foreach ($ventaFac->venta_emisiones as $em) {
            $incl = (string) ($em->incluyeimpuesto ?? '');
            if (! FacturacionLocalPrecioIvaSupport::esNeto($incl)) {
                continue;
            }
            $tasa = self::tasaIva((int) ($em->impuesto_id ?? 0));
            if ($tasa <= 0.) {
                continue;
            }
            $em->precio = round((float) $em->precio * (1. + ($tasa / 100.)), 4);
            $em->incluyeimpuesto = FacturacionLocalPrecioIvaSupport::INCLUYE_SI;
        }
    }

    private static function tasaIva(int $impuestoId): float
    {
        if ($impuestoId <= 0) {
            return 21.;
        }

        return (float) (Impuesto::query()->whereKey($impuestoId)->value('valor') ?? 21.);
    }
}
