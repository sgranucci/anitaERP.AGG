<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Compras\ArticuloProveedorMatchSupport;
use App\Support\Compras\ComprobanteProveedorControlesConfigSupport;
use App\Support\Compras\ComprobanteProveedorLineasFacturaSupport;
use App\Support\Stock\ArticuloSkuMatchSupport;
use Illuminate\Support\Collection;

/**
 * Match por línea factura ↔ COM/OC (SKU y/o precio unitario).
 *
 * No corre si los flags de configuración están off (default AGG).
 *
 * @phpstan-type ResultadoMatchLineas array{
 *     ok: bool,
 *     avisos: list<string>,
 *     errores: list<string>,
 *     matched: int,
 *     sin_match_factura: int,
 *     sin_match_com: int,
 *     fuera_tolerancia_precio: int
 * }
 * @phpstan-type RefLinea array{
 *     sku: string,
 *     precio: float,
 *     cantidad: float,
 *     origen: string,
 *     descripcion: string
 * }
 */
class ComprobanteProveedorMatchLineasService
{
    private const EPS_PRECIO = 0.0001;

    /**
     * @param  list<int>  $recepcionIds
     * @param  Collection<int, array<string, mixed>>|iterable<int, mixed>|null  $lineasFactura
     * @return ResultadoMatchLineas
     */
    public function validar(
        Ordencompra $ordencompra,
        array $recepcionIds,
        ?iterable $lineasFactura = null,
        bool $estricto = true,
        ?int $proveedorId = null,
    ): array {
        $resultado = [
            'ok' => true,
            'avisos' => [],
            'errores' => [],
            'matched' => 0,
            'sin_match_factura' => 0,
            'sin_match_com' => 0,
            'fuera_tolerancia_precio' => 0,
        ];

        $empresaId = (int) $ordencompra->empresa_id;
        $cfg = ComprobanteProveedorControlesConfigSupport::paraEmpresa($empresaId);

        if (! $cfg['match_lineas_activo']) {
            return $resultado;
        }

        $lineas = ComprobanteProveedorLineasFacturaSupport::coleccionDesdeIterable($lineasFactura ?? []);
        if ($lineas->isEmpty()) {
            $resultado['avisos'][] = 'Control SKU/precio activo en configuración, pero el comprobante '
                .'aún no tiene líneas de artículo para emparejar con la COM. '
                .'Solo aplica el control de importe cabecera.';

            return $resultado;
        }

        $proveedorId = $proveedorId ?: (int) ($ordencompra->proveedor_id ?? 0);
        $indice = $this->construirIndiceReferencia($ordencompra, $recepcionIds);
        $skusFacturaUsados = [];

        foreach ($lineas as $linea) {
            $clave = $this->resolverClaveSku($linea, $proveedorId);
            if ($clave === null || $clave === '') {
                $this->pushProblema(
                    $resultado,
                    $estricto && $cfg['controla_sku_vs_com'],
                    'Línea de factura sin SKU ni código de proveedor resoluble'
                    .(filled($linea['descripcion'] ?? null) ? ' ('.$linea['descripcion'].').' : '.'),
                );
                $resultado['sin_match_factura']++;

                continue;
            }

            $skusFacturaUsados[$clave] = true;
            $ref = $indice[$clave] ?? null;
            if ($ref === null) {
                $resultado['sin_match_factura']++;
                if ($cfg['controla_sku_vs_com']) {
                    $this->pushProblema(
                        $resultado,
                        $estricto,
                        sprintf(
                            'SKU «%s» en factura sin correlato en COM/OC%s.',
                            $clave,
                            filled($linea['descripcion'] ?? null) ? ' ('.$linea['descripcion'].')' : '',
                        ),
                    );
                }

                continue;
            }

            $resultado['matched']++;

            if (! $cfg['controla_precio_unitario']) {
                continue;
            }

            $precioFac = (float) ($linea['precio_unitario'] ?? 0);
            $precioRef = (float) ($ref['precio'] ?? 0);
            if (! $this->precioDentroTolerancia($precioRef, $precioFac, (float) $cfg['tolerancia_precio_pct'])) {
                $resultado['fuera_tolerancia_precio']++;
                $diffPct = $precioRef > self::EPS_PRECIO
                    ? abs($precioFac - $precioRef) / $precioRef * 100
                    : 0.0;
                $this->pushProblema(
                    $resultado,
                    $estricto,
                    sprintf(
                        'Precio SKU «%s»: factura %s vs %s %s (diff %s%%; tol. %s%%).',
                        $clave,
                        number_format($precioFac, 4, ',', '.'),
                        $ref['origen'],
                        number_format($precioRef, 4, ',', '.'),
                        number_format($diffPct, 2, ',', '.'),
                        number_format((float) $cfg['tolerancia_precio_pct'], 2, ',', '.'),
                    ),
                );
            }
        }

        if ($cfg['controla_sku_vs_com']) {
            foreach ($indice as $sku => $ref) {
                if (isset($skusFacturaUsados[$sku])) {
                    continue;
                }
                // Solo avisar COM sin factura (pueden facturar parcial); no bloquea.
                if (($ref['origen'] ?? '') === 'COM') {
                    $resultado['sin_match_com']++;
                    $resultado['avisos'][] = sprintf(
                        'SKU «%s» en COM sin línea en factura (posible facturación parcial).',
                        $sku,
                    );
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  array{ok: bool, avisos: list<string>, errores: list<string>}  $resultado
     */
    private function pushProblema(array &$resultado, bool $comoError, string $mensaje): void
    {
        if ($comoError) {
            $resultado['ok'] = false;
            $resultado['errores'][] = $mensaje;
        } else {
            $resultado['avisos'][] = $mensaje;
        }
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function resolverClaveSku(array $linea, int $proveedorId): ?string
    {
        $sku = trim((string) ($linea['sku'] ?? ''));
        if ($sku !== '') {
            return ArticuloSkuMatchSupport::normalizar($sku);
        }

        $codigoProv = trim((string) ($linea['codigo_proveedor'] ?? ''));
        if ($codigoProv !== '' && $proveedorId > 0) {
            $match = ArticuloProveedorMatchSupport::resolver($proveedorId, $codigoProv, null, null);
            $skuMatch = trim((string) data_get($match, 'articulo_sku', ''));
            if ($skuMatch === '') {
                $articuloIdMatch = (int) data_get($match, 'articulo_id', 0);
                if ($articuloIdMatch > 0) {
                    $skuMatch = (string) (\App\Models\Stock\Articulo::query()
                        ->whereKey($articuloIdMatch)
                        ->value('sku') ?? '');
                }
            }
            if ($skuMatch !== '') {
                return ArticuloSkuMatchSupport::normalizar($skuMatch);
            }

            return ArticuloSkuMatchSupport::normalizar($codigoProv);
        }

        $articuloId = (int) ($linea['articulo_id'] ?? 0);
        if ($articuloId > 0) {
            $skuDb = (string) (\App\Models\Stock\Articulo::query()->whereKey($articuloId)->value('sku') ?? '');
            if ($skuDb !== '') {
                return ArticuloSkuMatchSupport::normalizar($skuDb);
            }
        }

        return null;
    }

    /**
     * Índice SKU normalizado → referencia COM (prioridad) u OC.
     *
     * @param  list<int>  $recepcionIds
     * @return array<string, RefLinea>
     */
    private function construirIndiceReferencia(Ordencompra $ordencompra, array $recepcionIds): array
    {
        $indice = [];

        $ordencompra->loadMissing(['ordencompra_articulos.articulos']);

        foreach ($ordencompra->ordencompra_articulos ?? [] as $lineaOc) {
            $sku = ArticuloSkuMatchSupport::normalizar((string) ($lineaOc->articulos->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $this->acumularRef($indice, $sku, [
                'sku' => $sku,
                'precio' => (float) ($lineaOc->precio ?? 0),
                'cantidad' => (float) ($lineaOc->cantidad ?? 0),
                'origen' => 'OC',
                'descripcion' => (string) ($lineaOc->articulos->descripcion ?? ''),
            ]);
        }

        $ids = collect($recepcionIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->all();
        if ($ids === []) {
            return $indice;
        }

        $lineasCom = Recepcion_Proveedor_Articulo::query()
            ->with('articulos')
            ->whereIn('recepcion_proveedor_id', $ids)
            ->get();

        foreach ($lineasCom as $lineaCom) {
            $sku = ArticuloSkuMatchSupport::normalizar((string) ($lineaCom->articulos->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            // COM pisa OC para el mismo SKU (precio de recepción).
            $this->acumularRef($indice, $sku, [
                'sku' => $sku,
                'precio' => (float) ($lineaCom->precio ?? 0),
                'cantidad' => (float) ($lineaCom->cantidad ?? 0),
                'origen' => 'COM',
                'descripcion' => (string) ($lineaCom->articulos->descripcion ?? $lineaCom->detalle ?? ''),
            ], true);
        }

        return $indice;
    }

    /**
     * @param  array<string, RefLinea>  $indice
     * @param  RefLinea  $ref
     */
    private function acumularRef(array &$indice, string $sku, array $ref, bool $desdeCom = false): void
    {
        if (! isset($indice[$sku])) {
            $indice[$sku] = $ref;

            return;
        }

        $prev = $indice[$sku];
        $mismoOrigenCom = $desdeCom && ($prev['origen'] ?? '') === 'COM';

        if ($desdeCom && ! $mismoOrigenCom) {
            // COM reemplaza referencia OC.
            $indice[$sku] = $ref;

            return;
        }

        $cantPrev = (float) ($prev['cantidad'] ?? 0);
        $cantAdd = (float) ($ref['cantidad'] ?? 0);
        $cantTot = $cantPrev + $cantAdd;
        $precioPrev = (float) ($prev['precio'] ?? 0);
        $precioAdd = (float) ($ref['precio'] ?? 0);
        $precio = $cantTot > self::EPS_PRECIO
            ? (($precioPrev * $cantPrev) + ($precioAdd * $cantAdd)) / $cantTot
            : $precioAdd;

        $indice[$sku] = [
            'sku' => $sku,
            'precio' => $precio,
            'cantidad' => $cantTot,
            'origen' => $desdeCom ? 'COM' : ($prev['origen'] ?? $ref['origen']),
            'descripcion' => ($ref['descripcion'] !== '' ? $ref['descripcion'] : ($prev['descripcion'] ?? '')),
        ];
    }

    public function precioDentroTolerancia(float $precioRef, float $precioFactura, float $toleranciaPct): bool
    {
        if (abs($precioFactura - $precioRef) <= self::EPS_PRECIO) {
            return true;
        }
        if ($toleranciaPct <= 0) {
            return abs($precioFactura - $precioRef) <= self::EPS_PRECIO;
        }
        if ($precioRef <= self::EPS_PRECIO) {
            return abs($precioFactura - $precioRef) <= self::EPS_PRECIO;
        }
        $diffPct = abs($precioFactura - $precioRef) / $precioRef * 100;

        return $diffPct <= $toleranciaPct;
    }
}
