<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use Tests\TestCase;

class CierreJornadaProcesoClasificacionSupportTest extends TestCase
{
    public function test_cuadro_parte_de_anita_jornada_y_suma_waitry_pendiente(): void
    {
        $anitaJornada = [
            'qr' => 200.0,
            'mp' => 0.0,
            'efectivo' => 0.0,
            'otros' => 0.0,
            'total' => 200.0,
            'etiqueta' => 'Facturado Anita (jornada)',
            'tipo' => 'anita_jornada',
        ];

        $movimientos = [
            [
                'waitry_order_id' => 1,
                'total' => 100.0,
                'waitry_tipo_pago' => 'totalcoin',
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 2,
                'total' => 50.0,
                'waitry_tipo_pago' => 'cash',
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 3,
                'total' => 200.0,
                'waitry_tipo_pago' => 'mercadopago',
                'paid_waitry' => true,
                'facturada_erp' => true,
                'anita_es_totem' => false,
                'anita_cuentacaja_id' => 0,
            ],
        ];

        $resultado = CierreJornadaProcesoClasificacionSupport::clasificar($movimientos, 1, $anitaJornada);

        $this->assertSame(100.0, $resultado['grilla']['qr_sin_facturar']);
        $this->assertSame(100.0, $resultado['grilla']['cobrado_waitry_sin_facturar']);
        $this->assertSame(50.0, $resultado['grilla']['efectivo_waitry']);
        $this->assertSame(200.0, $resultado['total_facturacion']);
        $this->assertSame(100.0, $resultado['total_pendiente_facturar']);
        $this->assertCount(4, $resultado['cuadro_filas']);
        $this->assertSame('anita_jornada', $resultado['cuadro_filas'][0]['tipo']);
        $this->assertSame(200.0, $resultado['cuadro_filas'][0]['total']);
    }
}
