<?php

namespace Tests\Unit\Support\Contable\Efe;

use App\Models\Caja\Cuentacaja;
use App\Support\Caja\PosicionFinancieraOrdenConceptoSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaValoresCuentacajaSupport;
use Tests\TestCase;

class EfePosicionFinancieraMediosMaquinaErpTest extends TestCase
{
    public function test_etiqueta_concepto_maquina_usa_familia_cuentacaja_no_valormae(): void
    {
        $dolar = new Cuentacaja([
            'codigo' => '110',
            'nombre' => 'CAJA DOLAR BIYEMAS',
            'descripcion_operaciones' => null,
        ]);
        $euro = new Cuentacaja([
            'codigo' => '129',
            'nombre' => 'CAJA EURO BIYEMAS',
            'descripcion_operaciones' => '',
        ]);
        $pesos = new Cuentacaja([
            'codigo' => '100',
            'nombre' => 'CAJA PESOS BIYEMAS',
            'descripcion_operaciones' => 'Efectivo pesos',
        ]);

        $this->assertSame(
            'Efectivo dólares',
            PosicionFinancieraOrdenConceptoSupport::etiquetaConceptoMaquina($dolar),
        );
        $this->assertSame(
            'Efectivo euros',
            PosicionFinancieraOrdenConceptoSupport::etiquetaConceptoMaquina($euro),
        );
        $this->assertSame(
            'Efectivo pesos',
            PosicionFinancieraOrdenConceptoSupport::etiquetaConceptoMaquina($pesos),
        );
    }

    public function test_monto_me_se_pasa_a_pesos_con_cotizacion(): void
    {
        $this->assertSame(
            3090000.0,
            RendicionMaquinaValoresCuentacajaSupport::montoEnPesos(2, 2060.0, 1500.0),
        );
    }
}
