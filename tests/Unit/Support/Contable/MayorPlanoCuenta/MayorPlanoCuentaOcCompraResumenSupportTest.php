<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaOcCompraResumenSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaOcCompraResumenSupportTest extends TestCase
{
    public function test_resumen_agrupa_items_y_prioriza_detalle_de_linea(): void
    {
        $texto = MayorPlanoCuentaOcCompraResumenSupport::resumenDeterministico([
            ['descripcion' => 'Asado', 'detalle' => '', 'cantidad' => 10],
            ['descripcion' => 'Asado', 'detalle' => '', 'cantidad' => 5],
            ['descripcion' => 'Servicio', 'detalle' => 'Limpieza de planta', 'cantidad' => 1],
        ]);

        $this->assertSame('Compra de 15 × Asado, Limpieza de planta', $texto);
    }

    public function test_resumen_sin_items_queda_vacio(): void
    {
        $this->assertSame('', MayorPlanoCuentaOcCompraResumenSupport::resumenDeterministico([]));
    }

    public function test_nombre_item_usa_partida_si_no_hay_articulo(): void
    {
        $this->assertSame('Alquiler de equipos', MayorPlanoCuentaOcCompraResumenSupport::nombreItem([
            'sku' => '',
            'descripcion' => '',
            'detalle' => '',
            'partida' => 'Alquiler de equipos',
        ]));
    }
}