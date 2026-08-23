<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra_Articulo;
use App\Support\Compras\ComprobanteProveedorFacturaAnticipadaSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;

/**
 * Arma líneas aplicped factura → PEP (Anita Capex / mayor / hop FIB→PEP←COM).
 */
final class AplicpedFacturaAnitaMapper
{
    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}|null
     */
    public static function clavePepDesdeContexto(ComprobanteProveedorAnitaContext $ctx): ?array
    {
        $nro = (int) $ctx->numeroOrdenCompra();
        if ($nro <= 0) {
            return null;
        }

        $cfg = config('recepcion_proveedor.anita');

        return [
            'tipo' => (string) ($cfg['oc_tipo'] ?? 'PEP'),
            'letra' => (string) ($cfg['oc_letra'] ?? 'X'),
            'sucursal' => (int) ($cfg['oc_sucursal'] ?? 0),
            'nro' => $nro,
        ];
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function claveFactura(ComprobanteProveedorAnitaContext $ctx): array
    {
        return [
            'tipo' => $ctx->tipoComprobante(),
            'letra' => $ctx->letra(),
            'sucursal' => $ctx->sucursal(),
            'nro' => $ctx->numero(),
        ];
    }

    /**
     * a-compprov.c graba_aplicped_factura_anticipada: una fila, orden -1.
     *
     * @return array{
     *     orden_com: int,
     *     penvp_orden: int,
     *     sku: string,
     *     cantidad: float,
     *     penvp_nro_interno: int,
     *     anticipada: bool
     * }
     */
    public static function lineaAnticipadaAnita(): array
    {
        return [
            'orden_com' => RecepcionProveedorAnitaEscrituraSupport::APLICPED_ORDEN_ANTICIPADA,
            'penvp_orden' => RecepcionProveedorAnitaEscrituraSupport::APLICPED_ORDEN_ANTICIPADA,
            'sku' => RecepcionProveedorAnitaEscrituraSupport::APLICPED_ARTICULO_ANTICIPADA,
            'cantidad' => 0.0,
            'penvp_nro_interno' => 0,
            'anticipada' => true,
        ];
    }

    /**
     * @return list<array{
     *     orden_com: int,
     *     penvp_orden: int,
     *     sku: string,
     *     cantidad: float,
     *     penvp_nro_interno: int,
     *     anticipada?: bool
     * }>
     */
    public static function lineas(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_articulos.articulos',
            'ordencompras.ordencompra_articulos.articulos',
        ]);

        if (ComprobanteProveedorFacturaAnticipadaSupport::aplica($comprobante)) {
            return [self::lineaAnticipadaAnita()];
        }

        $lineasOc = $comprobante->ordencompras?->ordencompra_articulos ?? collect();
        $porArticuloId = [];
        $porSku = [];
        foreach ($lineasOc as $lineaOc) {
            /** @var Ordencompra_Articulo $lineaOc */
            $articuloId = (int) ($lineaOc->articulo_id ?? 0);
            if ($articuloId > 0 && ! isset($porArticuloId[$articuloId])) {
                $porArticuloId[$articuloId] = $lineaOc;
            }
            $sku = self::skuDeLineaOc($lineaOc);
            if ($sku !== '' && ! isset($porSku[$sku])) {
                $porSku[$sku] = $lineaOc;
            }
        }

        $resultado = [];
        $ordenFallback = 1;
        foreach ($comprobante->comprobante_proveedor_articulos ?? [] as $lineaFac) {
            $cantidad = abs((float) ($lineaFac->cantidad ?? 0));
            if ($cantidad <= 0) {
                continue;
            }

            $articuloId = (int) ($lineaFac->articulo_id ?? 0);
            $sku = trim((string) ($lineaFac->sku ?? ''));
            if ($sku === '' && $lineaFac->articulos) {
                $sku = trim((string) ($lineaFac->articulos->sku ?? ''));
            }
            $skuNorm = self::normalizarSku($sku);

            $lineaOc = null;
            if ($articuloId > 0 && isset($porArticuloId[$articuloId])) {
                $lineaOc = $porArticuloId[$articuloId];
            } elseif ($skuNorm !== '' && isset($porSku[$skuNorm])) {
                $lineaOc = $porSku[$skuNorm];
            }

            $ordenFac = (int) ($lineaFac->orden ?? 0);
            if ($ordenFac <= 0) {
                $ordenFac = $ordenFallback;
            }
            $ordenFallback++;

            $skuAnita = RecepcionProveedorAnitaEscrituraSupport::skuAnita13(
                $sku !== '' ? $sku : self::skuDeLineaOc($lineaOc)
            );

            $resultado[] = [
                'orden_com' => $ordenFac,
                'penvp_orden' => (int) ($lineaOc?->penvp_orden ?? 0),
                'sku' => $skuAnita,
                'cantidad' => $cantidad,
                'penvp_nro_interno' => (int) ($lineaOc?->penvp_nro_interno ?? 0),
            ];
        }

        if ($resultado === []) {
            // Sin ítems en la factura ERP: espejar líneas OC para llevar penvp_nro_interno / orden / sku.
            $ordenFallback = 1;
            foreach ($lineasOc as $lineaOc) {
                /** @var Ordencompra_Articulo $lineaOc */
                $nroInterno = (int) ($lineaOc->penvp_nro_interno ?? 0);
                $penvpOrden = (int) ($lineaOc->penvp_orden ?? 0);
                $skuRaw = trim((string) ($lineaOc->articulos?->sku ?? ''));

                $resultado[] = [
                    'orden_com' => $ordenFallback,
                    'penvp_orden' => $penvpOrden,
                    'sku' => RecepcionProveedorAnitaEscrituraSupport::skuAnita13($skuRaw),
                    'cantidad' => abs((float) ($lineaOc->cantidad ?? 0)),
                    'penvp_nro_interno' => $nroInterno,
                ];
                $ordenFallback++;
            }
        }

        if ($resultado === []) {
            $resultado[] = [
                'orden_com' => 0,
                'penvp_orden' => 0,
                'sku' => RecepcionProveedorAnitaEscrituraSupport::skuAnita13(''),
                'cantidad' => 0.0,
                'penvp_nro_interno' => 0,
            ];
        }

        return $resultado;
    }

    private static function skuDeLineaOc(?Ordencompra_Articulo $lineaOc): string
    {
        if ($lineaOc === null) {
            return '';
        }

        $sku = trim((string) ($lineaOc->articulos?->sku ?? ''));

        return self::normalizarSku($sku);
    }

    private static function normalizarSku(string $sku): string
    {
        return strtoupper(ltrim(trim($sku), '0')) ?: strtoupper(trim($sku));
    }
}
