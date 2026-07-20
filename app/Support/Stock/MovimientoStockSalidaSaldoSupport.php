<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Depmae;
use App\Models\Stock\Talle;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;

final class MovimientoStockSalidaSaldoSupport
{
    public static function esSignoRestaStock(?string $signoCantidad): bool
    {
        return strtoupper(trim((string) ($signoCantidad ?? ''))) === 'R';
    }

    /**
     * @param  list<int|string|null>  $articulosId
     * @param  list<int|float|string|null>  $cantidades
     * @param  list<int|string|null>  $coloresId
     * @param  list<int|string|null>  $tallesId
     */
    public static function validarDesdeLineasFormulario(
        int $depositoId,
        array $articulosId,
        array $cantidades,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
        array $coloresId = [],
        array $tallesId = [],
    ): void {
        if ($depositoId <= 0) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if (! DepmaeControlStockSupport::manejaControlStock($deposito)) {
            return;
        }

        /** @var array<string, float> $cantidadPorClave */
        $cantidadPorClave = [];
        foreach ($articulosId as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = abs((float) ($cantidades[$i] ?? 0));
            if ($articuloId <= 0 || $cantidad < 1e-9) {
                continue;
            }
            $colorId = isset($coloresId[$i]) ? (int) $coloresId[$i] : 0;
            $talleId = isset($tallesId[$i]) ? (int) $tallesId[$i] : 0;
            [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo(
                $colorId > 0 ? $colorId : null,
                $talleId > 0 ? $talleId : null,
            );
            $clave = $articuloId.'|'.$colorKey.'|'.$talleKey;
            $cantidadPorClave[$clave] = ($cantidadPorClave[$clave] ?? 0.0) + $cantidad;
        }

        if ($cantidadPorClave === []) {
            return;
        }

        self::validarCantidadesPorClave($depositoId, $cantidadPorClave, $saldoRepository);
    }

    /**
     * @param  array<int, float>  $cantidadPorArticulo
     *
     * @deprecated Preferir validarDesdeLineasFormulario con color/talle
     */
    public static function validarCantidadesPorDeposito(
        int $depositoId,
        array $cantidadPorArticulo,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ): void {
        $porClave = [];
        foreach ($cantidadPorArticulo as $articuloId => $cantidad) {
            $porClave[((int) $articuloId).'|0|0'] = (float) $cantidad;
        }
        self::validarCantidadesPorClave($depositoId, $porClave, $saldoRepository);
    }

    /**
     * @param  array<string, float>  $cantidadPorClave  "articuloId|colorKey|talleKey" => cantidad
     */
    public static function validarCantidadesPorClave(
        int $depositoId,
        array $cantidadPorClave,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ): void {
        if ($depositoId <= 0 || $cantidadPorClave === []) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if (! DepmaeControlStockSupport::manejaControlStock($deposito)) {
            return;
        }

        $articuloIds = [];
        $colorIds = [];
        $talleIds = [];
        foreach (array_keys($cantidadPorClave) as $clave) {
            [$articuloId, $colorKey, $talleKey] = array_map('intval', explode('|', $clave));
            $articuloIds[] = $articuloId;
            if ($colorKey > 0) {
                $colorIds[] = $colorKey;
            }
            if ($talleKey > 0) {
                $talleIds[] = $talleKey;
            }
        }

        $articulos = Articulo::query()
            ->whereIn('id', array_values(array_unique($articuloIds)))
            ->get(['id', 'sku', 'descripcion'])
            ->keyBy('id');
        $colores = $colorIds === []
            ? collect()
            : Color::query()->whereIn('id', array_values(array_unique($colorIds)))->get(['id', 'nombre'])->keyBy('id');
        $talles = $talleIds === []
            ? collect()
            : Talle::query()->whereIn('id', array_values(array_unique($talleIds)))->get(['id', 'nombre'])->keyBy('id');

        foreach ($cantidadPorClave as $clave => $cantidad) {
            [$articuloId, $colorKey, $talleKey] = array_map('intval', explode('|', $clave));
            $saldo = $saldoRepository->saldoVariante(
                $articuloId,
                $depositoId,
                $colorKey > 0 ? $colorKey : null,
                $talleKey > 0 ? $talleKey : null,
            );
            if ($cantidad > $saldo + 0.000001) {
                $art = $articulos->get($articuloId);
                $ref = $art
                    ? trim((string) $art->sku).' — '.trim((string) $art->descripcion)
                    : 'artículo ID '.$articuloId;
                if ($colorKey > 0) {
                    $ref .= ' / color '.($colores->get($colorKey)->nombre ?? $colorKey);
                }
                if ($talleKey > 0) {
                    $ref .= ' / talle '.($talles->get($talleKey)->nombre ?? $talleKey);
                }

                throw new \InvalidArgumentException(
                    'La cantidad supera el saldo disponible para '.$ref
                    .'. Saldo: '.self::formatearCantidad($saldo)
                    .', solicitado: '.self::formatearCantidad($cantidad).'.'
                );
            }
        }
    }

    private static function formatearCantidad(float $valor): string
    {
        $texto = number_format($valor, 6, ',', '.');
        $texto = rtrim(rtrim($texto, '0'), ',');

        return $texto === '' ? '0' : $texto;
    }
}
