<?php

namespace Tests\Unit\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use App\Support\Contable\CierreRendicionMaquinaTotalesSupport;
use Tests\TestCase;

class CierreRendicionMaquinaTotalesSupportTest extends TestCase
{
    public function test_resultado_un_dia_usa_drop_bruto_y_qr_como_p_vtamaquina(): void
    {
        $rendicion = new RendicionMaquina([
            'inputs_json' => [
                'venta_ficha' => 285928689.09,
                'drop_billete' => 357635363.03,
                'drop_billete_bruto' => 361219650.0,
                'impuesto_drop' => 3584286.97,
                'dropqr_rodillo' => 164732676.46,
                'pago_manual' => 5238008.56,
                'tito' => 563365780.8,
                'hopper' => 0,
                'venta_ruleta' => 0,
                'drop_ruleta' => 5547800,
                'drop_rul_ant' => 6148700,
                'tito_ruleta' => 0,
                'salida_ruleta' => 0,
            ],
        ]);

        $rodillo = CierreRendicionMaquinaTotalesSupport::resultadoRodilloUnDia($rendicion);
        $ruleta = CierreRendicionMaquinaTotalesSupport::resultadoRuletaUnDia($rendicion);

        $this->assertEqualsWithDelta(239692939.22, $rodillo, 0.02);
        $this->assertEqualsWithDelta(5547800.00, $ruleta, 0.02);
        $this->assertEqualsWithDelta(245240739.22, $rodillo + $ruleta, 0.02);
    }
}
