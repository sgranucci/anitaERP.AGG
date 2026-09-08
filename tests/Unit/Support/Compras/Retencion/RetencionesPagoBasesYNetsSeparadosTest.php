<?php

namespace Tests\Unit\Support\Compras\Retencion;

use App\Support\Compras\Retencion\RetencionesPagoBasesResultado;
use App\Support\Compras\Retencion\RetencionesPagoInput;
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
use PHPUnit\Framework\TestCase;

class RetencionesPagoBasesYNetsSeparadosTest extends TestCase
{
    public function test_bases_resultado_neto_documental(): void
    {
        $b = new RetencionesPagoBasesResultado(
            netoGanancias: 16462.78,
            netoIibb: 16462.78,
            netoGravado: 16462.78,
            netoExento: 100.0,
            netoNogravado: 50.0,
            importeIva: 3457.18,
            brutoAplicado: 19919.96,
            origen: 'conceptos',
        );

        $this->assertEqualsWithDelta(16612.78, $b->netoDocumental(), 0.01);
        $this->assertSame('conceptos', $b->toArray()['origen']);
    }

    public function test_orquestador_usa_bases_especificas_por_impuesto(): void
    {
        $proveedor = new Proveedor;
        $proveedor->id = 1;

        $ganancias = $this->createMock(RetencionGananciasCalculator::class);
        $iva = $this->createMock(RetencionIvaCalculator::class);
        $suss = $this->createMock(RetencionSussCalculator::class);
        $iibb = $this->createMock(RetencionIibbCalculator::class);

        $ganancias->expects($this->once())
            ->method('calcularParaProveedor')
            ->with(
                $proveedor,
                25023425.60, // neto ganancias
                0.0,
                0.0,
                null,
                null,
                null,
                null,
                null,
            )
            ->willReturn(new RetencionGananciasResultado(true, 495988.51, 25023425.60, 24799425.60, 2.0, RetencionGananciasResultado::MOTIVO_OK));

        $iva->expects($this->once())
            ->method('calcularParaProveedor')
            ->with(
                $proveedor,
                25023425.60, // neto gravado (importeNetoPago)
                5254913.60,  // iva
                0.0,
                0.0,
                0.0,
                null,
                null,
                null,
                false,
                null,
            )
            ->willReturn(RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_NO_RETIENE));

        $suss->expects($this->once())
            ->method('calcularParaProveedor')
            ->with(
                $proveedor,
                25023425.60, // neto suss
                0.0,
                0.0,
                null,
                null,
                null,
                null,
                null,
            )
            ->willReturn(RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_NO_RETIENE));

        $iibb->expects($this->once())
            ->method('calcularParaProveedor')
            ->with(
                $proveedor,
                25023425.60, // neto iibb
                '2026-09-03',
                null,
                null,
                null,
                null,
                1,
            )
            ->willReturn(new RetencionIibbResultado(true, 625585.64, 25023425.60, 2.5, RetencionIibbResultado::MOTIVO_OK));

        $calc = new RetencionesPagoCalculator($ganancias, $iva, $suss, $iibb);
        $out = $calc->calcular(new RetencionesPagoInput(
            proveedor: $proveedor,
            importeNetoPago: 25023425.60,
            importeIvaPago: 5254913.60,
            fecha: '2026-09-03',
            empresaId: 1,
            importeNetoGanancias: 25023425.60,
            importeNetoIibb: 25023425.60,
            importeNetoSuss: 25023425.60,
        ));

        $this->assertEqualsWithDelta(495988.51 + 625585.64, $out->totalRetenciones(), 0.01);
    }

    public function test_input_helpers_caen_a_neto_general(): void
    {
        $input = new RetencionesPagoInput(new Proveedor, 1000.0, 210.0);
        $this->assertSame(1000.0, $input->netoGanancias());
        $this->assertSame(1000.0, $input->netoIibb());
        $this->assertSame(1000.0, $input->netoSuss());
    }
}
