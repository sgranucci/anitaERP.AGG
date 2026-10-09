<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use Illuminate\Support\Facades\Log;

/**
 * Emite la NC del cambio solo por las líneas «A devolver», al precio de la grilla.
 * Si no lo editaron, ese precio es el de la factura original. Medio puente NCD.
 * No toca gastronomía AGG.
 */
final class CambioDevolucionMarketplaceNcService
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoService $notaCreditoService,
    ) {
    }

    /**
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirNcOriginal(CambioDevolucionMarketplace $cambio): array
    {
        $cambio->loadMissing(['localVenta', 'ventaOriginal']);
        $ventaOriginalId = (int) $cambio->venta_original_id;
        if ($ventaOriginalId <= 0) {
            return ['ok' => false, 'error' => 'Sin factura original.'];
        }

        /** @var Venta|null $venta */
        $venta = $cambio->ventaOriginal;
        if (! $venta) {
            return ['ok' => false, 'error' => 'Factura original inexistente.'];
        }

        $motivo = MotivoDevolucionSupport::resolverDeCambio($cambio);
        if (! $motivo) {
            return ['ok' => false, 'error' => 'Elegí un motivo de devolución activo antes de emitir la nota de crédito.'];
        }

        $cambio->loadMissing('lineas');
        $venta->loadMissing('venta_emisiones');
        try {
            $aDevolver = $this->lineasADevolver($cambio, $venta);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $total = 0.;
        foreach ($aDevolver['cantidades'] as $emisionId => $cantidad) {
            $precio = (float) ($aDevolver['precios'][$emisionId] ?? 0);
            $total = round($total + round($cantidad * $precio, 2), 2);
        }
        try {
            $medios = CambioDevolucionMarketplacePuenteSupport::medioPagoUnico($total);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $resultado = $this->notaCreditoService->generarDesdeFactura(
            $ventaOriginalId,
            null,
            'Cambio/devolución marketplace legajo Nº '.$cambio->numero,
            [
                'medios_forzados' => $medios,
                'local_venta_id' => (int) $cambio->local_venta_id,
                'motivo_devolucion_id' => (int) $motivo->id,
                'omitir_reingreso_stock' => ! $motivo->vuelve_stock,
                'registrar_historial' => false,
                'venta_emision_ids' => $aDevolver['ids'],
                'cantidades_por_emision' => $aDevolver['cantidades'],
                'precios_por_emision' => $aDevolver['precios'],
            ]
        );

        if (empty($resultado['ok'])) {
            Log::warning('cambio_devolucion_marketplace.nc_original.fallo', [
                'cambio_id' => $cambio->id,
                'venta_original_id' => $ventaOriginalId,
                'error' => $resultado['error'] ?? null,
            ]);

            return [
                'ok' => false,
                'error' => (string) ($resultado['error'] ?? 'No se pudo emitir la nota de crédito.'),
            ];
        }

        return [
            'ok' => true,
            'venta_id' => (int) ($resultado['venta_id'] ?? 0),
            'factura' => (string) ($resultado['factura'] ?? ''),
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
            'mensaje' => $resultado['mensaje'] ?? 'Nota de crédito emitida con medio puente NCD.',
        ];
    }

    /**
     * Solo las líneas «A devolver». El precio es el de la grilla (producto, o el de la factura si no lo cambiaron).
     *
     * @return array{ids:list<int>,cantidades:array<int,float>,precios:array<int,float>}
     */
    private function lineasADevolver(CambioDevolucionMarketplace $cambio, Venta $venta): array
    {
        $lineas = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)
            ->values();
        if ($lineas->isEmpty()) {
            throw new \InvalidArgumentException('No hay líneas «A devolver» para la nota de crédito.');
        }

        /** @var \Illuminate\Support\Collection<int, Venta_Emision> $emisiones */
        $emisiones = $venta->venta_emisiones->keyBy('id');
        $ids = [];
        $cantidades = [];
        $precios = [];

        foreach ($lineas as $linea) {
            $emision = $this->emisionDeLinea($linea, $emisiones, $ids);
            $emisionId = (int) $emision->id;
            $facturada = (float) ($emision->cantidad ?? 0);
            $cantidad = (float) ($linea->cantidad ?? 0);
            if ($cantidad <= 0) {
                throw new \InvalidArgumentException('La cantidad a devolver debe ser mayor a cero.');
            }
            if ($cantidad - $facturada > 0.0001) {
                throw new \InvalidArgumentException('La cantidad a devolver supera la facturada en '.$emision->detalle.'.');
            }
            $ids[] = $emisionId;
            $cantidades[$emisionId] = round(($cantidades[$emisionId] ?? 0) + $cantidad, 4);
            $precioGrilla = (float) ($linea->precio_unitario ?? 0);
            $precios[$emisionId] = $precioGrilla > 0
                ? $precioGrilla
                : (float) ($emision->precio ?? 0);
            if ($cantidades[$emisionId] - $facturada > 0.0001) {
                throw new \InvalidArgumentException('La cantidad a devolver supera la facturada en '.$emision->detalle.'.');
            }
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'cantidades' => $cantidades,
            'precios' => $precios,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Venta_Emision>  $emisiones
     * @param  list<int>  $yaElegidas
     */
    private function emisionDeLinea($linea, $emisiones, array $yaElegidas): Venta_Emision
    {
        $emisionId = (int) ($linea->venta_emision_id ?? 0);
        if ($emisionId > 0) {
            $emision = $emisiones->get($emisionId);
            if (! $emision instanceof Venta_Emision) {
                throw new \InvalidArgumentException('Una línea a devolver no pertenece a la factura original.');
            }

            return $emision;
        }

        $articuloId = (int) ($linea->articulo_id ?? 0);
        $combinacionId = (int) ($linea->combinacion_id ?? 0);
        $talleId = (int) ($linea->talle_id ?? 0);
        $colorId = (int) ($linea->color_id ?? 0);
        $candidata = $emisiones->first(function (Venta_Emision $em) use ($articuloId, $combinacionId, $talleId, $colorId, $yaElegidas) {
            if (in_array((int) $em->id, $yaElegidas, true)) {
                return false;
            }

            return (int) $em->articulo_id === $articuloId
                && (int) ($em->combinacion_id ?? 0) === $combinacionId
                && (int) ($em->talle_id ?? 0) === $talleId
                && (int) ($em->color_id ?? 0) === $colorId;
        });
        if (! $candidata instanceof Venta_Emision) {
            throw new \InvalidArgumentException('No se encontró en la factura original el artículo a devolver.');
        }

        return $candidata;
    }
}
