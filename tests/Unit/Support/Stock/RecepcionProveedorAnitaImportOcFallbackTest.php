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

    public function test_numero_oc_prioriza_pep_nro_fac_sobre_com_nro(): void
    {
        $cab = (object) [
            'recm_com_nro' => 5164,
            'recm_tipo_fac' => 'PEP',
            'recm_nro_fac' => 215752,
        ];

        $this->assertSame(215752, RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab));
    }

    public function test_numero_oc_usa_com_nro_si_no_hay_pep(): void
    {
        $cab = (object) [
            'recm_com_nro' => 218023,
            'recm_tipo_fac' => '',
            'recm_nro_fac' => 0,
        ];

        $this->assertSame(218023, RecepcionProveedorAnitaImportSupport::numeroOrdencompraDesdeCabecera($cab));
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

    public function test_numerofactura_prioriza_recm_ref_sobre_pep(): void
    {
        $cab = (object) [
            'recm_ref_tipo' => 'REM',
            'recm_ref_letra' => 'R',
            'recm_ref_sucursal' => 1,
            'recm_ref_nro' => 12345,
            'recm_tipo_fac' => 'PEP',
            'recm_letra_fac' => 'X',
            'recm_sucursal_fac' => 1,
            'recm_nro_fac' => 219162,
        ];

        $this->assertSame(
            'REM R0001-12345',
            RecepcionProveedorAnitaImportSupport::numerofacturaDesdeCabecera($cab)
        );
    }

    public function test_numerofactura_ignora_pep_en_fac_y_usa_cod_remito(): void
    {
        $cab = (object) [
            'recm_ref_tipo' => '',
            'recm_ref_nro' => 0,
            'recm_tipo_fac' => 'PEP',
            'recm_nro_fac' => 219162,
            'recm_cod_remito' => 'R-9988',
        ];

        $this->assertSame(
            'R-9988',
            RecepcionProveedorAnitaImportSupport::numerofacturaDesdeCabecera($cab)
        );
    }

    public function test_numerofactura_usa_fac_si_no_es_oc(): void
    {
        $cab = (object) [
            'recm_ref_nro' => 0,
            'recm_tipo_fac' => 'FAC',
            'recm_letra_fac' => 'A',
            'recm_sucursal_fac' => 6,
            'recm_nro_fac' => 186624,
        ];

        $this->assertSame(
            'FAC A0006-186624',
            RecepcionProveedorAnitaImportSupport::numerofacturaDesdeCabecera($cab)
        );
    }

    public function test_numerofactura_vacio_si_solo_hay_pep(): void
    {
        $cab = (object) [
            'recm_ref_nro' => 0,
            'recm_tipo_fac' => 'PEP',
            'recm_nro_fac' => 219162,
            'recm_cod_remito' => '',
        ];

        $this->assertSame('', RecepcionProveedorAnitaImportSupport::numerofacturaDesdeCabecera($cab));
    }
}
