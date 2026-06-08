<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\GastronomiaVentaWaitryComandasSupport;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class CierreJornadaFacturadoAnitaSupportTest extends TestCase
{
    public function test_neto_descuenta_nota_credito_una_sola_vez(): void
    {
        $factura = new Venta(['total' => 1000.0]);
        $factura->setAttribute('id', 9_999_999_992);
        $factura->setRelation('cobranzasDirectas', collect());
        $factura->setRelation('caja_movimientos', collect());

        $nc = new Venta(['total' => -200.0]);
        $nc->setAttribute('id', 9_999_999_993);
        $nc->setRelation('cobranzasDirectas', collect());
        $nc->setRelation('caja_movimientos', collect());

        $emFactura = new VentaGastronomiaEmision([
            'venta_id' => 9_999_999_992,
            'venta_factura_origen_id' => null,
        ]);
        $emFactura->setRelation('venta', $factura);
        $emFactura->setRelation('cuenta', null);

        $emNc = new VentaGastronomiaEmision([
            'venta_id' => 9_999_999_993,
            'venta_factura_origen_id' => 9_999_999_992,
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
        $this->assertSame(800.0, $totales['anita_jornada']['total']);
        $this->assertSame(800.0, $totales['anita_jornada']['otros']);
    }

    public function test_datos_asiento_separa_iva_cigarrillos_y_usa_cobranzas(): void
    {
        $ventaGravada = new Venta(['id' => 10, 'total' => 121.0]);
        $ventaGravada->setRelation('venta_impuestos', collect());
        $ventaGravada->setRelation('cobranzasDirectas', collect());
        $ventaGravada->setRelation('caja_movimientos', collect());

        $ventaKiosco = new Venta(['id' => 11, 'total' => 131.0]);
        $ventaKiosco->setRelation('venta_impuestos', collect([(object) ['concepto' => 'Impuesto interno', 'importe' => 10.0]]));
        $ventaKiosco->setRelation('cobranzasDirectas', collect());
        $ventaKiosco->setRelation('caja_movimientos', collect());

        $emGravada = new VentaGastronomiaEmision(['venta_id' => 10, 'venta_factura_origen_id' => null]);
        $emGravada->setRelation('venta', $ventaGravada);
        $emGravada->setRelation('cuenta', null);

        $emKiosco = new VentaGastronomiaEmision(['venta_id' => 11, 'venta_factura_origen_id' => null]);
        $emKiosco->setRelation('venta', $ventaKiosco);
        $emKiosco->setRelation('cuenta', null);

        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoDesdeEmisiones(
            new Collection([$emGravada, $emKiosco]),
            1,
        );

        $this->assertSame(252.0, $datos['total']);
        $this->assertSame(21.0, $datos['iva_normal']);
        $this->assertSame(21.0, $datos['iva_cigarrillos']);
        $this->assertSame(110.0, $datos['ventas_kiosco']);
        $this->assertNotEmpty($datos['advertencias']);
    }

    public function test_columna_cuadro_usa_misma_clave_que_asiento(): void
    {
        $this->assertSame('qr', CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio(CierreJornadaProcesoMedioSupport::CLAVE_QR));
        $this->assertSame('efectivo', CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio(CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO));
        $this->assertSame('otros', CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio(CierreJornadaProcesoMedioSupport::CLAVE_OTRO));
    }

    public function test_invitacion_cortesia_sin_cobranza_no_genera_advertencia(): void
    {
        $venta = new Venta([
            'total' => GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA,
        ]);
        $venta->setAttribute('id', 9_999_999_991);
        $venta->setRelation('venta_impuestos', collect());
        $venta->setRelation('cobranzasDirectas', collect());
        $venta->setRelation('caja_movimientos', collect());

        $emision = new VentaGastronomiaEmision(['venta_id' => 9_999_999_991, 'venta_factura_origen_id' => null]);
        $emision->setRelation('venta', $venta);
        $emision->setRelation('cuenta', null);

        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoDesdeEmisiones(
            new Collection([$emision]),
            1,
        );

        $this->assertSame([], $datos['advertencias']);
        $this->assertSame([], $datos['debe_por_cuenta']);
        $this->assertSame(0.01, $datos['debe_diferencia_caja']);
        $this->assertSame(1, $datos['cantidad_invitaciones']);
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

    public function test_excluye_facturas_proceso_cierre_jornada_del_cuadro_y_asiento2(): void
    {
        $ventaPos = new Venta(['total' => 1000.0]);
        $ventaPos->setAttribute('id', 9_999_999_980);
        $ventaPos->setRelation('cobranzasDirectas', collect());
        $ventaPos->setRelation('caja_movimientos', collect());
        $ventaPos->setRelation('venta_impuestos', collect());

        $ventaProceso = new Venta(['total' => 287900.0]);
        $ventaProceso->setAttribute('id', 9_999_999_981);
        $ventaProceso->setRelation('cobranzasDirectas', collect());
        $ventaProceso->setRelation('caja_movimientos', collect());
        $ventaProceso->setRelation('venta_impuestos', collect());

        $emPos = new VentaGastronomiaEmision([
            'venta_id' => 9_999_999_980,
            'venta_factura_origen_id' => null,
        ]);
        $emPos->setRelation('venta', $ventaPos);
        $emPos->setRelation('cuenta', null);

        $emProceso = new VentaGastronomiaEmision([
            'venta_id' => 9_999_999_981,
            'venta_factura_origen_id' => null,
            'identificador_pc' => GastronomiaVentaWaitryComandasSupport::IDENTIFICADOR_PC_CIERRE_JORNADA,
            'cierre_jornada_proceso_lote' => 1,
        ]);
        $emProceso->setRelation('venta', $ventaProceso);
        $emProceso->setRelation('cuenta', null);

        $totales = CierreJornadaFacturadoAnitaSupport::totalesDesdeEmisiones(
            new Collection([$emPos, $emProceso]),
            1,
        );

        $this->assertSame(1000.0, $totales['total']);
        $this->assertSame(1000.0, $totales['anita_jornada']['total']);

        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoDesdeEmisiones(
            new Collection([$emPos, $emProceso]),
            1,
        );

        $this->assertSame(1000.0, $datos['total']);
        $this->assertSame(1, $datos['cantidad_emisiones']);
    }
}
