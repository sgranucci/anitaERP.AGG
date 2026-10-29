<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceLinea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioArticuloSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use Illuminate\Support\Facades\Log;

/**
 * Emite la NC del cambio solo por las líneas «A devolver». Medio puente NCD.
 * El precio sale de la opción elegida al emitir (neto de la factura, lleno de esa factura o lleno actual).
 * No toca gastronomía AGG.
 */
final class CambioDevolucionMarketplaceNcService
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoService $notaCreditoService,
    ) {
    }

    /**
     * Nota de crédito de la factura de reemplazo, por un error de carga.
     * Acredita lo facturado y reingresa el stock para poder facturar de nuevo.
     *
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirNcReemplazo(CambioDevolucionMarketplace $cambio): array
    {
        $cambio->loadMissing(['localVenta', 'ventaReemplazo']);
        $ventaId = (int) ($cambio->venta_reemplazo_id ?? 0);
        if ($ventaId <= 0) {
            return ['ok' => false, 'error' => 'No hay factura de reemplazo vigente.'];
        }

        $motivo = MotivoDevolucionSupport::resolverDeCambio($cambio);
        if (! $motivo) {
            return ['ok' => false, 'error' => 'Elegí un motivo de devolución activo antes de emitir la nota de crédito.'];
        }

        /** @var Venta|null $venta */
        $venta = $cambio->ventaReemplazo ?: Venta::query()->find($ventaId);
        if (! $venta) {
            return ['ok' => false, 'error' => 'Factura de reemplazo inexistente.'];
        }

        $total = round(abs((float) $venta->total), 2);
        if ($total <= 0) {
            return ['ok' => false, 'error' => 'La factura de reemplazo no tiene importe.'];
        }

        try {
            $medios = CambioDevolucionMarketplacePuenteSupport::medioPagoUnico($total);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $resultado = $this->notaCreditoService->generarDesdeFactura(
            $ventaId,
            null,
            'Error de reemplazo. Legajo Nº '.$cambio->numero,
            [
                'medios_forzados' => $medios,
                'local_venta_id' => (int) $cambio->local_venta_id,
                'motivo_devolucion_id' => (int) $motivo->id,
                'omitir_reingreso_stock' => false,
                'registrar_historial' => false,
            ]
        );

        if (empty($resultado['ok'])) {
            Log::warning('cambio_devolucion_marketplace.nc_reemplazo.fallo', [
                'cambio_id' => $cambio->id,
                'venta_reemplazo_id' => $ventaId,
                'error' => $resultado['error'] ?? null,
            ]);

            return [
                'ok' => false,
                'error' => (string) ($resultado['error'] ?? 'No se pudo emitir la nota de crédito del reemplazo.'),
            ];
        }

        return [
            'ok' => true,
            'venta_id' => (int) ($resultado['venta_id'] ?? 0),
            'factura' => (string) ($resultado['factura'] ?? ''),
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
            'mensaje' => (string) ($resultado['mensaje'] ?? 'Nota de crédito del reemplazo emitida.'),
        ];
    }

    /**
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirNcOriginal(CambioDevolucionMarketplace $cambio, ?string $modoPrecio = null): array
    {
        $modo = CambioDevolucionMarketplacePrecioSupport::normalizar($modoPrecio);
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
            $aDevolver = $this->lineasADevolver($cambio, $venta, $modo);
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
                'precios_finales' => CambioDevolucionMarketplacePrecioSupport::esPrecioFinal($modo),
                'omitir_tope_factura' => CambioDevolucionMarketplacePrecioSupport::esPrecioFinal($modo),
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

        $this->persistirPreciosAcreditados($aDevolver['precios_linea'] ?? []);

        return [
            'ok' => true,
            'venta_id' => (int) ($resultado['venta_id'] ?? 0),
            'factura' => (string) ($resultado['factura'] ?? ''),
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
            'modo_precio' => $modo,
            'mensaje' => trim(
                ($resultado['mensaje'] ?? 'Nota de crédito emitida con medio puente NCD.')
                .' '.CambioDevolucionMarketplacePrecioSupport::etiqueta($modo).'.'
                .' '.($aDevolver['aviso'] ?? '')
            ),
        ];
    }

    /**
     * Solo las líneas «A devolver».
     * Modo neto: un precio de grilla menor al facturado se acredita; por encima, lo cobrado.
     * Precio lleno: el unitario elegido, sin reaplicar el descuento de la factura.
     *
     * @return array{ids:list<int>,cantidades:array<int,float>,precios:array<int,float>,precios_linea:array<int,float>,aviso:string}
     */
    private function lineasADevolver(CambioDevolucionMarketplace $cambio, Venta $venta, string $modo): array
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
        $preciosLinea = [];
        $avisos = [];
        $precioFinal = CambioDevolucionMarketplacePrecioSupport::esPrecioFinal($modo);

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
            $precioFacturado = round((float) ($emision->precio ?? 0), 4);
            $nombre = trim((string) ($emision->detalle ?: $linea->descripcion ?: 'ítem'));
            if ($precioFinal) {
                $precioActual = null;
                if ($modo === CambioDevolucionMarketplacePrecioSupport::MODO_LLENO_ACTUAL) {
                    $precioActual = $this->precioActualDeLinea($cambio, $linea);
                }
                $precio = CambioDevolucionMarketplacePrecioSupport::unitario(
                    $modo,
                    $precioFacturado,
                    (float) ($emision->descuento ?? 0),
                    $precioActual,
                );
                if ($precio === null || $precio <= 0) {
                    throw new \InvalidArgumentException(
                        $modo === CambioDevolucionMarketplacePrecioSupport::MODO_LLENO_ACTUAL
                            ? 'No hay precio de lista vigente para '.$nombre.'.'
                            : 'No se pudo resolver el precio lleno de la factura para '.$nombre.'.'
                    );
                }
            } else {
                $precioGrilla = (float) ($linea->precio_unitario ?? 0);
                $precio = CambioDevolucionMarketplacePrecioSupport::precioAcreditable($precioGrilla, $precioFacturado);
                if ($precioFacturado > 0 && $precioGrilla - $precioFacturado > 0.02) {
                    $avisos[] = $nombre
                        .': $ '.number_format($precioGrilla, 2, ',', '.')
                        .' del legajo, se acredita $ '.number_format($precio, 2, ',', '.')
                        .' de la factura';
                }
            }
            $precios[$emisionId] = $precio;
            $preciosLinea[(int) $linea->id] = $precio;
            if ($cantidades[$emisionId] - $facturada > 0.0001) {
                throw new \InvalidArgumentException('La cantidad a devolver supera la facturada en '.$emision->detalle.'.');
            }
        }

        $aviso = '';
        if ($avisos !== []) {
            $aviso = 'El precio del legajo superaba lo facturado en el renglón. '
                .implode('; ', $avisos).'.';
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'cantidades' => $cantidades,
            'precios' => $precios,
            'precios_linea' => $preciosLinea,
            'aviso' => $aviso,
        ];
    }

    private function precioActualDeLinea(CambioDevolucionMarketplace $cambio, $linea): ?float
    {
        $cot = FacturacionLocalPrecioArticuloSupport::paraLocal(
            (int) $cambio->local_venta_id,
            (int) ($linea->articulo_id ?? 0),
            (int) ($linea->combinacion_id ?? 0),
            (int) ($linea->talle_id ?? 0),
        );
        $precio = round((float) ($cot['precio'] ?? 0), 4);

        return $precio > 0 ? $precio : null;
    }

    /**
     * @param  array<int, float>  $preciosLinea
     */
    private function persistirPreciosAcreditados(array $preciosLinea): void
    {
        foreach ($preciosLinea as $lineaId => $precio) {
            $lineaId = (int) $lineaId;
            if ($lineaId <= 0) {
                continue;
            }
            $linea = CambioDevolucionMarketplaceLinea::query()->find($lineaId);
            if ($linea === null) {
                continue;
            }
            $linea->precio_unitario = round((float) $precio, 4);
            $linea->save();
        }
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
