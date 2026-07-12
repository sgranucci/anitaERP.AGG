<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
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
     */
    public static function validarDesdeLineasFormulario(
        int $depositoId,
        array $articulosId,
        array $cantidades,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ): void {
        if ($depositoId <= 0) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if (! DepmaeControlStockSupport::manejaControlStock($deposito)) {
            return;
        }

        /** @var array<int, float> $cantidadPorArticulo */
        $cantidadPorArticulo = [];
        foreach ($articulosId as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            $cantidad = abs((float) ($cantidades[$i] ?? 0));
            if ($articuloId <= 0 || $cantidad < 1e-9) {
                continue;
            }
            $cantidadPorArticulo[$articuloId] = ($cantidadPorArticulo[$articuloId] ?? 0.0) + $cantidad;
        }

        if ($cantidadPorArticulo === []) {
            return;
        }

        self::validarCantidadesPorDeposito($depositoId, $cantidadPorArticulo, $saldoRepository);
    }

    /**
     * @param  array<int, float>  $cantidadPorArticulo
     */
    public static function validarCantidadesPorDeposito(
        int $depositoId,
        array $cantidadPorArticulo,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepository,
    ): void {
        if ($depositoId <= 0 || $cantidadPorArticulo === []) {
            return;
        }

        $deposito = Depmae::query()->find($depositoId);
        if (! DepmaeControlStockSupport::manejaControlStock($deposito)) {
            return;
        }

        $articulos = Articulo::query()
            ->whereIn('id', array_keys($cantidadPorArticulo))
            ->get(['id', 'sku', 'descripcion'])
            ->keyBy('id');

        foreach ($cantidadPorArticulo as $articuloId => $cantidad) {
            $saldo = $saldoRepository->saldo((int) $articuloId, $depositoId);
            if ($cantidad > $saldo + 0.000001) {
                $art = $articulos->get((int) $articuloId);
                $ref = $art
                    ? trim((string) $art->sku).' — '.trim((string) $art->descripcion)
                    : 'artículo ID '.$articuloId;

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
