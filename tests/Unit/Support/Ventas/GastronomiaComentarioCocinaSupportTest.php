<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\GastronomiaComentarioCocinaSupport;
use App\Support\Ventas\GastronomiaVentaEmisionMapSupport;
use Tests\TestCase;

final class GastronomiaComentarioCocinaSupportTest extends TestCase
{
    public function test_normalizar_recorta_y_limpia(): void
    {
        $this->assertNull(GastronomiaComentarioCocinaSupport::normalizar('   '));
        $this->assertSame('Sin sal', GastronomiaComentarioCocinaSupport::normalizar('  Sin   sal '));
    }

    public function test_map_linea_cuenta_a_emision_para_comentario(): void
    {
        $linea = new CuentaGastronomiaLinea;
        $linea->forceFill([
            'id' => 1,
            'articulo_id' => 10,
            'comentario_cocina' => 'Extra queso',
        ]);

        $cuenta = new CuentaGastronomia;
        $cuenta->setRelation('lineas', collect([$linea]));

        $emision = new Venta_Emision;
        $emision->forceFill([
            'id' => 100,
            'articulo_id' => 10,
            'numeroitem' => 1,
            'cantidad' => 1,
            'precio' => 100.,
        ]);

        $venta = new Venta(['id' => 50]);
        $venta->setRelation('venta_emisiones', collect([$emision]));

        $map = GastronomiaVentaEmisionMapSupport::mapLineasCuentaAVentaEmision($venta, $cuenta->lineas);

        $this->assertSame([1 => 100], $map);
        $this->assertSame('Extra queso', GastronomiaComentarioCocinaSupport::normalizar($linea->comentario_cocina));
    }
}
