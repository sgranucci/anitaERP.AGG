<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceLinea;
use App\Models\Ventas\LocalVenta;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioArticuloSupport;
use Illuminate\Support\Facades\Log;

/**
 * Emite FAC de reemplazo del legajo con medio puente NCD (solo FL Ferli).
 */
final class CambioDevolucionMarketplaceEmisionService
{
    public function __construct(
        private readonly FacturacionLocalEmisionService $emisionService,
    ) {
    }

    /**
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirFacReemplazo(CambioDevolucionMarketplace $cambio, ?string $modoPrecio = null): array
    {
        $modo = CambioDevolucionMarketplacePrecioSupport::normalizar($modoPrecio);
        $cambio->loadMissing(['lineas.articulo', 'localVenta', 'ventaOriginal.venta_emisiones']);
        /** @var LocalVenta|null $local */
        $local = $cambio->localVenta;
        if (! $local) {
            return ['ok' => false, 'error' => 'Local de venta inexistente.'];
        }

        $lineasReemplazo = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO)
            ->values();
        if ($lineasReemplazo->isEmpty()) {
            return ['ok' => false, 'error' => 'No hay líneas de reemplazo para facturar.'];
        }

        $emisiones = $cambio->ventaOriginal?->venta_emisiones?->keyBy('id') ?? collect();
        $devolverPendientes = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)
            ->values();
        $devolverUsados = [];
        $ajustes = [];

        $lineas = [];
        $total = 0.0;
        $avisos = [];
        foreach ($lineasReemplazo as $linea) {
            $cant = (float) $linea->cantidad;
            $anterior = (float) $linea->precio_unitario;
            $par = $this->parDeDevolucion($devolverPendientes, $linea, $devolverUsados);
            $resuelto = $this->precioDeReemplazo($modo, $cambio, $linea, $par, $emisiones);
            if ($resuelto['error'] !== null) {
                return ['ok' => false, 'error' => $resuelto['error']];
            }
            $precio = (float) $resuelto['precio'];
            if (abs($precio - $anterior) > 0.02) {
                $ajustes[] = [
                    'reemplazo' => $linea,
                    'precio' => $precio,
                    'anterior' => $anterior,
                ];
            }
            if ($resuelto['aviso'] !== '') {
                $avisos[] = $resuelto['aviso'];
            }
            $total += $cant * $precio;
            $lineas[] = [
                'articulo_id' => (int) $linea->articulo_id,
                'cantidad' => $cant,
                'precio' => $precio,
                'descuento' => 0,
                'descripcion' => (string) ($linea->descripcion ?? ''),
                'combinacion_id' => (int) ($linea->combinacion_id ?? 0),
                'talle_id' => (int) ($linea->talle_id ?? 0),
                'color_id' => (int) ($linea->color_id ?? 0),
            ];
        }

        try {
            $medios = CambioDevolucionMarketplacePuenteSupport::medioPagoUnico($total);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $ventaOriginal = $cambio->ventaOriginal;
        $doc = preg_replace('/\D/', '', (string) ($cambio->receptor_documento ?: $ventaOriginal?->nroinscripcion ?: '')) ?? '';
        $input = [
            'lineas' => $lineas,
            'medios_pago' => $medios,
            'cliente_id' => $cambio->cliente_id,
            'receptor' => [
                'nombre' => trim((string) ($cambio->receptor_nombre ?: $ventaOriginal?->nombre ?: '')),
                'nro_documento' => $doc,
                'domicilio' => (string) ($ventaOriginal?->domicilio ?? ''),
                'provincia_id' => (int) ($ventaOriginal?->provincia_id ?? 0) ?: null,
                'localidad_id' => (int) ($ventaOriginal?->localidad_id ?? 0) ?: null,
            ],
            'alinear_medio_al_total' => true,
        ];

        $resultado = $this->emisionService->emitir($local, $input);
        if (empty($resultado['ok'])) {
            Log::warning('cambio_devolucion_marketplace.fac_reemplazo.fallo', [
                'cambio_id' => $cambio->id,
                'error' => $resultado['error'] ?? null,
            ]);

            return [
                'ok' => false,
                'error' => (string) ($resultado['error'] ?? 'No se pudo emitir la factura de reemplazo.'),
            ];
        }

        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        $factura = '';
        if ($ventaId > 0) {
            $venta = \App\Models\Ventas\Venta::query()->find($ventaId);
            $factura = trim((string) ($venta->codigo ?? ''));
        }

        $aviso = $this->persistirPreciosFacturados($ajustes);
        $detalle = trim(implode(' ', array_filter([
            CambioDevolucionMarketplacePrecioSupport::etiqueta($modo).'.',
            $aviso,
            $avisos !== [] ? implode(' ', $avisos) : '',
        ])));

        return [
            'ok' => true,
            'venta_id' => $ventaId,
            'factura' => $factura,
            'pdf_urls' => $ventaId > 0 ? [url('ventas/listaunafactura/'.$ventaId)] : [],
            'mensaje' => trim('Factura de reemplazo emitida con medio puente NCD. '.$detalle),
            'modo_precio' => $modo,
        ];
    }

    /**
     * El par que se lleva toma el precio de una línea «A devolver» todavía libre.
     * Primero el mismo artículo; si el reemplazo es otro modelo, la siguiente línea a devolver.
     *
     * @param  \Illuminate\Support\Collection<int, CambioDevolucionMarketplaceLinea>  $devolver
     * @param  list<int>  $usados
     */
    private function parDeDevolucion($devolver, CambioDevolucionMarketplaceLinea $reemplazo, array &$usados): ?CambioDevolucionMarketplaceLinea
    {
        $par = $this->devolverMismoArticulo($devolver, (int) $reemplazo->articulo_id, $usados);
        if (! $par instanceof CambioDevolucionMarketplaceLinea) {
            $par = $devolver->first(function (CambioDevolucionMarketplaceLinea $linea) use ($usados) {
                return ! in_array((int) $linea->id, $usados, true);
            });
        }
        if ($par instanceof CambioDevolucionMarketplaceLinea) {
            $usados[] = (int) $par->id;

            return $par;
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Ventas\Venta_Emision>  $emisiones
     * @return array{precio:float,error:?string,aviso:string}
     */
    private function precioDeReemplazo(
        string $modo,
        CambioDevolucionMarketplace $cambio,
        CambioDevolucionMarketplaceLinea $linea,
        ?CambioDevolucionMarketplaceLinea $par,
        $emisiones,
    ): array {
        $nombre = trim((string) ($linea->descripcion ?: $linea->articulo?->descripcion ?: 'el artículo'));
        $precioActual = null;
        if ($modo === CambioDevolucionMarketplacePrecioSupport::MODO_LLENO_ACTUAL) {
            $precioActual = $this->precioActualDeLinea($cambio, $linea);
            if ($precioActual === null) {
                return [
                    'precio' => 0,
                    'error' => 'No hay precio de lista vigente para '.$nombre.'.',
                    'aviso' => '',
                ];
            }
        }

        $facturado = 0.0;
        $descuento = 0.0;
        if ($par instanceof CambioDevolucionMarketplaceLinea) {
            $emision = $this->emisionDeLineaDevolver($par, $emisiones);
            $facturado = round((float) ($emision?->precio ?? 0), 4);
            $descuento = (float) ($emision?->descuento ?? 0);
        }

        if ($modo !== CambioDevolucionMarketplacePrecioSupport::MODO_LLENO_ACTUAL && ! $par instanceof CambioDevolucionMarketplaceLinea) {
            $propio = round((float) $linea->precio_unitario, 4);

            return [
                'precio' => $propio > 0 ? $propio : 0,
                'error' => $propio > 0 ? null : 'La línea de reemplazo '.$nombre.' no tiene precio.',
                'aviso' => $nombre.': sin par en la factura original, se usó el precio de la línea.',
            ];
        }

        $unitario = CambioDevolucionMarketplacePrecioSupport::unitario($modo, $facturado, $descuento, $precioActual);
        if ($unitario === null || $unitario <= 0) {
            return [
                'precio' => 0,
                'error' => 'No se pudo resolver el precio de '.$nombre.' con la opción elegida.',
                'aviso' => '',
            ];
        }

        return ['precio' => $unitario, 'error' => null, 'aviso' => ''];
    }

    private function precioActualDeLinea(CambioDevolucionMarketplace $cambio, CambioDevolucionMarketplaceLinea $linea): ?float
    {
        $cot = FacturacionLocalPrecioArticuloSupport::paraLocal(
            (int) $cambio->local_venta_id,
            (int) $linea->articulo_id,
            (int) ($linea->combinacion_id ?? 0),
            (int) ($linea->talle_id ?? 0),
        );
        $precio = round((float) ($cot['precio'] ?? 0), 4);

        return $precio > 0 ? $precio : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Ventas\Venta_Emision>  $emisiones
     */
    private function emisionDeLineaDevolver(CambioDevolucionMarketplaceLinea $linea, $emisiones)
    {
        $emisionId = (int) ($linea->venta_emision_id ?? 0);
        $emision = $emisionId > 0 ? $emisiones->get($emisionId) : null;
        if ($emision !== null) {
            return $emision;
        }
        $articuloId = (int) $linea->articulo_id;

        return $emisiones->first(fn ($em) => (int) $em->articulo_id === $articuloId);
    }

    /**
     * El reemplazo del mismo artículo se empareja con una línea «A devolver» todavía libre.
     *
     * @param  \Illuminate\Support\Collection<int, CambioDevolucionMarketplaceLinea>  $devolver
     * @param  list<int>  $usados
     */
    private function devolverMismoArticulo($devolver, int $articuloId, array $usados): ?CambioDevolucionMarketplaceLinea
    {
        if ($articuloId <= 0) {
            return null;
        }

        $par = $devolver->first(function (CambioDevolucionMarketplaceLinea $linea) use ($articuloId, $usados) {
            return (int) $linea->articulo_id === $articuloId
                && ! in_array((int) $linea->id, $usados, true);
        });

        return $par instanceof CambioDevolucionMarketplaceLinea ? $par : null;
    }

    /**
     * Deja en la línea de reemplazo el precio que salió en la factura.
     * La línea a devolver no se toca: la nota de crédito elige su precio aparte.
     *
     * @param  list<array{reemplazo:CambioDevolucionMarketplaceLinea,precio:float,anterior:float}>  $ajustes
     */
    private function persistirPreciosFacturados(array $ajustes): string
    {
        if ($ajustes === []) {
            return '';
        }

        $partes = [];
        foreach ($ajustes as $ajuste) {
            $precio = round((float) $ajuste['precio'], 4);
            $reemplazo = $ajuste['reemplazo'];
            $reemplazo->precio_unitario = $precio;
            $reemplazo->save();
            $nombre = trim((string) ($reemplazo->descripcion ?: $reemplazo->articulo?->descripcion ?: 'el artículo'));
            $partes[] = $nombre
                .' $ '.number_format((float) $ajuste['anterior'], 2, ',', '.')
                .' → $ '.number_format($precio, 2, ',', '.');
        }

        return implode('; ', $partes).'.';
    }
}
