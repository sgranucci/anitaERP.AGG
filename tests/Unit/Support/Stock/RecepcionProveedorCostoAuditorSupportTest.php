<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorCostoAuditorSupport as Reglas;
use PHPUnit\Framework\TestCase;

final class RecepcionProveedorCostoAuditorSupportTest extends TestCase
{
    public function test_mediana_ignora_ceros_y_promedia_pares(): void
    {
        $this->assertNull(Reglas::mediana([]));
        $this->assertSame(2.0, Reglas::mediana([3.0, 0.0, 1.0, 2.0]));
        $this->assertSame(2.5, Reglas::mediana([1.0, 2.0, 3.0, 4.0]));
    }

    public function test_precio_doble_de_la_oc_se_detecta_como_duplicado(): void
    {
        $desvio = Reglas::evaluarDesvio(53024.8, 26512.4, 1.5, 20.0);

        $this->assertNotNull($desvio);
        $this->assertSame(2.0, $desvio['ratio']);
        $this->assertStringContainsString('2 veces', $desvio['pista']);
    }

    public function test_precio_unitario_cargado_como_caja(): void
    {
        $desvio = Reglas::evaluarDesvio(1325.62, 26512.4, 1.5, 20.0);

        $this->assertNotNull($desvio);
        $this->assertStringContainsString('precio por unidad cargado como precio por envase de 20', $desvio['pista']);
    }

    public function test_variacion_normal_no_alerta(): void
    {
        $this->assertNull(Reglas::evaluarDesvio(26512.4, 22183.28, 1.5));
        $this->assertNull(Reglas::evaluarDesvio(100.0, null, 1.5));
    }

    public function test_unidades_desde_descripcion_proveedor(): void
    {
        $this->assertSame(20.0, Reglas::unidadesDesdeDescripcion('CHOC COFLER BLOCK  20U 30GR'));
        $this->assertSame(12.0, Reglas::unidadesDesdeDescripcion('ALFAJOR X12'));
        $this->assertNull(Reglas::unidadesDesdeDescripcion('CHOC COFLER BLOCK 30GR'));
    }

    public function test_proveedor_en_caja_con_articulo_en_unidades_sin_coeficiente(): void
    {
        $this->assertTrue(Reglas::faltaCoeficienteProveedor(6, 5, 1.0));
        $this->assertFalse(Reglas::faltaCoeficienteProveedor(6, 5, 20.0));
        $this->assertFalse(Reglas::faltaCoeficienteProveedor(6, 6, 1.0));
    }

    public function test_linea_en_otra_um_sin_conversion(): void
    {
        $this->assertTrue(Reglas::lineaSinConversion(1, 5, 1.0));
        $this->assertFalse(Reglas::lineaSinConversion(6, 6, 1.0));
        $this->assertFalse(Reglas::lineaSinConversion(6, 5, 20.0));
    }

    public function test_cambios_conversion_ignora_formato_numerico(): void
    {
        $cambios = Reglas::cambiosConversion(
            ['unidadmedida_id' => 6, 'coeficienteconversion' => 20, 'unidadesxenvase' => '20.000000', 'descripcion' => 'A'],
            ['unidadmedida_id' => '5', 'coeficienteconversion' => '1', 'unidadesxenvase' => '20', 'descripcion' => 'B'],
        );

        $this->assertSame(['unidadmedida_id', 'coeficienteconversion'], array_column($cambios, 'campo'));
    }

    public function test_receta_bajo_costo_respeta_precio_minimo(): void
    {
        $this->assertTrue(Reglas::recetaBajoCosto(2651.24, 2600.0, 100.0));
        $this->assertFalse(Reglas::recetaBajoCosto(1325.62, 2600.0, 100.0));
        $this->assertFalse(Reglas::recetaBajoCosto(500.0, 0.01, 100.0));
    }
}
