<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\RendicionMaquina\RendicionMaquinaValorQrPrecargaSupport;
use PHPUnit\Framework\TestCase;

class RendicionMaquinaValorQrPrecargaSupportTest extends TestCase
{
    public function test_monto_es_drop_qr_mas_impuesto_qr(): void
    {
        $this->assertSame(150.50, RendicionMaquinaValorQrPrecargaSupport::montoDesdeInputs([
            'dropqr_rodillo' => 100.25,
            'impuesto_qr' => 50.25,
        ]));
    }

    public function test_detecta_totalcoin_qr_maquinas_y_no_caja_ni_m0qr(): void
    {
        $this->assertTrue(RendicionMaquinaValorQrPrecargaSupport::esTotalCoinQrMaquinas([
            'nombre' => 'TotalCoin QR Maquina',
            'nombre_maestro' => 'TOTAL COIN MAQUINAS',
        ]));
        $this->assertFalse(RendicionMaquinaValorQrPrecargaSupport::esTotalCoinQrMaquinas([
            'nombre' => 'TotalCoin QR Caja',
            'nombre_maestro' => 'TOTAL COIN CAJA',
        ]));
        $this->assertFalse(RendicionMaquinaValorQrPrecargaSupport::esTotalCoinQrMaquinas([
            'nombre' => 'QR',
            'nombre_maestro' => 'QR Maquinas',
            'codigo' => 'M0QR',
        ]));
    }

    public function test_lineas_precarga_solo_la_cuenta_totalcoin_maquinas(): void
    {
        $lineas = RendicionMaquinaValorQrPrecargaSupport::lineasPrecarga(
            ['dropqr_rodillo' => 80, 'impuesto_qr' => 20],
            [
                ['cuentacaja_id' => 225, 'nombre' => 'TotalCoin QR Maquina', 'nombre_maestro' => 'TOTAL COIN MAQUINAS'],
                ['cuentacaja_id' => 226, 'nombre' => 'TotalCoin QR Caja', 'nombre_maestro' => 'TOTAL COIN CAJA'],
                ['cuentacaja_id' => 203, 'nombre' => 'QR', 'nombre_maestro' => 'QR Maquinas'],
            ]
        );

        $this->assertCount(1, $lineas);
        $this->assertSame(225, $lineas[0]['cuentacaja_id']);
        $this->assertSame(100.0, $lineas[0]['monto']);
    }
}
