<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use Tests\TestCase;

class IngresoEgresoAnitaTesmovReferenciaControlTest extends TestCase
{
    public function test_compensatorio_opp_usa_aop_del_nro_original(): void
    {
        $tipoOpp = new Tipotransaccion_Caja(['abreviatura' => 'OPP']);
        $orig = new Caja_Movimiento([
            'numerotransaccion' => 124876,
            'caja_movimiento_origen_id' => null,
        ]);
        $orig->setRelation('tipotransaccioncajas', $tipoOpp);

        $rev = new Caja_Movimiento([
            'numerotransaccion' => 124886,
            'caja_movimiento_origen_id' => 108062,
        ]);
        $rev->setRelation('tipotransaccioncajas', $tipoOpp);
        $rev->setRelation('movimientoOrigen', $orig);

        $ref = IngresoEgresoAnitaTesmovSupport::referenciaAnitaParaControl($rev);

        $this->assertSame('AOP', $ref['tipo']);
        $this->assertSame(124876, $ref['numero']);
    }

    public function test_alta_opp_usa_tipo_y_nro_erp(): void
    {
        $tipoOpp = new Tipotransaccion_Caja(['abreviatura' => 'OPP']);
        $mov = new Caja_Movimiento([
            'numerotransaccion' => 124887,
            'caja_movimiento_origen_id' => null,
        ]);
        $mov->setRelation('tipotransaccioncajas', $tipoOpp);

        $ref = IngresoEgresoAnitaTesmovSupport::referenciaAnitaParaControl($mov);

        $this->assertSame('OPP', $ref['tipo']);
        $this->assertSame(124887, $ref['numero']);
    }
}
