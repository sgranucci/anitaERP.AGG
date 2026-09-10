<?php

namespace Tests\Unit\Support\Compras;

use App\Services\Compras\SuscripcionService;
use App\Support\Compras\SuscripcionSupport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SuscripcionSupportTest extends TestCase
{
    public function test_sku_articulo_default_es_900000_081(): void
    {
        $this->assertSame('900000-081', SuscripcionSupport::ARTICULO_SKU_DEFAULT);
    }

    public function test_alta_exige_articulo_y_crea_linea_oc(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(SuscripcionService::class))->getFileName()
        );

        $this->assertStringContainsString("'articulo_id' => 'required|integer|exists:articulo,id'", $src);
        $this->assertStringContainsString('$this->asegurarLineaArticulo($oc, $articuloResuelto[\'articulo\'], $monto, $renovacion);', $src);
    }
}
