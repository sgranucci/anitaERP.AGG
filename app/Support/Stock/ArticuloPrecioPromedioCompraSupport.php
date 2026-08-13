<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Promedio de las 3 últimas compras (artículos TITO / asiento TRCONT).
 *
 * Orden:
 * 1. ERP: exactamente 3 recepciones COM confirmadas. El precio en pesos se arma
 *    con moneda y cotización de Anita recepmov (recv_cod_mon / recv_cotizacion);
 *    si no hay recepmov, se usa la línea ERP.
 * 2. Fallback Anita: promedio de stkmae.stkm_pre_compra1/2/3
 */
final class ArticuloPrecioPromedioCompraSupport
{
    public const CANTIDAD = 3;

    public const ORIGEN_ERP_COM = 'erp_com';

    public const ORIGEN_ANITA_STKMAE = 'anita_stkmae';

    public const FUENTE_RECEPMOV = 'recepmov';

    public const FUENTE_ERP = 'erp';

    /**
     * @return array{precio: float|null, origen: string|null, compras: list<array<string, mixed>>}
     */
    public static function resolverPorArticulo(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $porId = self::resolverPorArticulos([$articulo], $anitaService);

        return $porId[(int) $articulo->id] ?? ['precio' => null, 'origen' => null, 'compras' => []];
    }

