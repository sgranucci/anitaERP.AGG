<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Sicore;

use App\Support\Contable\Sicore\SicoreErpComplementoSupport;
use PHPUnit\Framework\TestCase;

class SicoreErpComplementoSupportTest extends TestCase
{
    public function test_omite_erp_si_ya_esta_el_mismo_comp_y_certificado(): void
    {
        $anita = [
            ['nro_comp' => 124100, 'nro_cert' => 88001, 'importe' => 1500.50],
        ];
        $erp = [
            ['nro_comp' => 124100, 'nro_cert' => 88001, 'importe' => 1500.50, 'origen' => 'compras_ganancias_erp'],
            ['nro_comp' => 124828, 'nro_cert' => 99001, 'importe' => 29448.30, 'origen' => 'compras_ganancias_erp'],
        ];

        $nuevos = SicoreErpComplementoSupport::soloNuevos($erp, $anita);

        $this->assertCount(1, $nuevos);
        $this->assertSame(124828, $nuevos[0]['nro_comp']);
    }

    public function test_omite_erp_si_coincide_comp_e_importe_aunque_falte_el_certificado(): void
    {
        $anita = [
            ['nro_comp' => 124100, 'nro_cert' => 0, 'importe' => 6800.00],
        ];
        $erp = [
            ['nro_comp' => 124100, 'nro_cert' => 12, 'importe' => 6800.00],
        ];

        $this->assertSame([], SicoreErpComplementoSupport::soloNuevos($erp, $anita));
    }

    public function test_descarta_anita_sin_base_cuando_el_erp_tiene_la_misma_retencion(): void
    {
        $anita = [
            [
                'nro_comp' => 0,
                'nro_cert' => 459,
                'importe' => 60900.00,
                'base_calculo' => 0,
                'codigo_proveedor' => '005196',
                'fecha_retencion' => '2026-09-30',
                'referencia' => 'Ret.IVA 005196 — FC 0',
            ],
        ];
        $erp = [
            [
                'nro_comp' => 125213,
                'nro_cert' => 459,
                'importe' => 60900.00,
                'base_calculo' => 60900.00,
                'codigo_proveedor' => '5196',
                'fecha_retencion' => '2026-09-30',
                'referencia' => 'Ret.ERP 5196 OPP 1-125213',
            ],
        ];

        $anita = SicoreErpComplementoSupport::descartarSinBaseCubiertos($anita, $erp);

        $this->assertSame([], $anita);
        $this->assertCount(1, SicoreErpComplementoSupport::soloNuevos($erp, $anita));
    }

    public function test_conserva_anita_sin_base_si_no_hay_erp_que_la_cubra(): void
    {
        $anita = [
            [
                'nro_comp' => 0,
                'nro_cert' => 10,
                'importe' => 100.00,
                'base_calculo' => 0,
                'codigo_proveedor' => '1',
                'fecha_retencion' => '2026-09-30',
            ],
        ];

        $this->assertSame($anita, SicoreErpComplementoSupport::descartarSinBaseCubiertos($anita, []));
    }

    public function test_conserva_erp_cuando_anita_esta_vacio(): void
    {
        $erp = [
            ['nro_comp' => 1, 'nro_cert' => 2, 'importe' => 10.00],
        ];

        $this->assertSame($erp, SicoreErpComplementoSupport::soloNuevos($erp, []));
    }
}
