<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Caja\IngresoEgresoOperacionVariaSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoOperacionVariaSupportTest extends TestCase
{
    public function test_es_opv_por_abreviatura_y_operacion(): void
    {
        $porAbrev = new Tipotransaccion_Caja([
            'abreviatura' => 'OPV',
            'operacion' => 'I',
        ]);
        $porOp = new Tipotransaccion_Caja([
            'abreviatura' => 'XXX',
            'operacion' => 'V',
        ]);
        $otro = new Tipotransaccion_Caja([
            'abreviatura' => 'EGR',
            'operacion' => 'E',
        ]);

        $this->assertTrue(IngresoEgresoOperacionVariaSupport::esOpv($porAbrev));
        $this->assertTrue(IngresoEgresoOperacionVariaSupport::esOpv($porOp));
        $this->assertFalse(IngresoEgresoOperacionVariaSupport::esOpv($otro));
        $this->assertFalse(IngresoEgresoOperacionVariaSupport::esOpv(null));
    }

    public function test_es_opv_desde_data_por_hint(): void
    {
        $this->assertTrue(IngresoEgresoOperacionVariaSupport::esOpvDesdeData([
            'tipo_abreviatura' => 'OPV',
        ]));
        $this->assertTrue(IngresoEgresoOperacionVariaSupport::esOpvDesdeData([
            'tipo_operacion' => 'V',
        ]));
        $this->assertFalse(IngresoEgresoOperacionVariaSupport::esOpvDesdeData([
            'tipo_abreviatura' => 'EGR',
            'tipotransaccion_caja_id' => 0,
        ]));
    }
}
