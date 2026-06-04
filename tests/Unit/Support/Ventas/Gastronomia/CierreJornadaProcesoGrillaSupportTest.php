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
}
