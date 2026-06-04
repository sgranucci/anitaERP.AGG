<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class CierreJornadaFacturadoAnitaSupportTest extends TestCase
{
    public function test_neto_descuenta_nota_credito_una_sola_vez(): void
    {
        $factura = new Venta(['total' => 1000.0]);
        $nc = new Venta(['total' => -200.0]);

        $emFactura = new VentaGastronomiaEmision([
            'venta_id' => 1,
            'venta_factura_origen_id' => null,
        ]);
        $emFactura->setRelation('venta', $factura);
        $emFactura->setRelation('cuenta', null);

        $emNc = new VentaGastronomiaEmision([
            'venta_id' => 2,
            'venta_factura_origen_id' => 1,
        ]);
        $emNc->setRelation('venta', $nc);
        $emNc->setRelation('cuenta', null);

        $totales = CierreJornadaFacturadoAnitaSupport::totalesDesdeEmisiones(
            new Collection([$emFactura, $emNc]),
            1,
        );

        $this->assertSame(800.0, $totales['total']);
        $this->assertSame(1000.0, $totales['total_facturas']);
        $this->assertSame(-200.0, $totales['total_notas_credito']);
        $this->assertSame(1, $totales['cantidad_facturas']);
        $this->assertSame(1, $totales['cantidad_notas_credito']);
        $this->assertSame(1000.0, $totales['anita_jornada']['otros']);
        $this->assertSame(1000.0, $totales['anita_jornada']['total']);
    }

    public function test_grilla_preserva_total_neto_con_nc(): void
    {
        $anita = [
            'qr' => 1000.0,
            'mp' => 0.0,
            'efectivo' => 0.0,
            'otros' => 0.0,
            'total' => 800.0,
            'etiqueta' => 'Facturado Anita (jornada)',
            'tipo' => 'anita_jornada',
        ];

        $totalesAnita = [
            'anita_jornada' => $anita,
            'anita_totem' => [
                'qr' => 0.0,
                'mp' => 0.0,
                'efectivo' => 0.0,
                'otros' => 0.0,
                'total' => 0.0,
                'etiqueta' => 'Facturado Anita — cobro TOTEM (medio real Waitry)',
                'tipo' => 'anita_totem',
            ],
            'total' => 800.0,
        ];

        $cuadro = CierreJornadaProcesoGrillaSupport::armar([], $totalesAnita);

        $this->assertSame(800.0, $cuadro['total_facturacion']);
        $this->assertSame(1000.0, $cuadro['filas'][0]['total']);
        $this->assertSame(1000.0, $cuadro['filas'][0]['qr']);
    }
}
