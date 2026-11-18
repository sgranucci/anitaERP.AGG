<?php

namespace App\Support\Ventas;

use App\Models\Stock\Listaprecio;
use App\Services\Stock\PrecioService;
use Carbon\Carbon;

/**
 * Fasón Interforming (Anita asigna_fason / porc_fason / penv_precio_fason).
 *
 * - `% fason` parte la cantidad (virgen vs fasón); marca partida.
 * - `precio_fason` sale de la lista de precios «Fason», no de precio × %.
 */
final class PedidoInterformingFasonSupport
{
    private static ?int $listaprecioFasonIdCache = null;

    private static bool $listaprecioFasonResuelto = false;

    /**
     * Lista Anita «Fason» (codigo 2). Null si no está cargada.
     */
    public static function listaprecioFasonId(): ?int
    {
        if (self::$listaprecioFasonResuelto) {
            return self::$listaprecioFasonIdCache;
        }

        self::$listaprecioFasonResuelto = true;

        $id = Listaprecio::query()
            ->where('codigo', 2)
            ->orderBy('id')
            ->value('id');

        if (! $id) {
            $id = Listaprecio::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', ['FASON'])
                ->orderBy('id')
                ->value('id');
        }

        self::$listaprecioFasonIdCache = $id ? (int) $id : null;

        return self::$listaprecioFasonIdCache;
    }

    /**
     * Precio vigente en lista Fason (0 si no hay lista o no hay precio).
     *
     * @return array{precio: float, moneda_id: int|null, listaprecio_id: int|null}
     */
    public static function resolverPrecioDesdeLista(int $articuloId, ?string $fechaYmd = null): array
    {
        $vacio = ['precio' => 0.0, 'moneda_id' => null, 'listaprecio_id' => null];
        if ($articuloId <= 0) {
            return $vacio;
        }

        $listaId = self::listaprecioFasonId();
        if (! $listaId) {
            return $vacio;
        }

        $fecha = $fechaYmd
            ? Carbon::parse($fechaYmd)->startOfDay()
            : Carbon::now()->startOfDay();

        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaId, $fecha);
        if ($precios === []) {
            return [
                'precio' => 0.0,
                'moneda_id' => null,
                'listaprecio_id' => $listaId,
            ];
        }

        $vigente = end($precios);

        return [
            'precio' => round((float) ($vigente['precio'] ?? 0), 6),
            'moneda_id' => isset($vigente['moneda_id']) ? (int) $vigente['moneda_id'] : null,
            'listaprecio_id' => $listaId,
        ];
    }

    /**
     * Partida Anita: 1 = fason si hay %, 0 = propio.
     */
    public static function partidaDesdePorc(float $porcFason): int
    {
        return $porcFason > 0
            ? PedidoEstadosInterforming::PARTIDA_FASON
            : PedidoEstadosInterforming::PARTIDA_PROPIO;
    }

    /**
     * Cantidad correspondiente al tramo fasón (el resto es virgen / propio).
     */
    public static function cantidadTramoFason(float $cantidad, float $porcFason): float
    {
        if ($porcFason <= 0 || $cantidad == 0.0) {
            return 0.0;
        }

        return round($cantidad * ($porcFason / 100.0), 6);
    }

    /**
     * Partición Anita (a-pedido / Env.Cli. vs Interforming): % sobre cantidad, 2 decimales.
     *
     * @return array{cant_fason: float, cant_virgen: float}
     */
    public static function particionCantidad(float $cantidad, float $porcFason): array
    {
        if ($porcFason <= 0.0001 || abs($cantidad) < 0.0000001) {
            return [
                'cant_fason' => 0.0,
                'cant_virgen' => round($cantidad, 2),
            ];
        }

        $cantFason = round($cantidad * ($porcFason / 100.0), 2);
        $cantVirgen = round($cantidad - $cantFason, 2);

        return [
            'cant_fason' => $cantFason,
            'cant_virgen' => $cantVirgen,
        ];
    }

    /**
     * Importe de línea Anita: virgen × precio + fasón × precio_fason (dto % sobre cada precio).
     * El % solo parte cantidad; no inventa precio.
     *
     * @return array{
     *   cant_fason: float,
     *   cant_virgen: float,
     *   importe: float,
     *   importe_sin_dto: float,
     *   precio_neto_efectivo: float,
     *   precio_lista_efectivo: float
     * }
     */
    public static function importeLineaMix(
        float $cantidad,
        float $precio,
        float $porcFason,
        float $precioFason,
        float $descuentoPct = 0.0
    ): array {
        if ($descuentoPct < 0) {
            $descuentoPct = 0.0;
        }
        if ($descuentoPct > 100) {
            $descuentoPct = 100.0;
        }

        $partes = self::particionCantidad($cantidad, $porcFason);
        $cantFason = $partes['cant_fason'];
        $cantVirgen = $partes['cant_virgen'];

        $coef = $descuentoPct > 0.00001
            ? (1.0 - ($descuentoPct / 100.0))
            : 1.0;
        $precioNeto = round($precio * $coef, 6);
        $precioFasonNeto = round($precioFason * $coef, 6);

        if ($cantFason > 0.00001 && $precioFason > 0.0000001) {
            $importeSinDto = round(
                ($cantVirgen * $precio) + ($cantFason * $precioFason),
                2
            );
            $importe = round(
                ($cantVirgen * $precioNeto) + ($cantFason * $precioFasonNeto),
                2
            );
        } else {
            $importeSinDto = round($cantidad * $precio, 2);
            $importe = round($cantidad * $precioNeto, 2);
        }

        $den = abs($cantidad) > 0.0000001 ? $cantidad : 1.0;

        return [
            'cant_fason' => $cantFason,
            'cant_virgen' => $cantVirgen,
            'importe' => $importe,
            'importe_sin_dto' => $importeSinDto,
            'precio_neto_efectivo' => round($importe / $den, 6),
            'precio_lista_efectivo' => round($importeSinDto / $den, 6),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function aplicarAItem(array $item): array
    {
        $porc = (float) ($item['porc_fason'] ?? 0);
        $item['porc_fason'] = $porc;
        $item['partida'] = self::partidaDesdePorc($porc);

        if ($porc <= 0) {
            $item['precio_fason'] = 0.0;
        } else {
            $enviado = (float) ($item['precio_fason'] ?? 0);
            if ($enviado > 0.0000001) {
                $item['precio_fason'] = round($enviado, 6);
            } else {
                $articuloId = (int) ($item['articulo_id'] ?? 0);
                $resuelto = self::resolverPrecioDesdeLista($articuloId);
                $item['precio_fason'] = $resuelto['precio'];
                if ($resuelto['moneda_id']) {
                    $item['moneda_fason_id'] = $resuelto['moneda_id'];
                }
            }
        }

        if (! isset($item['porc_fason_ant'])) {
            $item['porc_fason_ant'] = $porc;
        }

        return $item;
    }
}
