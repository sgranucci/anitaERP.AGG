<?php

namespace Tests\Unit\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use Tests\TestCase;

class RecepcionProveedorImpuestoInternoSupportTest extends TestCase
{
    public const TIPO_CIG_ID = 99;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fijarTipoCigarrilloId(self::TIPO_CIG_ID);
    }

    protected function tearDown(): void
    {
        $this->fijarTipoCigarrilloId(null);
        parent::tearDown();
    }

    public function test_precio_ultima_compra_suma_ii_en_linea_neta(): void
    {
        $this->assertSame(
            5831.545,
            RecepcionProveedorImpuestoInternoSupport::precioUltimaCompraConImpuestoInterno(
                1242.865,
                4588.68,
                true,
            )
        );
    }

    public function test_precio_ultima_compra_no_duplica_ii_si_linea_ya_incluye(): void
    {
        $this->assertSame(
            5831.544,
            RecepcionProveedorImpuestoInternoSupport::precioUltimaCompraConImpuestoInterno(
                5831.544,
                4588.68,
                true,
            )
        );
    }

    public function test_precio_ultima_compra_sin_cigarrillo_no_suma_ii(): void
    {
        $this->assertSame(
            100.0,
            RecepcionProveedorImpuestoInternoSupport::precioUltimaCompraConImpuestoInterno(100.0, 50.0, false)
        );
    }

    public function test_impuesto_interno_por_unidad_prorratea_sobre_cantidad_cigarrillos(): void
    {
        $recepcion = $this->recepcionConLineas([
            [10.0, self::TIPO_CIG_ID],
            [20.0, self::TIPO_CIG_ID],
            [5.0, 1],
        ], 300.0);

        $this->assertSame(30.0, RecepcionProveedorImpuestoInternoSupport::totalCantidadCigarrillos($recepcion));
        $this->assertSame(10.0, RecepcionProveedorImpuestoInternoSupport::impuestoInternoPorUnidad($recepcion));
    }

    public function test_normalizar_impuesto_interno_sin_cigarrillos_devuelve_null(): void
    {
        $this->assertNull(RecepcionProveedorImpuestoInternoSupport::normalizarImpuestoInternoGuardado(150.0, false));
    }

    public function test_normalizar_impuesto_interno_con_cigarrillos_redondea(): void
    {
        $this->assertSame(123.46, RecepcionProveedorImpuestoInternoSupport::normalizarImpuestoInternoGuardado(123.456, true));
    }

    public function test_sku_articulo_impuesto_interno_default(): void
    {
        config(['recepcion_proveedor.sku_articulo_impuesto_interno' => 'IMPINTERNO']);

        $this->assertSame('IMPINTERNO', RecepcionProveedorImpuestoInternoSupport::skuArticuloImpuestoInterno());
    }

    public function test_devolucion_con_cigarrillos_requiere_impuesto_interno(): void
    {
        $devolucion = $this->recepcionConLineas([[10.0, self::TIPO_CIG_ID]], 0.0);
        $devolucion->tipo = Recepcion_Proveedor::TIPO_DEVOLUCION;

        $this->assertTrue(RecepcionProveedorImpuestoInternoSupport::recepcionRequiereImpuestoInterno($devolucion));
    }

    public function test_impuesto_interno_proporcional_entre_recepciones(): void
    {
        $origen = $this->recepcionConLineas([
            [100.0, self::TIPO_CIG_ID],
            [50.0, 1],
        ], 400.0);

        $devolucion = $this->recepcionConLineas([[50.0, self::TIPO_CIG_ID]], 0.0);
        $devolucion->tipo = Recepcion_Proveedor::TIPO_DEVOLUCION;

        $iiEntre = RecepcionProveedorImpuestoInternoSupport::calcularImpuestoInternoProporcionalEntreRecepciones(
            $origen,
            $devolucion
        );

        $this->assertSame(200.0, $iiEntre);
    }

    public function test_assert_impuesto_interno_cero_falla_en_devolucion(): void
    {
        $devolucion = $this->recepcionConLineas([[10.0, self::TIPO_CIG_ID]], 0.0);
        $devolucion->tipo = Recepcion_Proveedor::TIPO_DEVOLUCION;
        $devolucion->impuesto_interno = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('impuesto interno');
        RecepcionProveedorImpuestoInternoSupport::assertImpuestoInternoCumplido($devolucion);
    }

    public function test_diagnostico_impuesto_interno_devolucion_detecta_falta(): void
    {
        $origen = $this->recepcionConLineas([[100.0, self::TIPO_CIG_ID]], 400.0);
        $origen->numerorecepcion = 165556;

        $devolucion = $this->recepcionConLineas([[100.0, self::TIPO_CIG_ID]], 0.0);
        $devolucion->tipo = Recepcion_Proveedor::TIPO_DEVOLUCION;
        $devolucion->impuesto_interno = 0;
        $devolucion->setRelation('recepcion_referencia', $origen);

        $diag = RecepcionProveedorImpuestoInternoSupport::diagnosticoImpuestoInternoDevolucion($devolucion);

        $this->assertNotNull($diag);
        $this->assertSame(400.0, $diag['ii_esperado']);
        $this->assertSame(0.0, $diag['ii_actual']);
        $this->assertStringContainsString('Devolución sin impuesto interno', $diag['mensaje']);
    }

    public function test_diagnostico_impuesto_interno_devolucion_ok_si_coincide(): void
    {
        $origen = $this->recepcionConLineas([[100.0, self::TIPO_CIG_ID]], 400.0);
        $devolucion = $this->recepcionConLineas([[50.0, self::TIPO_CIG_ID]], 200.0);
        $devolucion->tipo = Recepcion_Proveedor::TIPO_DEVOLUCION;
        $devolucion->setRelation('recepcion_referencia', $origen);

        $this->assertNull(
            RecepcionProveedorImpuestoInternoSupport::diagnosticoImpuestoInternoDevolucion($devolucion)
        );
    }

    /** @param list<array{0: float, 1: int}> $lineas */
    private function recepcionConLineas(array $lineas, float $impuestoInterno): Recepcion_Proveedor
    {
        $recepcion = new Recepcion_Proveedor([
            'tipo' => Recepcion_Proveedor::TIPO_RECEPCION,
            'impuesto_interno' => $impuestoInterno,
        ]);

        $coleccion = collect();
        foreach ($lineas as [$cantidad, $tipoArticuloId]) {
            $linea = new Recepcion_Proveedor_Articulo([
                'cantidad' => $cantidad,
                'cantidad_stock' => $cantidad,
            ]);
            $articulo = new Articulo;
            $articulo->tipoarticulo_id = $tipoArticuloId;
            $linea->setRelation('articulos', $articulo);
            $coleccion->push($linea);
        }

        $recepcion->setRelation('recepcion_proveedor_articulos', $coleccion);

        return $recepcion;
    }

    private function fijarTipoCigarrilloId(?int $id): void
    {
        $ref = new \ReflectionClass(RecepcionProveedorImpuestoInternoSupport::class);
        $cache = $ref->getProperty('tipoArticuloCigarrilloIdCache');
        $cache->setAccessible(true);
        $cache->setValue(null, $id);

        $resolved = $ref->getProperty('tipoArticuloCigarrilloIdResolved');
        $resolved->setAccessible(true);
        $resolved->setValue(null, $id !== null);
    }
}
