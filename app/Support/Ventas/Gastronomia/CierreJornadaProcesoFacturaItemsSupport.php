<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaConsumoService;
use InvalidArgumentException;

/**
 * Arma ítems de factura del proceso de cierre a partir de órdenes Waitry (QR/MP).
 */
final class CierreJornadaProcesoFacturaItemsSupport
{
    /**
     * @param  list<array<string, mixed>>  $comandas  Movimientos sin_facturar_qr del proceso
     * @param  array<int, array<string, mixed>>  $ordenesPorId  orderId => orden Waitry
     * @return array{
     *   articulo_ids: list<int>,
     *   cantidades: list<float>,
     *   precios: list<float>,
     *   descripciones: list<string>,
     *   errores: list<string>,
     *   waitry_order_ids: list<int>
     * }
     */
    public static function construirItemsFactura(
        array $comandas,
        array $ordenesPorId,
        ConfiguracionPuntoventaGastronomia $cfg,
        WaitryOrdenesExternasService $waitryOrdenesService,
        GastronomiaCuentaService $cuentaService,
    ): array {
        /** @var array<int, array{cantidad:float,precio:float,descripcion:string}> $aggregated */
        $aggregated = [];
        $errores = [];
        $waitryIds = [];

        foreach ($comandas as $mov) {
            $orderId = (int) ($mov['waitry_order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $totalComanda = CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($mov);
            if ($totalComanda <= 0.0001) {
                continue;
            }

            $orden = $ordenesPorId[$orderId] ?? null;
            if ($orden === null) {
                $errores[] = 'Waitry #'.$orderId.': no se encontró la orden en la consulta Waitry del tramo.';

                continue;
            }

            $lineas = $waitryOrdenesService->extraerLineasDesdeOrden($orden, true);
            if ($lineas === []) {
                $errores[] = 'Waitry #'.$orderId.': la orden no tiene ítems importables (carrito vacío o ítems sin SKU/externalId en Waitry).';

                continue;
            }

            $waitryIds[] = $orderId;

            foreach ($lineas as $ln) {
                $sku = trim((string) ($ln['sku'] ?? ''));
                $articulo = self::resolverArticulo($cfg, $cuentaService, $sku);
                if ($articulo === null) {
                    $errores[] = 'Waitry #'.$orderId.' — SKU «'.$sku.'» ('.($ln['titulo'] ?? '').') no está en catálogo gastronomía.';

                    continue;
                }

                $cantidad = round((float) ($ln['cantidad'] ?? 0), 4);
                if ($cantidad <= 0.) {
                    continue;
                }

                $precio = round((float) ($ln['precio_unitario'] ?? 0), 4);
                $articuloId = (int) $articulo->id;
                $key = $articuloId.'|'.number_format($precio, 4, '.', '');

                if (! isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'articulo_id' => $articuloId,
                        'cantidad' => 0.,
                        'precio' => $precio,
                        'descripcion' => trim((string) ($ln['titulo'] ?? $articulo->descripcion ?? '')),
                    ];
                }
                $aggregated[$key]['cantidad'] = round($aggregated[$key]['cantidad'] + $cantidad, 4);
            }
        }

        if ($aggregated === []) {
            return [
                'articulo_ids' => [],
                'cantidades' => [],
                'precios' => [],
                'descripciones' => [],
                'errores' => $errores !== []
                    ? $errores
                    : ['No hay ítems válidos para facturar en las comandas seleccionadas.'],
                'waitry_order_ids' => [],
            ];
        }

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];

        foreach ($aggregated as $row) {
            $articuloIds[] = (int) $row['articulo_id'];
            $cantidades[] = (float) $row['cantidad'];
            $precios[] = (float) $row['precio'];
            $descripciones[] = (string) $row['descripcion'];
        }

        $totalEsperado = CierreJornadaProcesoFacturaComandasSupport::totalComandas($comandas);
        [$precios, $cantidades] = self::escalarPreciosAlTotal($articuloIds, $cantidades, $precios, $totalEsperado);

        return [
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripciones' => $descripciones,
            'errores' => $errores,
            'waitry_order_ids' => array_values(array_unique($waitryIds)),
        ];
    }

    /**
     * Insumos agregados de comandas completas (100 % efectivo, sin facturar).
     *
     * @param  list<array<string, mixed>>  $comandasAjuste
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{
     *   lineas: list<array{articulo_id:int,cantidad:float,waitry_order_id:int}>,
     *   errores: list<string>
     * }
     */
    public static function lineasInsumosComandasCompletas(
        array $comandasAjuste,
        array $ordenesPorId,
        ConfiguracionPuntoventaGastronomia $cfg,
        WaitryOrdenesExternasService $waitryOrdenesService,
        GastronomiaCuentaService $cuentaService,
        GastronomiaFormulaConsumoService $consumoFormulaService,
    ): array {
        return self::lineasInsumosDesdeComandas(
            $comandasAjuste,
            $ordenesPorId,
            $cfg,
            $waitryOrdenesService,
            $cuentaService,
            $consumoFormulaService,
            1.,
        );
    }

