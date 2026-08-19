<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Tests\TestCase;

class RecepcionProveedorAnitaImportOcFallbackTest extends TestCase
{
    public function test_where_recepmae_por_oc_busca_com_nro_y_nro_fac(): void
    {
        $where = RecepcionProveedorAnitaImportSupport::whereRecepmaePorOrdencompra(218023);

        $this->assertStringContainsString("recm_tipo = 'COM'", $where);
        $this->assertStringContainsString("recm_letra = 'X'", $where);
        $this->assertStringContainsString('recm_com_nro = 218023', $where);
        $this->assertStringContainsString('recm_nro_fac = 218023', $where);
    }

    public function test_where_aplicped_por_oc_usa_pep_y_com(): void
    {
        $where = RecepcionProveedorAnitaImportSupport::whereAplicpedPorOrdencompra(218023);

        $this->assertStringContainsString("aplp_ref_tipo = 'PEP'", $where);
        $this->assertStringContainsString('aplp_ref_nro = 218023', $where);
        $this->assertStringContainsString("aplp_tipo = 'COM'", $where);
    }

    public function test_numero_oc_desde_cabecera_usa_nro_fac_cuando_es_pep(): void
    {
        $cab = (object) [
            'recm_com_nro' => 0,
            'recm_tipo_fac' => 'PEP',
            'recm_nro_fac' => 218023,
        ];

        $this->assertSame(218023, RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab));
    }

    public function test_numero_oc_prioriza_recm_com_nro(): void
    {
        $cab = (object) [
            'recm_com_nro' => 99001,
            'recm_tipo_fac' => 'PEP',
            'recm_nro_fac' => 218023,
        ];

        $this->assertSame(99001, RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab));
    }

    public function test_unir_claves_deduplica_recepmae_y_aplicped(): void
    {
        $cabeceras = [
            (object) ['recm_sucursal' => 3, 'recm_nro' => 50485],
            (object) ['recm_sucursal' => 3, 'recm_nro' => 50485],
        ];
        $aplicped = [
            (object) ['aplp_tipo' => 'COM', 'aplp_letra' => 'X', 'aplp_sucursal' => 3, 'aplp_nro' => 50485],
            (object) ['aplp_tipo' => 'COM', 'aplp_letra' => 'X', 'aplp_sucursal' => 1, 'aplp_nro' => 60001],
        ];

        $claves = RecepcionProveedorAnitaImportSupport::unirClavesCom($cabeceras, $aplicped);

        $this->assertCount(2, $claves);
        $this->assertSame(
            [
                ['tipo' => 'COM', 'letra' => 'X', 'sucursal' => 3, 'nro' => 50485],
                ['tipo' => 'COM', 'letra' => 'X', 'sucursal' => 1, 'nro' => 60001],
            ],
            $claves
        );
    }

    public function test_unir_claves_ignora_filas_sin_nro(): void
    {
        $this->assertSame(
            [],
            RecepcionProveedorAnitaImportSupport::unirClavesCom(
                [(object) ['recm_sucursal' => 3, 'recm_nro' => 0]],
                [(object) ['aplp_sucursal' => 0, 'aplp_nro' => 50485]]
            )
        );
    }
}
