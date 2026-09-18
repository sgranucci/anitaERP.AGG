<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\CondicionIIBB;
use App\Support\Compras\Retencion\RetencionIibbElegibilidadSupport;
use PHPUnit\Framework\TestCase;

class RetencionIibbElegibilidadSupportTest extends TestCase
{
    public function test_no_retener_en_maestro_no_corresponde(): void
    {
        $condicion = new CondicionIIBB;
        $condicion->id = 4;
        $condicion->formacalculo = 'N';
        $condicion->estado = 'A';

        $proveedor = new Proveedor;
        $proveedor->condicionIIBB_id = 4;
        $proveedor->setRelation('condicionIIBBs', $condicion);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor));
    }

    public function test_convenio_que_retiene_si_corresponde(): void
    {
        $condicion = new CondicionIIBB;
        $condicion->id = 1;
        $condicion->formacalculo = 'R';
        $condicion->estado = 'A';

        $proveedor = new Proveedor;
        $proveedor->condicionIIBB_id = 1;
        $proveedor->setRelation('condicionIIBBs', $condicion);

        $this->assertTrue(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor));
    }

    public function test_sin_condicion_iibb_no_retiene(): void
    {
        $proveedor = new Proveedor;
        $proveedor->condicionIIBB_id = null;
        $proveedor->setRelation('condicionIIBBs', null);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor));
    }

    public function test_monotributo_no_impide_si_el_maestro_retiene(): void
    {
        $condicion = new CondicionIIBB;
        $condicion->id = 2;
        $condicion->formacalculo = 'R';
        $condicion->estado = 'A';

        $proveedor = new Proveedor;
        $proveedor->condicioniva_id = 4;
        $proveedor->condicionIIBB_id = 2;
        $proveedor->setRelation('condicionIIBBs', $condicion);

        $this->assertTrue(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor));
    }
}
