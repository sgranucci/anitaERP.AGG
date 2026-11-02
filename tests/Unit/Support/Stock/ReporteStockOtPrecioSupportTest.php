<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ReporteStockOtPrecioSupport as P;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): precio de fábrica del Stock por OT.
 */
class ReporteStockOtPrecioSupportTest extends TestCase
{
    /**
     * @return list<array{id: int, tiponumeracion_id: int, desde: int, hasta: int}>
     */
    private function listas(): array
    {
        return [
            ['id' => 1, 'tiponumeracion_id' => 2, 'desde' => 16, 'hasta' => 26],
            ['id' => 4, 'tiponumeracion_id' => 1, 'desde' => 35, 'hasta' => 41],
            ['id' => 5, 'tiponumeracion_id' => 3, 'desde' => 38, 'hasta' => 47],
        ];
    }

    public function test_dama_36_usa_lista_35_41_y_no_la_de_chico_ni_hombre(): void
    {
        self::assertSame(4, P::listaParaTalle('36', 1, $this->listas()));
        self::assertSame(0, P::listaParaTalle('36', 2, $this->listas()));
        self::assertSame(0, P::listaParaTalle('', 1, $this->listas()));
    }

    public function test_goiaba_y_maxxi_toman_la_lista_de_fabrica_de_la_combinacion(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 12196, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 20200],
            ['id' => 2, 'articulo_id' => 12196, 'combinacion_id' => 16841, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 20200],
            ['id' => 3, 'articulo_id' => 12196, 'combinacion_id' => 0, 'listaprecio_id' => 6, 'fechavigencia' => '2026-09-15', 'precio' => 52500],
            ['id' => 4, 'articulo_id' => 11484, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-22', 'precio' => 22300],
            ['id' => 5, 'articulo_id' => 11484, 'combinacion_id' => 14749, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-22', 'precio' => 22300],
            ['id' => 6, 'articulo_id' => 11484, 'combinacion_id' => 0, 'listaprecio_id' => 6, 'fechavigencia' => '2026-09-15', 'precio' => 58000],
        ]);
        $listas = $this->listas();
        $tipo = [12196 => 1, 11484 => 1];

        $goiaba = P::precioDeFila([
            'articulo_id' => 12196,
            'combinacion_id' => 16841,
            'medidas' => [
                ['medida' => '36', 'cantidad' => 8],
                ['medida' => '16', 'cantidad' => -1],
            ],
        ], $tipo, $listas, $indice);
        $maxxi = P::precioDeFila([
            'articulo_id' => 11484,
            'combinacion_id' => 14749,
            'medidas' => [
                ['medida' => '36', 'cantidad' => 4],
                ['medida' => '37', 'cantidad' => 4],
            ],
        ], $tipo, $listas, $indice);

        self::assertSame(20200.0, $goiaba);
        self::assertSame(22300.0, $maxxi);
    }

    public function test_generico_posterior_pisa_la_combinacion_y_la_misma_fecha_no(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 1, 'combinacion_id' => 9, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-01', 'precio' => 100],
            ['id' => 2, 'articulo_id' => 1, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 200],
            ['id' => 3, 'articulo_id' => 2, 'combinacion_id' => 9, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 300],
            ['id' => 4, 'articulo_id' => 2, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 150],
        ]);

        self::assertSame(200.0, P::elegirVigente($indice[1][4], 9));
        self::assertSame(300.0, P::elegirVigente($indice[2][4], 9));
    }

    public function test_sin_lista_para_el_talle_deja_el_precio_del_movimiento(): void
    {
        $precio = P::precioDeFila([
            'articulo_id' => 1,
            'combinacion_id' => 1,
            'medidas' => [['medida' => 'UNICO', 'cantidad' => 1]],
        ], [1 => 1], $this->listas(), []);

        self::assertNull($precio);
    }
}
