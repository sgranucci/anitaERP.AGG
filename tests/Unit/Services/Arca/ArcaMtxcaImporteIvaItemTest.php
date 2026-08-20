<?php

namespace Tests\Unit\Services\Arca;

use App\Services\Arca\ArcaMtxcaFacturaElectronicaService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test puro (sin BD). WSMTXCA valida cada ítem sobre base = cantidad × precioUnitario −
 * importeBonificacion: clase A sin IVA (importeItem = base + IVA) y clase B con IVA incluido.
 */
class ArcaMtxcaImporteIvaItemTest extends TestCase
{
    public function test_factura_a_manda_precio_neto_e_importe_iva(): void
    {
        $items = $this->invokeBuildArrayItems([
            [
                'sku' => 'ABC',
                'descripcion' => 'Producto',
                'cantidad' => 2,
                'precio' => 100,
                'incluyeimpuesto' => 'N',
                'tasa_iva' => 21,
            ],
        ], $this->cabecera(200, 42));

        self::assertCount(1, $items['item']);
        self::assertSame(42.00, $items['item'][0]['importeIVA']);
        self::assertSame(242.00, $items['item'][0]['importeItem']);
        self::assertSame('100.000000', $items['item'][0]['precioUnitario']);
    }

    public function test_factura_b_no_informa_importe_iva_y_el_precio_lo_incluye(): void
    {
        $items = $this->invokeBuildArrayItems([
            [
                'sku' => 'ABC',
                'descripcion' => 'Producto',
                'cantidad' => 2,
                'precio' => 100,
                'incluyeimpuesto' => 'N',
                'tasa_iva' => 21,
            ],
        ], $this->cabecera(200, 42), 6);

        self::assertArrayNotHasKey('importeIVA', $items['item'][0]);
        self::assertSame(242.00, $items['item'][0]['importeItem']);
        self::assertSame('121.000000', $items['item'][0]['precioUnitario']);
    }

    public function test_precio_con_iva_incluido_netea_la_base(): void
    {
        $items = $this->invokeBuildArrayItems([
            [
                'sku' => 'ABC',
                'descripcion' => 'Producto',
                'cantidad' => 1,
                'precio' => 1210,
                'incluyeimpuesto' => '1',
                'tasa_iva' => 21,
            ],
        ], $this->cabecera(1000, 210));

        self::assertSame(210.00, $items['item'][0]['importeIVA']);
        self::assertSame(1210.00, $items['item'][0]['importeItem']);
    }

    public function test_la_linea_exenta_no_lleva_iva_aunque_el_impuesto_diga_veintiuno(): void
    {
        $items = $this->invokeBuildArrayItems([
            [
                'sku' => 'EX',
                'descripcion' => 'Exento',
                'cantidad' => 1,
                'precio' => 500,
                'incluyeimpuesto' => 'N',
                'tasa_iva' => 0,
                'codigo_condicion_iva' => 5,
            ],
        ], ['gravado' => 0, 'exento' => 500, 'nogravado' => 0, 'iva' => 0, 'impuestos' => []]);

        self::assertSame(2, $items['item'][0]['codigoCondicionIVA']);
        self::assertArrayNotHasKey('importeIVA', $items['item'][0]);
        self::assertSame(500.00, $items['item'][0]['importeItem']);
    }

    public function test_el_descuento_de_linea_viaja_como_bonificacion(): void
    {
        $items = $this->invokeBuildArrayItems([
            [
                'sku' => 'ABC',
                'descripcion' => 'Producto',
                'cantidad' => 10,
                'precio' => 100,
                'totalcondescuento' => 900,
                'incluyeimpuesto' => 'N',
                'tasa_iva' => 21,
            ],
        ], $this->cabecera(900, 189));

        self::assertSame('100.00', $items['item'][0]['importeBonificacion']);
        self::assertSame(189.00, $items['item'][0]['importeIVA']);
        self::assertSame(1089.00, $items['item'][0]['importeItem']);
    }

    public function test_sin_lineas_arma_el_detalle_desde_la_cabecera(): void
    {
        $items = $this->invokeBuildArrayItems([], $this->cabecera(1000, 210));

        self::assertCount(1, $items['item']);
        self::assertSame(210.00, $items['item'][0]['importeIVA']);
        self::assertSame(1210.00, $items['item'][0]['importeItem']);
    }

    /**
     * @return array<string, mixed>
     */
    private function cabecera(float $gravado, float $iva): array
    {
        return [
            'gravado' => $gravado,
            'exento' => 0,
            'nogravado' => 0,
            'iva' => $iva,
            'impuestos' => [['id' => 5, 'base_imp' => $gravado, 'importe' => $iva]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  array<string, mixed>  $datos
     * @return array{item: list<array<string, mixed>>}|null
     */
    private function invokeBuildArrayItems(array $lineas, array $datos, int $cbteTipo = 1): ?array
    {
        $svc = (new ReflectionClass(ArcaMtxcaFacturaElectronicaService::class))->newInstanceWithoutConstructor();
        $m = (new ReflectionClass($svc))->getMethod('buildArrayItems');
        $m->setAccessible(true);

        return $m->invoke($svc, $lineas, $datos, $cbteTipo);
    }
}