    /**
     * @deprecated Use lineasInsumosComandasCompletas()
     *
     * @param  list<array<string, mixed>>  $comandasEfectivo
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{
     *   lineas: list<array{articulo_id:int,cantidad:float,waitry_order_id:int}>,
     *   errores: list<string>
     * }
     */
    public static function lineasInsumosEfectivoNoFacturado(
        array $comandasEfectivo,
        array $ordenesPorId,
        ConfiguracionPuntoventaGastronomia $cfg,
        WaitryOrdenesExternasService $waitryOrdenesService,
        GastronomiaCuentaService $cuentaService,
        GastronomiaFormulaConsumoService $consumoFormulaService,
    ): array {
        return self::lineasInsumosComandasCompletas(
            $comandasEfectivo,
            $ordenesPorId,
            $cfg,
            $waitryOrdenesService,
            $cuentaService,
            $consumoFormulaService,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $comandas
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{
     *   lineas: list<array{articulo_id:int,cantidad:float,waitry_order_id:int}>,
     *   errores: list<string>
     * }
     */
    private static function lineasInsumosDesdeComandas(
        array $comandas,
        array $ordenesPorId,
        ConfiguracionPuntoventaGastronomia $cfg,
        WaitryOrdenesExternasService $waitryOrdenesService,
        GastronomiaCuentaService $cuentaService,
        GastronomiaFormulaConsumoService $consumoFormulaService,
        float $factor,
    ): array {
        $errores = [];
        /** @var array<int, float> $aggregated articulo_id => cantidad */
        $aggregated = [];

        foreach ($comandas as $mov) {
            $orderId = (int) ($mov['waitry_order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $orden = $ordenesPorId[$orderId] ?? null;
            if ($orden === null) {
                $errores[] = 'Waitry #'.$orderId.': no se encontró la orden para ajuste de insumos.';

                continue;
            }

            $lineas = $waitryOrdenesService->extraerLineasDesdeOrden($orden, true);
            foreach ($lineas as $ln) {
                $sku = trim((string) ($ln['sku'] ?? ''));
                $articulo = self::resolverArticulo($cfg, $cuentaService, $sku);
                if ($articulo === null) {
                    continue;
                }

                $cantidadLinea = round((float) ($ln['cantidad'] ?? 0) * $factor, 6);
                if ($cantidadLinea <= 0.) {
                    continue;
                }

                $insumos = $consumoFormulaService->insumosAgregadosPorArticulo(
                    (int) $articulo->id,
                    $cantidadLinea,
                    [],
                );

                foreach ($insumos as $insumoId => $cantInsumo) {
                    if ($cantInsumo <= 0.) {
                        continue;
                    }
                    $aggregated[$insumoId] = round(($aggregated[$insumoId] ?? 0.) + $cantInsumo, 6);
                }
            }
        }

        $lineas = [];
        foreach ($aggregated as $articuloId => $cantidad) {
            if ($cantidad <= 0.) {
                continue;
            }
            $lineas[] = [
                'articulo_id' => (int) $articuloId,
                'cantidad' => (float) $cantidad,
                'waitry_order_id' => 0,
            ];
        }

        return ['lineas' => $lineas, 'errores' => $errores];
    }

    private static function resolverArticulo(
        ConfiguracionPuntoventaGastronomia $cfg,
        GastronomiaCuentaService $cuentaService,
        string $codigo,
    ): ?Articulo {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            $codigo,
            mb_strtoupper($codigo, 'UTF-8'),
            GastronomiaSkuCatalogoSupport::skuDesdeSufijoDigitos($codigo),
            GastronomiaSkuCatalogoSupport::prefijo().$codigo,
            mb_strtoupper(GastronomiaSkuCatalogoSupport::prefijo().$codigo, 'UTF-8'),
        ])));

        foreach ($candidatos as $sku) {
            $articulo = $cuentaService->buscarArticuloCatalogoPorSku($cfg, $sku);
            if ($articulo instanceof Articulo) {
                return $articulo;
            }
        }

        return null;
    }

    /**
     * Escala precios para que la suma de líneas coincida con el total QR del asiento 1.
     *
     * @param  list<int>  $articuloIds
     * @param  list<float>  $cantidades
     * @param  list<float>  $precios
     * @return array{0:list<float>,1:list<float>}
     */
    private static function escalarPreciosAlTotal(
        array $articuloIds,
        array $cantidades,
        array $precios,
        float $totalEsperado,
    ): array {
        $n = min(count($articuloIds), count($cantidades), count($precios));
        if ($n === 0 || $totalEsperado <= 0.0001) {
            return [$precios, $cantidades];
        }

        $suma = 0.;
        for ($i = 0; $i < $n; $i++) {
            $suma += round((float) $cantidades[$i] * (float) $precios[$i], 2);
        }
        $suma = round($suma, 2);

        if ($suma <= 0. || abs($suma - $totalEsperado) <= 0.02) {
            return [$precios, $cantidades];
        }

        $factor = $totalEsperado / $suma;
        $ultimo = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $precios[$i] = round((float) $precios[$i] * $factor, 4);
        }

        $sumaEscalada = 0.;
        for ($i = 0; $i < $n; $i++) {
            $sumaEscalada += round((float) $cantidades[$i] * (float) $precios[$i], 2);
        }
        $delta = round($totalEsperado - $sumaEscalada, 2);
        if (abs($delta) >= 0.001 && $ultimo >= 0 && (float) $cantidades[$ultimo] > 0.) {
            $precios[$ultimo] = round((float) $precios[$ultimo] + ($delta / (float) $cantidades[$ultimo]), 4);
        }

        return [$precios, $cantidades];
    }
}
