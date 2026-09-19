<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Models\Configuracion\CondicionIIBB;
use App\Models\Configuracion\Condicioniva;
use App\Support\Compras\Retencion\RetencionIibbElegibilidadSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Tests\TestCase;

class RetencionIibbElegibilidadSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['compras.iibb_retencion_omite_monotributo' => null]);
    }

    public function test_no_retener_en_maestro_no_corresponde(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(4, 'N', 1)
        ));
    }

    public function test_convenio_que_retiene_si_corresponde(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $this->assertTrue(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(1, 'R', 1)
        ));
    }

    public function test_sin_condicion_iibb_no_retiene(): void
    {
        $proveedor = new Proveedor;
        $proveedor->condicionIIBB_id = null;
        $proveedor->setRelation('condicionIIBBs', null);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb($proveedor));
    }

    public function test_agg_omite_monotributo_aunque_el_maestro_retenga(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(2, 'R', 4, 'Monotributo A Clientes')
        ));
    }

    public function test_agg_omite_monotributo_c_proveedores(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $this->assertFalse(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(2, 'R', 8, 'Monotributo C Proveedores')
        ));
    }

    public function test_ferli_monotributo_no_impide_si_el_maestro_retiene(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::FERLI]);

        $this->assertTrue(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(2, 'R', 4, 'Monotributo A Clientes')
        ));
    }

    public function test_flag_false_en_agg_deja_entrar_monotributo(): void
    {
        config([
            'app.empresa' => EntornoEmpresaSupport::AGG,
            'compras.iibb_retencion_omite_monotributo' => 'false',
        ]);

        $this->assertTrue(RetencionIibbElegibilidadSupport::correspondePorCondicionIibb(
            $this->proveedorConIibb(2, 'R', 4, 'Monotributo A Clientes')
        ));
    }

    private function proveedorConIibb(
        int $iibbId,
        string $formaCalculo,
        int $ivaId,
        ?string $ivaNombre = null,
    ): Proveedor {
        $condicion = new CondicionIIBB;
        $condicion->id = $iibbId;
        $condicion->formacalculo = $formaCalculo;
        $condicion->estado = 'A';

        $proveedor = new Proveedor;
        $proveedor->condicionIIBB_id = $iibbId;
        $proveedor->condicioniva_id = $ivaId;
        $proveedor->setRelation('condicionIIBBs', $condicion);

        $iva = new Condicioniva;
        $iva->id = $ivaId;
        $iva->nombre = $ivaNombre ?? ($ivaId === 4 ? 'Monotributo A Clientes' : 'Responsable Inscripto');
        $proveedor->setRelation('condicionivas', $iva);

        return $proveedor;
    }
}
