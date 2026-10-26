<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\CambioDevolucionMarketplaceLinea;
use App\Models\Ventas\LocalVenta;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePrecioSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
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
    public function emitirFacReemplazo(CambioDevolucionMarketplace $cambio): array
    {
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
        foreach ($lineasReemplazo as $linea) {
            $cant = (float) $linea->cantidad;
            $precio = (float) $linea->precio_unitario;
            $par = $this->devolverMismoArticulo($devolverPendientes, (int) $linea->articulo_id, $devolverUsados);
            if ($par instanceof CambioDevolucionMarketplaceLinea) {
                $devolverUsados[] = (int) $par->id;
                $acreditable = CambioDevolucionMarketplacePrecioSupport::precioAcreditable(
                    (float) $par->precio_unitario,
                    $this->precioFacturadoDeLinea($par, $emisiones)
                );
                if ($acreditable > 0 && $precio - $acreditable > 0.02) {
                    $ajustes[] = [
                        'reemplazo' => $linea,
                        'devolver' => $par,
                        'precio' => $acreditable,
                        'anterior' => $precio,
                    ];
                    $precio = $acreditable;
                }
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

        $aviso = $this->persistirPreciosAcreditables($ajustes);

        return [
            'ok' => true,
            'venta_id' => $ventaId,
            'factura' => $factura,
            'pdf_urls' => $ventaId > 0 ? [url('ventas/listaunafactura/'.$ventaId)] : [],
            'mensaje' => trim('Factura de reemplazo emitida con medio puente NCD. '.$aviso),
        ];
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
     * @param  \Illuminate\Support\Collection<int, \App\Models\Ventas\Venta_Emision>  $emisiones
     */
    private function precioFacturadoDeLinea(CambioDevolucionMarketplaceLinea $linea, $emisiones): float
    {
        $emisionId = (int) ($linea->venta_emision_id ?? 0);
        $emision = $emisionId > 0 ? $emisiones->get($emisionId) : null;
        if ($emision === null) {
            $articuloId = (int) $linea->articulo_id;
            $emision = $emisiones->first(fn ($em) => (int) $em->articulo_id === $articuloId);
        }

        return round((float) ($emision?->precio ?? 0), 4);
    }

    /**
     * Deja en el legajo el precio que salió en la factura, el mismo que va a acreditar la nota.
     *
     * @param  list<array{reemplazo:CambioDevolucionMarketplaceLinea,devolver:CambioDevolucionMarketplaceLinea,precio:float,anterior:float}>  $ajustes
     */
    private function persistirPreciosAcreditables(array $ajustes): string
    {
        if ($ajustes === []) {
            return '';
        }

        $partes = [];
        foreach ($ajustes as $ajuste) {
            $precio = round((float) $ajuste['precio'], 4);
            $reemplazo = $ajuste['reemplazo'];
            $devolver = $ajuste['devolver'];
            $reemplazo->precio_unitario = $precio;
            $reemplazo->save();
            if ((float) $devolver->precio_unitario - $precio > 0.02) {
                $devolver->precio_unitario = $precio;
                $devolver->save();
            }
            $nombre = trim((string) ($reemplazo->descripcion ?: $reemplazo->articulo?->descripcion ?: 'el artículo'));
            $partes[] = $nombre
                .' $ '.number_format((float) $ajuste['anterior'], 2, ',', '.')
                .' → $ '.number_format($precio, 2, ',', '.');
        }

        return 'Mismo artículo: se facturó al precio ya cobrado para no volver a cobrar el descuento. '
            .implode('; ', $partes).'.';
    }
}
