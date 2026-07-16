<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;
use App\Services\Compras\RetencionGananciasCalculator;
use App\Services\Compras\RetencionIibbCalculator;
use App\Services\Compras\RetencionIvaCalculator;
use App\Services\Compras\RetencionSussCalculator;
use App\Services\Compras\RetencionesPagoCalculator;
use App\Support\Compras\Retencion\RetencionGananciasResultado;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use App\Support\Compras\Retencion\RetencionIvaResultado;
use App\Support\Compras\Retencion\RetencionSussResultado;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use App\Support\Compras\Retencion\RetencionesPagoResultado;
use PHPUnit\Framework\TestCase;

/**
 * Orquestador + totales — sin BD (calculators mockeados).
 */
class RetencionesPagoCalculatorTest extends TestCase
{
    public function test_total_y_neto_a_transferir(): void
    {
        $resultado = new RetencionesPagoResultado(
            new RetencionGananciasResultado(true, 656.60, 100000, 32830, 2.0, RetencionGananciasResultado::MOTIVO_OK),
            new RetencionIvaResultado(true, 1050.0, 2100, 50.0, RetencionIvaResultado::MOTIVO_OK),
            new RetencionSussResultado(true, 1000.0, 100000, 1.0, RetencionSussResultado::MOTIVO_OK),
            new RetencionIibbResultado(true, 3000.0, 100000, 3.0, RetencionIibbResultado::MOTIVO_OK),
        );

        $this->assertEqualsWithDelta(5706.60, $resultado->totalRetenciones(), 0.01);
        // (100000 + 2100) − 5706.60 = 96393.40
        $this->assertEqualsWithDelta(96393.40, $resultado->netoATransferir(100000.0, 2100.0), 0.01);
        $this->assertCount(4, $resultado->lineas());
    }

    public function test_lineas_sin_aplicar_van_en_cero(): void
    {
        $resultado = new RetencionesPagoResultado(
            RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_NO_RETIENE),
            new RetencionIvaResultado(true, 500.0, 1000, 50.0, RetencionIvaResultado::MOTIVO_OK),
            RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_NO_RETIENE),
            RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE),
        );

        $this->assertEqualsWithDelta(500.0, $resultado->totalRetenciones(), 0.01);
        $this->assertFalse($resultado->lineas()[0]['aplica']);
        $this->assertSame(0.0, $resultado->lineas()[0]['importe']);
        $this->assertTrue($resultado->lineas()[1]['aplica']);
    }

    public function test_orquestador_delega_a_los_cuatro_calculators(): void
    {
        $proveedor = new Proveedor;
        $proveedor->id = 1;

        $ganancias = $this->createMock(RetencionGananciasCalculator::class);
        $iva = $this->createMock(RetencionIvaCalculator::class);
        $suss = $this->createMock(RetencionSussCalculator::class);
        $iibb = $this->createMock(RetencionIibbCalculator::class);

        $ganancias->expects($this->once())
            ->method('calcularParaProveedor')
            ->willReturn(new RetencionGananciasResultado(true, 100.0, 10000, 10000, 2.0, RetencionGananciasResultado::MOTIVO_OK));

        $iva->expects($this->once())
            ->method('calcularParaProveedor')
            ->willReturn(new RetencionIvaResultado(true, 200.0, 2100, 50.0, RetencionIvaResultado::MOTIVO_OK));

        $suss->expects($this->once())
            ->method('calcularParaProveedor')
            ->willReturn(new RetencionSussResultado(true, 50.0, 10000, 1.0, RetencionSussResultado::MOTIVO_OK));

        $iibb->expects($this->once())
            ->method('calcularParaProveedor')
            ->willReturn(new RetencionIibbResultado(true, 300.0, 10000, 3.0, RetencionIibbResultado::MOTIVO_OK));

        $orquestador = new RetencionesPagoCalculator($ganancias, $iva, $suss, $iibb);
        $out = $orquestador->calcular(new RetencionesPagoInput(
            $proveedor,
            10000.0,
            2100.0,
            '2026-07-15',
        ));

        $this->assertEqualsWithDelta(650.0, $out->totalRetenciones(), 0.01);
        $this->assertTrue($out->ganancias->aplica);
        $this->assertTrue($out->iva->aplica);
        $this->assertTrue($out->suss->aplica);
        $this->assertTrue($out->iibb->aplica);
    }

    public function test_flags_omitir_no_llaman_calculator(): void
    {
        $proveedor = new Proveedor;

        $ganancias = $this->createMock(RetencionGananciasCalculator::class);
        $iva = $this->createMock(RetencionIvaCalculator::class);
        $suss = $this->createMock(RetencionSussCalculator::class);
        $iibb = $this->createMock(RetencionIibbCalculator::class);

        $ganancias->expects($this->never())->method('calcularParaProveedor');
        $iva->expects($this->once())
            ->method('calcularParaProveedor')
            ->willReturn(new RetencionIvaResultado(true, 10.0, 100, 50.0, RetencionIvaResultado::MOTIVO_OK));
        $suss->expects($this->never())->method('calcularParaProveedor');
        $iibb->expects($this->never())->method('calcularParaProveedor');

        $orquestador = new RetencionesPagoCalculator($ganancias, $iva, $suss, $iibb);
        $out = $orquestador->calcular(new RetencionesPagoInput(
            proveedor: $proveedor,
            importeNetoPago: 1000.0,
            importeIvaPago: 210.0,
            calcularGanancias: false,
            calcularIva: true,
            calcularSuss: false,
            calcularIibb: false,
        ));

        $this->assertEqualsWithDelta(10.0, $out->totalRetenciones(), 0.01);
        $this->assertFalse($out->ganancias->aplica);
        $this->assertTrue(($out->ganancias->detalle['omitido'] ?? false) === true);
        $this->assertTrue($out->iva->aplica);
    }
}
