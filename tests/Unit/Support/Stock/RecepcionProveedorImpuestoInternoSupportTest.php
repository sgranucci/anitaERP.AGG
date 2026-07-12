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
