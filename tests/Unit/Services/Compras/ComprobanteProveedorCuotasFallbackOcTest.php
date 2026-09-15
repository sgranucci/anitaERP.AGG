<?php

namespace Tests\Unit\Services\Compras;

use App\Services\Compras\ComprobanteProveedorCondicionPagoDesdeOcService;
use App\Services\Compras\ComprobanteProveedorCuentacorrienteService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ComprobanteProveedorCuotasFallbackOcTest extends TestCase
{
    public function test_resolver_repite_cuota_anterior_si_no_hay_occ_pendiente(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorCondicionPagoDesdeOcService::class))->getFileName()
        );

        $this->assertStringContainsString('fallbackDesdeCuotaAnterior', $src);
        $this->assertStringNotContainsString('sugerirCuotasDesdeCondicionpago', $src);
        $this->assertStringContainsString('No inventa desde condicionpago', $src);
    }

    public function test_cuentacorriente_autogenera_cuota_unica_si_falta_plan(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorCuentacorrienteService::class))->getFileName()
        );

        $this->assertStringContainsString('Sin OC o sin plan usable: una cuota al total', $src);
        $this->assertStringContainsString('autogenerarCuotasSiFaltan', $src);
    }
}
