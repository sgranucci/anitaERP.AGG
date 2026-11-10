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
            ['id' => 2, 'tiponumeracion_id' => 2, 'desde' => 27, 'hasta' => 33],
            ['id' => 3, 'tiponumeracion_id' => 2, 'desde' => 34, 'hasta' => 41],
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

    public function test_linea_ninos_parte_en_las_tres_listas_de_fabrica(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 1, 'fechavigencia' => '2026-10-27', 'precio' => 41800],
            ['id' => 2, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 2, 'fechavigencia' => '2026-10-27', 'precio' => 44300],
            ['id' => 3, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 3, 'fechavigencia' => '2026-10-27', 'precio' => 46400],
        ]);
        $fila = [
            'articulo_id' => 50,
            'combinacion_id' => 9,
            'sku' => '11000702',
            'lote' => '30529',
            'precio' => 999,
            'medidas' => [
                ['medida' => '22', 'cantidad' => 2],
                ['medida' => '28', 'cantidad' => 4],
                ['medida' => '36', 'cantidad' => 6],
            ],
            'total_pares' => 12,
        ];

        $partes = P::expandirPorLista($fila, [50 => 2], $this->listas(), $indice);

        self::assertCount(3, $partes);
        self::assertSame(41800.0, (float) $partes[0]['precio']);
        self::assertSame(2.0, (float) $partes[0]['total_pares']);
        self::assertSame([['medida' => '22', 'cantidad' => 2.0]], $partes[0]['medidas']);
        self::assertSame(44300.0, (float) $partes[1]['precio']);
        self::assertSame(4.0, (float) $partes[1]['total_pares']);
        self::assertSame(46400.0, (float) $partes[2]['precio']);
        self::assertSame(6.0, (float) $partes[2]['total_pares']);
    }

    public function test_dama_con_una_sola_lista_no_se_parte(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 10, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 20200],
        ]);
        $fila = [
            'articulo_id' => 10,
            'combinacion_id' => 1,
            'precio' => 1,
            'medidas' => [
                ['medida' => '36', 'cantidad' => 4],
                ['medida' => '37', 'cantidad' => 4],
            ],
            'total_pares' => 8,
        ];

        $partes = P::expandirPorLista($fila, [10 => 1], $this->listas(), $indice);

        self::assertCount(1, $partes);
        self::assertSame(20200.0, (float) $partes[0]['precio']);
        self::assertSame(8.0, (float) $partes[0]['total_pares']);
    }

    public function test_varias_lineas_en_el_mismo_lote_de_filas_parten_solo_ninos(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 1, 'fechavigencia' => '2026-10-27', 'precio' => 100],
            ['id' => 2, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 2, 'fechavigencia' => '2026-10-27', 'precio' => 200],
            ['id' => 3, 'articulo_id' => 10, 'combinacion_id' => 0, 'listaprecio_id' => 4, 'fechavigencia' => '2026-09-07', 'precio' => 300],
        ]);
        $tipo = [50 => 2, 10 => 1];
        $listas = $this->listas();

        $ninos = P::expandirPorLista([
            'articulo_id' => 50,
            'combinacion_id' => 0,
            'medidas' => [
                ['medida' => '20', 'cantidad' => 1],
                ['medida' => '30', 'cantidad' => 1],
            ],
            'total_pares' => 2,
            'precio' => 0,
        ], $tipo, $listas, $indice);
        $dama = P::expandirPorLista([
            'articulo_id' => 10,
            'combinacion_id' => 0,
            'medidas' => [
                ['medida' => '36', 'cantidad' => 2],
            ],
            'total_pares' => 2,
            'precio' => 0,
        ], $tipo, $listas, $indice);

        self::assertCount(2, $ninos);
        self::assertCount(1, $dama);
        self::assertSame(100.0, (float) $ninos[0]['precio']);
        self::assertSame(200.0, (float) $ninos[1]['precio']);
        self::assertSame(300.0, (float) $dama[0]['precio']);
    }

    public function test_mismo_precio_en_las_listas_no_parte_la_curva(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 1, 'fechavigencia' => '2026-09-07', 'precio' => 18000],
            ['id' => 2, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 2, 'fechavigencia' => '2026-09-07', 'precio' => 18000],
        ]);
        $fila = [
            'articulo_id' => 50,
            'combinacion_id' => 0,
            'precio' => 1,
            'medidas' => [
                ['medida' => '26', 'cantidad' => 2],
                ['medida' => '27', 'cantidad' => 2],
                ['medida' => '28', 'cantidad' => 2],
            ],
            'total_pares' => 6,
        ];

        $partes = P::expandirPorLista($fila, [50 => 2], $this->listas(), $indice);

        self::assertCount(1, $partes);
        self::assertSame(18000.0, (float) $partes[0]['precio']);
        self::assertCount(3, $partes[0]['medidas']);
        self::assertSame(6.0, (float) $partes[0]['total_pares']);
        self::assertArrayNotHasKey('curva_presentada', $partes[0]);
    }

    public function test_precios_distintos_conservan_la_curva_del_modulo_entero(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 1, 'fechavigencia' => '2026-09-07', 'precio' => 15000],
            ['id' => 2, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 2, 'fechavigencia' => '2026-09-07', 'precio' => 18000],
        ]);
        $fila = [
            'articulo_id' => 50,
            'combinacion_id' => 0,
            'precio' => 1,
            'medidas' => [
                ['medida' => '23', 'cantidad' => 20],
                ['medida' => '24', 'cantidad' => 20],
                ['medida' => '25', 'cantidad' => 20],
                ['medida' => '26', 'cantidad' => 20],
                ['medida' => '27', 'cantidad' => 20],
                ['medida' => '28', 'cantidad' => 20],
            ],
            'total_pares' => 120,
            '_presentacion' => [
                'coincide' => true,
                'modulos' => 10,
                'medidas' => [23 => 2, 24 => 2, 25 => 2, 26 => 2, 27 => 2, 28 => 2],
            ],
        ];

        $partes = P::expandirPorLista($fila, [50 => 2], $this->listas(), $indice);

        self::assertCount(2, $partes);
        self::assertSame(15000.0, (float) $partes[0]['precio']);
        self::assertSame(18000.0, (float) $partes[1]['precio']);
        self::assertSame([23 => 2, 24 => 2, 25 => 2, 26 => 2], $partes[0]['curva_presentada']['medidas']);
        self::assertSame(10, $partes[0]['curva_presentada']['modulos']);
        self::assertSame(8.0, (float) $partes[0]['curva_presentada']['pares_modulo']);
        self::assertSame([27 => 2, 28 => 2], $partes[1]['curva_presentada']['medidas']);
        self::assertSame(10, $partes[1]['curva_presentada']['modulos']);
        self::assertSame(4.0, (float) $partes[1]['curva_presentada']['pares_modulo']);
        self::assertSame(40.0, (float) $partes[1]['total_pares']);
        self::assertArrayNotHasKey('_presentacion', $partes[0]);
    }

    public function test_los_tres_precios_salen_juntos_sin_partir_la_curva(): void
    {
        $indice = P::indexarPrecios([
            ['id' => 1, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 1, 'fechavigencia' => '2026-10-27', 'precio' => 41800],
            ['id' => 2, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 2, 'fechavigencia' => '2026-10-27', 'precio' => 44300],
            ['id' => 3, 'articulo_id' => 50, 'combinacion_id' => 0, 'listaprecio_id' => 3, 'fechavigencia' => '2026-10-27', 'precio' => 46400],
        ]);
        $fila = [
            'articulo_id' => 50,
            'combinacion_id' => 9,
            'medidas' => [
                ['medida' => '23', 'cantidad' => 20],
                ['medida' => '28', 'cantidad' => 20],
            ],
            'total_pares' => 40,
        ];

        $precios = P::preciosFabricaDeFila($fila, [50 => 2], $this->listas(), $indice);

        self::assertSame(['16/26', '27/33', '34/41'], array_column($precios, 'etiqueta'));
        self::assertSame([41800.0, 44300.0, 46400.0], array_map('floatval', array_column($precios, 'precio')));
        self::assertSame(
            ['16/26', '27/33', '34/41'],
            P::etiquetasPrecio([['precios_fabrica' => $precios]])
        );
    }
}