    /**
     * @param  iterable<Articulo|int>  $articulosOrIds
     * @return array<int, array{precio: float|null, origen: string|null, compras: list<array<string, mixed>>}>
     */
    public static function resolverPorArticulos(
        iterable $articulosOrIds,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $articulos = [];
        foreach ($articulosOrIds as $item) {
            if ($item instanceof Articulo) {
                $articulos[(int) $item->id] = $item;
            } elseif (is_numeric($item) && (int) $item > 0) {
                $articulos[(int) $item] = null;
            }
        }

        if ($articulos === []) {
            return [];
        }

        $faltantes = array_keys(array_filter($articulos, static fn ($a) => $a === null));
        if ($faltantes !== []) {
            foreach (Articulo::query()->whereIn('id', $faltantes)->get(['id', 'sku']) as $art) {
                $articulos[(int) $art->id] = $art;
            }
        }

        $articuloIds = [];
        $skuPorId = [];
        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $articuloIds[] = $id;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku !== '') {
                $skuPorId[$id] = $sku;
            }
        }

        $detalles = ArticuloPrecioUltimaCompraSupport::ultimasRecepcionesConfirmadasDetallePorArticuloIds(
            $articuloIds,
            self::CANTIDAD
        );
        $recepmovPorClave = self::cargarRecepmovPorClaves($detalles);

        $out = [];
        $skusFallback = [];
        $idsFallback = [];

        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                $out[$id] = ['precio' => null, 'origen' => null, 'compras' => []];

                continue;
            }

            $filasErp = $detalles[$id] ?? [];
            if (count($filasErp) === self::CANTIDAD) {
                $compras = [];
                $precios = [];
                foreach ($filasErp as $fila) {
                    $convertida = self::convertirFilaComAPesos($fila, $recepmovPorClave);
                    if ($convertida['precio'] <= 0) {
                        $precios = [];
                        break;
                    }
                    $precios[] = $convertida['precio'];
                    $compras[] = [
                        'n' => count($compras) + 1,
                        'precio' => $convertida['precio'],
                        'fecha' => $fila['fecha'] ?? null,
                        'com' => $fila['numerorecepcion'] ?? null,
                        'moneda' => $convertida['moneda'],
                        'cotizacion' => $convertida['cotizacion'],
                        'fuente' => $convertida['fuente'],
                    ];
                }

                if (count($precios) === self::CANTIDAD) {
                    $out[$id] = [
                        'precio' => round(array_sum($precios) / self::CANTIDAD, 6),
                        'origen' => self::ORIGEN_ERP_COM,
                        'compras' => $compras,
                    ];

                    continue;
                }
            }

            $sku = $skuPorId[$id] ?? '';
            if ($sku === '') {
                $out[$id] = ['precio' => null, 'origen' => null, 'compras' => []];

                continue;
            }

            $skusFallback[] = $sku;
            $idsFallback[] = $id;
        }

        if ($idsFallback === []) {
            return $out;
        }

        $anitaService ??= app(StkmaeUltimaCompraAnitaService::class);
        $datosAnita = $anitaService->obtenerDatosUltimaCompraPorSkus(array_values(array_unique($skusFallback)));

        foreach ($idsFallback as $id) {
            $sku = $skuPorId[$id] ?? '';
            $dato = $sku !== '' ? ($datosAnita[$sku] ?? null) : null;
            $c1 = (float) ($dato['compra1'] ?? 0);
            $c2 = (float) ($dato['compra2'] ?? 0);
            $c3 = (float) ($dato['compra3'] ?? 0);
            $promedio = round(($c1 + $c2 + $c3) / 3, 6);
            if ($promedio <= 0) {
                $out[$id] = ['precio' => null, 'origen' => null, 'compras' => []];

                continue;
            }

            $compras = [];
            foreach ([1 => $c1, 2 => $c2, 3 => $c3] as $n => $precio) {
                if ($precio <= 0) {
                    continue;
                }
                $compras[] = [
                    'n' => $n,
                    'precio' => round($precio, 6),
                    'fecha' => null,
                    'com' => null,
                    'moneda' => 'PES',
                    'cotizacion' => null,
                    'fuente' => self::ORIGEN_ANITA_STKMAE,
                ];
            }

            $out[$id] = [
                'precio' => $promedio,
                'origen' => self::ORIGEN_ANITA_STKMAE,
                'compras' => $compras,
            ];
        }

        return $out;
    }

    public static function resolverPrecioUnitario(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): ?float {
        $dato = self::resolverPorArticulo($articulo, $anitaService);
        $precio = $dato['precio'] ?? null;

        return $precio !== null && (float) $precio > 0 ? round((float) $precio, 6) : null;
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $detalles
     * @return array<string, list<object>>
     */
    private static function cargarRecepmovPorClaves(array $detalles): array
    {
        $claves = [];
        foreach ($detalles as $filas) {
            foreach ($filas as $fila) {
                $clave = self::claveRecepmov($fila);
                if ($clave === null) {
                    continue;
                }
                $claves[$clave] = [
                    'tipo' => (string) ($fila['anita_tipo'] ?? 'COM'),
                    'letra' => (string) ($fila['anita_letra'] ?? 'X'),
                    'sucursal' => (int) ($fila['anita_sucursal'] ?? 0),
                    'nro' => (int) ($fila['anita_nro'] ?? 0),
                ];
            }
        }

        $out = [];
        foreach ($claves as $clave => $dato) {
            try {
                $out[$clave] = RecepcionProveedorAnitaImportSupport::listarRecepmov(
                    $dato['tipo'],
                    $dato['letra'],
                    $dato['sucursal'],
                    $dato['nro']
                );
            } catch (\Throwable $e) {
                Log::warning('ArticuloPrecioPromedioCompra: recepmov no disponible', [
                    'clave' => $clave,
                    'error' => $e->getMessage(),
                ]);
                $out[$clave] = [];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private static function claveRecepmov(array $fila): ?string
    {
        $sucursal = (int) ($fila['anita_sucursal'] ?? 0);
        $nro = (int) ($fila['anita_nro'] ?? 0);
        if ($sucursal <= 0 || $nro <= 0) {
            return null;
        }
        $tipo = trim((string) ($fila['anita_tipo'] ?? 'COM')) ?: 'COM';
        $letra = trim((string) ($fila['anita_letra'] ?? 'X')) ?: 'X';

        return $tipo.'|'.$letra.'|'.$sucursal.'|'.$nro;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, list<object>>  $recepmovPorClave
     * @return array{precio: float, moneda: string, cotizacion: float|null, fuente: string}
     */
    private static function convertirFilaComAPesos(array $fila, array $recepmovPorClave): array
    {
        $clave = self::claveRecepmov($fila);
        $sku = trim((string) ($fila['sku'] ?? ''));
        if ($clave !== null && $sku !== '' && isset($recepmovPorClave[$clave])) {
            $lin = RecepcionProveedorAnitaImportSupport::lineaRecepmovPorSku($recepmovPorClave[$clave], $sku);
            if ($lin !== null) {
                $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($lin->recv_cod_mon ?? 1);
                $cotizacion = (float) ($lin->recv_cotizacion ?? 1) ?: 1.0;
                $precio = ArticuloPrecioUltimaCompraSupport::precioUnitarioMonedaLocal(
                    (float) ($lin->recv_precio ?? 0),
                    $monedaId,
                    $cotizacion
                );
                if ($precio > 0) {
                    return [
                        'precio' => $precio,
                        'moneda' => self::abreviaturaMoneda($monedaId),
                        'cotizacion' => $cotizacion,
                        'fuente' => self::FUENTE_RECEPMOV,
                    ];
                }
            }
        }

        $monedaId = isset($fila['moneda_id']) ? (int) $fila['moneda_id'] : 0;
        $cotizacion = isset($fila['cotizacion']) ? (float) $fila['cotizacion'] : 1.0;
        $precio = ArticuloPrecioUltimaCompraSupport::precioUnitarioMonedaLocal(
            (float) ($fila['precio'] ?? 0),
            $monedaId > 0 ? $monedaId : null,
            $cotizacion
        );

        return [
            'precio' => $precio,
            'moneda' => self::abreviaturaMoneda($monedaId),
            'cotizacion' => $cotizacion > 0 ? $cotizacion : null,
            'fuente' => self::FUENTE_ERP,
        ];
    }

    private static function abreviaturaMoneda(int $monedaId): string
    {
        static $map = null;
        if ($map === null) {
            $map = DB::table('moneda')->pluck('abreviatura', 'id')->all();
        }

        $abr = trim((string) ($map[$monedaId] ?? ''));

        return $abr !== '' ? $abr : (string) $monedaId;
    }
}
