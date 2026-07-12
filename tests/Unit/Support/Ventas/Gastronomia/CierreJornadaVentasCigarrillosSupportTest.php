<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Support\Ventas\Gastronomia\CierreJornadaVentasCigarrillosSupport;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Tests\TestCase;

class CierreJornadaVentasCigarrillosSupportTest extends TestCase
{
    public function test_nota_credito_cigarrillos_firma_impuesto_interno_negativo(): void
    {
        $venta = new Venta(['total' => -5771.45]);
        $venta->setRelation('tipotransacciones', (object) ['signo' => GastronomiaVentaComprobanteSignoSupport::SIGNO_RESTA]);
        $venta->setRelation('venta_impuestos', collect([
            (object) ['concepto' => 'Impuesto Interno', 'importe' => 4457.27],
        ]));

        $importeCig = -6100.0;
        $ii = CierreJornadaVentasCigarrillosSupport::resolverImpuestoInternoVenta($venta, 1, $importeCig);

        $this->assertSame(-4457.27, $ii);

        $desglose = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(
            -5771.45,
            $ii,
            $importeCig,
        );
        $haber = round(
            $desglose['ventas_gravadas']
            + $desglose['ventas_kiosco']
            + $desglose['iva_normal']
            + $desglose['iva_cigarrillos'],
            2,
        );

        $this->assertSame(-5771.45, $haber);
    }

    public function test_factura_cigarrillos_mantiene_impuesto_interno_positivo(): void
    {
        $venta = new Venta(['total' => 5771.45]);
        $venta->setRelation('tipotransacciones', (object) ['signo' => GastronomiaVentaComprobanteSignoSupport::SIGNO_SUMA]);
        $venta->setRelation('venta_impuestos', collect([
            (object) ['concepto' => 'Impuesto Interno', 'importe' => 4457.27],
        ]));

        $importeCig = 6100.0;
        $ii = CierreJornadaVentasCigarrillosSupport::resolverImpuestoInternoVenta($venta, 1, $importeCig);

        $this->assertSame(4457.27, $ii);
    }
}
