<?php

namespace Tests\Unit\Support\Ventas\AnitaImport;

use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportVentaMatchSupport;
use Tests\TestCase;

class ClienteCuentacorrienteAnitaImportFormatoSupportTest extends TestCase
{
    public function test_letra_desde_codigo_venta(): void
    {
        $this->assertSame(
            'A',
            ClienteCuentacorrienteAnitaImportVentaMatchSupport::letraDesdeCodigoVenta('FAC A-00012-00083016')
        );
        $this->assertSame(
            'B',
            ClienteCuentacorrienteAnitaImportVentaMatchSupport::letraDesdeCodigoVenta('NCA B-00001-00000012')
        );
    }

    public function test_perfil_agg_incluye_cliv_empresa(): void
    {
        config([
            'app.empresa' => 'AGG',
            'cliente_cuentacorriente_anita.climov_tiene_empresa' => null,
            'cliente_cuentacorriente_anita.campos_climov' => '',
            'cliente_cuentacorriente_anita.campos_aplmov' => '',
            'cliente_cuentacorriente_anita.tipos_no_deuda' => ['COB', 'COA'],
            'cliente_cuentacorriente_anita.aplmov_fallback_ref_como_cob' => true,
            'cliente_cuentacorriente_anita.tolerancia_aplicado' => 0.02,
            'cliente_cuentacorriente_anita.bridge_list_reintentos' => 3,
            'cliente_cuentacorriente_anita.sistema' => 'ventas',
            'cliente_cuentacorriente_anita.tabla_climov' => 'climov',
            'cliente_cuentacorriente_anita.tabla_aplmov' => 'aplmov',
        ]);

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $this->assertTrue($perfil['climov_tiene_empresa']);
        $this->assertStringContainsString('cliv_empresa', $perfil['campos_climov']);
        $this->assertTrue(ClienteCuentacorrienteAnitaImportFormatoSupport::esTipoNoDeuda('COB', $perfil));
        $this->assertFalse(ClienteCuentacorrienteAnitaImportFormatoSupport::esTipoNoDeuda('FAC', $perfil));
    }

    public function test_perfil_ferli_omite_cliv_empresa(): void
    {
        config([
            'app.empresa' => 'Calzados Ferli',
            'cliente_cuentacorriente_anita.climov_tiene_empresa' => null,
            'cliente_cuentacorriente_anita.campos_climov' => '',
            'cliente_cuentacorriente_anita.campos_aplmov' => '',
            'cliente_cuentacorriente_anita.tipos_no_deuda' => ['COB'],
            'cliente_cuentacorriente_anita.aplmov_fallback_ref_como_cob' => true,
            'cliente_cuentacorriente_anita.tolerancia_aplicado' => 0.02,
            'cliente_cuentacorriente_anita.bridge_list_reintentos' => 3,
            'cliente_cuentacorriente_anita.sistema' => 'ventas',
            'cliente_cuentacorriente_anita.tabla_climov' => 'climov',
            'cliente_cuentacorriente_anita.tabla_aplmov' => 'aplmov',
        ]);

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $this->assertFalse($perfil['climov_tiene_empresa']);
        $this->assertStringNotContainsString('cliv_empresa', $perfil['campos_climov']);
    }
}
