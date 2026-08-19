<?php

namespace Tests\Unit\Services\Caja;

use App\Services\Caja\Flash\FlashReporteAggExcelService;
use Tests\TestCase;

class FlashReporteAggExcelServiceTest extends TestCase
{
    public function test_la_plantilla_oficial_esta_en_el_repo(): void
    {
        $service = new FlashReporteAggExcelService;
        $ruta = $service->rutaPlantilla();

        $this->assertFileExists($ruta);
        $this->assertGreaterThan(100000, filesize($ruta));
    }
}
