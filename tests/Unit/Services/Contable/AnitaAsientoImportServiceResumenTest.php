<?php

namespace Tests\Unit\Services\Contable;

use App\Services\Contable\AnitaAsientoImportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnitaAsientoImportServiceResumenTest extends TestCase
{
    #[Test]
    public function tes_com_vta_con_asi_mon_ref_menos_uno_no_son_resumen(): void
    {
        foreach (['T', 'C', 'V'] as $sistema) {
            $this->assertFalse(
                AnitaAsientoImportService::esAsientoResumenSubdiario((object) [
                    'ctav_sistema' => $sistema,
                    'ctav_asi_mon_ref' => AnitaAsientoImportService::ASI_MON_REF_ORIGEN_ERP,
                ]),
                "sistema {$sistema} con asi_mon_ref=-1 es nativo ERP"
            );
        }
    }

    #[Test]
    public function tes_com_vta_con_asi_mon_ref_distinto_de_menos_uno_son_resumen(): void
    {
        foreach (['T', 'C', 'V'] as $sistema) {
            $this->assertTrue(
                AnitaAsientoImportService::esAsientoResumenSubdiario((object) [
                    'ctav_sistema' => $sistema,
                    'ctav_asi_mon_ref' => 0,
                ]),
                "sistema {$sistema} con asi_mon_ref=0 es resumen de subdiario"
            );
        }
    }

    #[Test]
    public function personal_nunca_es_resumen_aunque_asi_mon_ref_no_sea_menos_uno(): void
    {
        $this->assertFalse(
            AnitaAsientoImportService::esAsientoResumenSubdiario((object) [
                'ctav_sistema' => 'P',
                'ctav_asi_mon_ref' => 0,
            ])
        );
    }
}
