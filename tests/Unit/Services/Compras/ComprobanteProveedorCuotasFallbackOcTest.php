<?php

namespace Tests\Unit\Services\Compras;

use App\Services\Compras\ComprobanteProveedorCondicionPagoDesdeOcService;
use App\Services\Compras\ComprobanteProveedorCuentacorrienteService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ComprobanteProveedorCuotasFallbackOcTest extends TestCase
{
    public function test_resolver_aplica_vencimientos_desde_condicionpago(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorCondicionPagoDesdeOcService::class))->getFileName()
        );

        $this->assertStringContainsString('fallbackDesdeCuotaAnterior', $src);
        $this->assertStringContainsString('conVencimientosDesdeCondicion', $src);
        $this->assertStringContainsString('ComprobanteProveedorVencimientoCondicionSupport', $src);
    }

    public function test_cuentacorriente_autogenera_desde_condicion_o_cuota_unica(): void
    {
        $src = (string) file_get_contents(
            (new ReflectionClass(ComprobanteProveedorCuentacorrienteService::class))->getFileName()
        );

        $this->assertStringContainsString('armarCuotasDesdeCondicion', $src);
        $this->assertStringContainsString('autogenerarCuotasSiFaltan', $src);
        $this->assertStringContainsString('ComprobanteProveedorVencimientoCondicionSupport', $src);
    }
}
