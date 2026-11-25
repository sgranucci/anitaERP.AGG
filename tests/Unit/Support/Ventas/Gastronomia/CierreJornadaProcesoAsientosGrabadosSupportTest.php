<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabadosSupport;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class CierreJornadaProcesoAsientosGrabadosSupportTest extends TestCase
{
    public function test_lineas_desde_movimientos_separa_debe_y_haber(): void
    {
        $caja = new Cuentacontable();
        $caja->codigo = '111010001';
        $caja->nombre = 'CAJA PESOS';

        $mp = new Cuentacontable();
        $mp->codigo = '111050025';
        $mp->nombre = 'CTA CTE MERCADO PAGO';

        $movCaja = new Asiento_Movimiento();
        $movCaja->cuentacontable_id = 1;
        $movCaja->monto = 16799;
        $movCaja->observacion = 'Venta gastronomia';
        $movCaja->setRelation('cuentacontables', $caja);

        $movMp = new Asiento_Movimiento();
        $movMp->cuentacontable_id = 2;
        $movMp->monto = 1;
        $movMp->observacion = 'Venta gastronomia';
        $movMp->setRelation('cuentacontables', $mp);

        $movHaber = new Asiento_Movimiento();
        $movHaber->cuentacontable_id = 3;
        $movHaber->monto = -16800;
        $movHaber->observacion = 'Venta gastronomia';
        $ventas = new Cuentacontable();
        $ventas->codigo = '413010001';
        $ventas->nombre = 'VENTAS';
        $movHaber->setRelation('cuentacontables', $ventas);

        $lineas = CierreJornadaProcesoAsientosGrabadosSupport::lineasDesdeMovimientos([
            $movCaja,
            $movMp,
            $movHaber,
        ]);

        $this->assertCount(3, $lineas);
        $this->assertSame(16799.0, $lineas[0]['debe']);
        $this->assertSame(0.0, $lineas[0]['haber']);
        $this->assertSame('111010001', $lineas[0]['cuenta_codigo']);
        $this->assertSame(1.0, $lineas[1]['debe']);
        $this->assertSame(16800.0, $lineas[2]['haber']);
        $this->assertSame(0.0, $lineas[2]['debe']);
    }

    public function test_asiento_desde_modelo_incluye_numero_de_asiento_en_titulo(): void
    {
        $cuenta = new Cuentacontable();
        $cuenta->codigo = '111010001';
        $cuenta->nombre = 'CAJA PESOS';

        $mov = new Asiento_Movimiento();
        $mov->cuentacontable_id = 5;
        $mov->monto = 1026000;
        $mov->observacion = 'Reducion de Fondo fijo';
        $mov->setRelation('cuentacontables', $cuenta);

        $movH = new Asiento_Movimiento();
        $movH->cuentacontable_id = 15;
        $movH->monto = -1026000;
        $movH->observacion = 'Reducion de Fondo fijo';
        $ff = new Cuentacontable();
        $ff->codigo = '111020005';
        $ff->nombre = 'FONDO FIJO';
        $movH->setRelation('cuentacontables', $ff);

        $asiento = new Asiento();
        $asiento->id = 178049;
        $asiento->numeroasiento = '370701';
        $asiento->setRelation('asiento_movimientos', new Collection([$mov, $movH]));

        $out = CierreJornadaProcesoAsientosGrabadosSupport::asientoDesdeModelo(
            $asiento,
            [
                'codigo' => 'compensacion_efectivo_no_facturado',
                'titulo' => '3 — Reducion de Fondo fijo',
                'numeroasiento' => '370701',
                'resumen_debe' => 1026000,
                'resumen_haber' => 1026000,
            ],
            3,
        );

        $this->assertSame(3, $out['numero']);
        $this->assertSame('370701', $out['numeroasiento']);
        $this->assertSame(178049, $out['asiento_id']);
        $this->assertTrue($out['desde_grabacion']);
        $this->assertStringContainsString('N° 370701', $out['titulo']);
        $this->assertStringStartsWith('3 —', $out['titulo']);
        $this->assertSame(1026000.0, $out['resumen_debe']);
        $this->assertSame(1026000.0, $out['resumen_haber']);
        $this->assertCount(2, $out['lineas']);
    }

    public function test_cuadre_desde_grabados_solo_valida_debe_haber(): void
    {
        $cuadre = CierreJornadaProcesoAsientosGrabadosSupport::armarCuadreDesdeGrabados([
            [
                'numero' => 1,
                'codigo' => 'sin_facturar_qr',
                'titulo' => '1 — Waitry sin facturar',
                'numeroasiento' => '370699',
                'resumen_debe' => 16800,
                'resumen_haber' => 16800,
                'total' => 16800,
            ],
            [
                'numero' => 2,
                'codigo' => 'ventas_medio_real',
                'titulo' => '2 — Facturación Anita',
                'numeroasiento' => '370700',
                'resumen_debe' => 100,
                'resumen_haber' => 90,
                'total' => 100,
            ],
        ]);

        $this->assertTrue($cuadre['desde_grabacion']);
        $this->assertFalse($cuadre['cuadre_global_ok']);
        $this->assertCount(2, $cuadre['filas']);
        $this->assertTrue($cuadre['filas'][0]['debe_haber_cuadra']);
        $this->assertFalse($cuadre['filas'][1]['debe_haber_cuadra']);
        $this->assertSame([], $cuadre['validaciones']);
        $this->assertStringContainsString('370699', $cuadre['filas'][0]['referencia_etiqueta']);
    }
}
