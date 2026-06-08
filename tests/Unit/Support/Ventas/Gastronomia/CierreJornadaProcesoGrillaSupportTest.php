<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport;
use Tests\TestCase;

class CierreJornadaProcesoGrillaSupportTest extends TestCase
{
    public function test_armar_cuadro_desde_anita_jornada_y_movimientos_waitry(): void
    {
        $anitaJornada = [
            'qr' => 300.0,
            'mp' => 150.0,
            'efectivo' => 50.0,
            'otros' => 0.0,
            'total' => 500.0,
            'etiqueta' => 'Facturado Anita (jornada)',
            'tipo' => 'anita_jornada',
        ];
        $totalesAnita = [
            'anita_jornada' => $anitaJornada,
            'anita_totem' => [
                'qr' => 80.0,
                'mp' => 0.0,
                'efectivo' => 0.0,
                'otros' => 0.0,
                'total' => 80.0,
                'etiqueta' => 'Facturado Anita — cobro TOTEM (medio real Waitry)',
                'tipo' => 'anita_totem',
            ],
            'total' => 500.0,
        ];

        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 100.0,
                'waitry_tipo_pago' => 'totalcoin',
                'paid_waitry' => true,
                'facturada_erp' => false,
                'medio_waitry_clave' => 'qr',
            ],
            [
                'waitry_order_id' => 2,
                'total' => 40.0,
                'waitry_tipo_pago' => 'cash',
                'paid_waitry' => true,
                'facturada_erp' => false,
                'medio_waitry_clave' => 'efectivo',
            ],
            [
                'waitry_order_id' => 3,
                'total' => 80.0,
                'waitry_tipo_pago' => 'mercadopago',
                'paid_waitry' => false,
                'facturada_erp' => false,
                'medio_waitry_clave' => 'mp',
            ],
        ];

        $resultado = CierreJornadaProcesoGrillaSupport::armar($movimientos, $totalesAnita);

        $this->assertCount(5, $resultado['filas']);
        $this->assertSame(500.0, $resultado['total_facturacion']);
        $this->assertSame(100.0, $resultado['total_pendiente_facturar']);
        $this->assertSame(80.0, $resultado['total_impago_waitry']);
        $this->assertSame(680.0, $resultado['total_cuadro']);
        $this->assertSame(100.0, $resultado['grilla']['qr_sin_facturar']);
        $this->assertSame(40.0, $resultado['grilla']['efectivo_waitry']);
        $this->assertSame('waitry_pago', $resultado['filas'][2]['tipo']);
        $this->assertSame('waitry_cash', $resultado['filas'][4]['tipo']);
        $this->assertSame('anita_totem', $resultado['filas'][1]['tipo']);
    }

    public function test_anita_jornada_no_aplica_compensacion_z_en_fila_cobranzas_erp(): void
    {
        $totalesAnita = [
            'anita_jornada' => [
                'qr' => 100.0,
                'mp' => 0.0,
                'efectivo' => 900.0,
                'otros' => 0.0,
                'diferencia_caja' => 0.0,
                'total' => 1000.0,
                'etiqueta' => 'Facturado Anita (jornada — cobranzas ERP, cuadra con asiento 2)',
                'tipo' => 'anita_jornada',
            ],
            'anita_totem' => CierreJornadaProcesoGrillaSupport::filaVacia('Facturado Anita — cobro TOTEM (medio real Waitry)', 'anita_totem'),
            'total' => 1000.0,
        ];

        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 500.0,
                'paid_waitry' => true,
                'facturada_erp' => true,
                'grupo' => 'facturado_medio_real',
                'medio_anita_clave' => 'efectivo',
                'medios_pago_planificados' => [
                    ['clave' => 'qr', 'monto' => 500.0],
                ],
            ],
        ];

        $resultado = CierreJornadaProcesoGrillaSupport::armar($movimientos, $totalesAnita);

        $this->assertSame(100.0, $resultado['filas'][0]['qr']);
        $this->assertSame(900.0, $resultado['filas'][0]['efectivo']);
    }

    public function test_armar_excluye_anuladas_descuento_de_impago(): void
    {
        $totalesAnita = [
            'anita_jornada' => [
                'qr' => 0.0,
                'mp' => 0.0,
                'efectivo' => 0.0,
                'otros' => 0.0,
                'total' => 0.0,
                'etiqueta' => 'Facturado Anita (jornada)',
                'tipo' => 'anita_jornada',
            ],
            'anita_totem' => CierreJornadaProcesoGrillaSupport::filaVacia('Facturado Anita — cobro TOTEM (medio real Waitry)', 'anita_totem'),
            'total' => 0.0,
        ];

        $movimientos = [
            [
                'waitry_order_id' => 17573854,
                'total' => 7800.0,
                'waitry_tipo_pago' => null,
                'paid_waitry' => false,
                'facturada_erp' => false,
                'waitry_anulada_descuento' => true,
                'total_neto_waitry' => 0.0,
                'medio_waitry_clave' => 'otros',
            ],
            [
                'waitry_order_id' => 99,
                'total' => 50.0,
                'waitry_tipo_pago' => 'mercadopago',
                'paid_waitry' => false,
                'facturada_erp' => false,
                'medio_waitry_clave' => 'mp',
            ],
        ];

        $resultado = CierreJornadaProcesoGrillaSupport::armar($movimientos, $totalesAnita);

        $this->assertSame(50.0, $resultado['total_impago_waitry']);
        $this->assertSame(0.0, $resultado['grilla']['waitry_impago_otros']);
        $this->assertSame(50.0, $resultado['grilla']['waitry_impago_mp']);
    }
}
