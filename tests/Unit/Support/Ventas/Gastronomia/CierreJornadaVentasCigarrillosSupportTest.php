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

    public function test_exento_no_se_grava_con_iva(): void
    {
        // Total 1210 con 210 exento: IVA solo sobre la base gravable (1000), no sobre el exento.
        $desglose = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(
            1210.0,
            0.0,
            0.0,
            210.0,
        );

        $this->assertSame(173.55, $desglose['iva_normal']);
        $this->assertSame(1036.45, $desglose['ventas_gravadas']);
        $this->assertSame(0.0, $desglose['iva_cigarrillos']);

        $haber = round(
            $desglose['ventas_gravadas']
            + $desglose['ventas_kiosco']
            + $desglose['iva_normal']
            + $desglose['iva_cigarrillos'],
            2,
        );

        // El haber sigue cuadrando contra el total facturado.
        $this->assertSame(1210.0, $haber);
    }

    public function test_exento_reduce_iva_respecto_a_gravar_todo(): void
    {
        $conExento = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(1210.0, 0.0, 0.0, 210.0);
        $sinExento = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(1210.0, 0.0, 0.0, 0.0);

        // Gravando todo, el IVA sería 210; con 210 exento baja a 173.55.
        $this->assertSame(210.0, $sinExento['iva_normal']);
        $this->assertSame(173.55, $conExento['iva_normal']);
        $this->assertGreaterThan($conExento['iva_normal'], $sinExento['iva_normal']);
    }

    public function test_resolver_exento_venta_lee_cabecera_con_signo(): void
    {
        $venta = new Venta(['total' => 1210.0]);
        $venta->setRelation('tipotransacciones', (object) ['signo' => GastronomiaVentaComprobanteSignoSupport::SIGNO_SUMA]);
        $venta->setRelation('venta_impuestos', collect([
            (object) ['concepto' => 'Exento', 'importe' => 210.0],
        ]));

        $this->assertSame(210.0, CierreJornadaVentasCigarrillosSupport::resolverExentoVenta($venta));

        $notaCredito = new Venta(['total' => -1210.0]);
        $notaCredito->setRelation('tipotransacciones', (object) ['signo' => GastronomiaVentaComprobanteSignoSupport::SIGNO_RESTA]);
        $notaCredito->setRelation('venta_impuestos', collect([
            (object) ['concepto' => 'Exento', 'importe' => 210.0],
        ]));

        $this->assertSame(-210.0, CierreJornadaVentasCigarrillosSupport::resolverExentoVenta($notaCredito));
    }
}
