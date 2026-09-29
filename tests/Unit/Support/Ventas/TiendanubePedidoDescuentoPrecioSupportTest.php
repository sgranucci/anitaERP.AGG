<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\Tiendanube\TiendanubePedidoDescuentoPrecioSupport as S;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): el cupón baja el precio con IVA incluido
 * y no se manda como descuento de pie (AFIP 10048).
 */
final class TiendanubePedidoDescuentoPrecioSupportTest extends TestCase
{
    public function test_cupon_del_pedido_16195_baja_el_articulo_y_deja_el_flete(): void
    {
        $precios = S::aplicar(
            [75600.0, 4700.0],
            [1.0, 1.0],
            ['producto', 'envio'],
            7560.0,
        );

        self::assertSame([68040.0, 4700.0], $precios);
        self::assertSame(72740.0, round($precios[0] + $precios[1], 2));
    }

    public function test_reparte_el_cupon_entre_articulos_y_el_ultimo_absorbe_el_centavo(): void
    {
        $precios = S::aplicar(
            [100.0, 200.0, 50.0],
            [1.0, 1.0, 1.0],
            ['producto', 'producto', 'envio'],
            10.0,
        );

        self::assertSame(96.67, $precios[0]);
        self::assertSame(193.33, $precios[1]);
        self::assertSame(50.0, $precios[2]);
        self::assertSame(340.0, round(array_sum($precios), 2));
    }

    public function test_el_excedente_del_cupon_baja_el_flete(): void
    {
        $precios = S::aplicar(
            [100.0, 40.0],
            [1.0, 1.0],
            ['producto', 'envio'],
            120.0,
        );

        self::assertSame([0.0, 20.0], $precios);
    }

    public function test_cupon_mayor_que_el_pedido_no_arma_precio_negativo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        S::aplicar([100.0], [1.0], ['producto'], 100.01);
    }
}
