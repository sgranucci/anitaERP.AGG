<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\MovimientoStockCambioDepositoSupport;
use PHPUnit\Framework\TestCase;

class MovimientoStockCambioDepositoSupportTest extends TestCase
{
    public function test_sin_movimientos_posteriores_deja_cambiar_el_deposito(): void
    {
        $this->assertNull(MovimientoStockCambioDepositoSupport::mensajeBloqueo(13, '64-A', []));
    }

    public function test_bloquea_si_el_lote_ya_tiene_reubicacion_o_consumo_en_el_deposito_del_alta(): void
    {
        $msg = MovimientoStockCambioDepositoSupport::mensajeBloqueo(13, '64-A', [[
            'lote' => '503388',
            'deposito_id' => 1,
            'deposito_codigo' => '1',
            'conceptos' => ['Reubica depósito Excel Boa Onda (salida)', 'Consumo de OT'],
        ]]);

        $this->assertNotNull($msg);
        $this->assertStringContainsString('503388', $msg);
        $this->assertStringContainsString('64-A', $msg);
        $this->assertStringContainsString('duplica los pares', $msg);
        $this->assertStringContainsString('Reubica depósito Excel Boa Onda (salida)', $msg);
    }
}
