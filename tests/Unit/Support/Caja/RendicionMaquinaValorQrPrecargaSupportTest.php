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

    public function test_drop_qr_es_totalcoin_menos_impuesto(): void
    {
        $this->assertSame(113372902.35, RendicionMaquinaValorQrPrecargaSupport::dropQrDesdeTotalCoin(
            114449945.00,
            1077042.65
        ));
    }

    public function test_maniana_alinea_drop_qr_al_neto_de_la_planilla(): void
    {
        $inputs = RendicionMaquinaValorQrPrecargaSupport::alinearDropQrConTotalCoinManiana(
            'M',
            ['dropqr_rodillo' => 113322382.29, 'impuesto_qr' => 1077042.65],
            [[
                'cuentacaja_id' => 225,
                'monto' => 114449945.00,
                'nombre' => 'TotalCoin QR Maquina',
            ]]
        );

        $this->assertSame(113372902.35, $inputs['dropqr_rodillo']);
    }

    public function test_completo_no_alinea_drop_qr(): void
    {
        $inputs = RendicionMaquinaValorQrPrecargaSupport::alinearDropQrConTotalCoinManiana(
            'C',
            ['dropqr_rodillo' => 113322382.29, 'impuesto_qr' => 1077042.65],
            [[
                'cuentacaja_id' => 225,
                'monto' => 114449945.00,
                'nombre' => 'TotalCoin QR Maquina',
            ]]
        );

        $this->assertSame(113322382.29, $inputs['dropqr_rodillo']);
    }

    public function test_no_borra_drop_si_totalcoin_esta_vacio(): void
    {
        $inputs = RendicionMaquinaValorQrPrecargaSupport::alinearDropQrConTotalCoinManiana(
            'M',
            ['dropqr_rodillo' => 113322382.29, 'impuesto_qr' => 1077042.65],
            [[
                'cuentacaja_id' => 225,
                'monto' => 0,
                'nombre' => 'TotalCoin QR Maquina',
            ]]
        );

        $this->assertSame(113322382.29, $inputs['dropqr_rodillo']);
    }

    public function test_completo_no_precarga_totalcoin_desde_drop_mas_impuesto(): void
    {
        // Completo no debe usar drop+impuesto: mezcla drop WIGOS del día con
        // impuesto del consolidado (otra jornada). El TotalCoin viene de M/T/N.
        $desdeInputs = RendicionMaquinaValorQrPrecargaSupport::montoDesdeInputs([
            'dropqr_rodillo' => 91307433.32,
            'impuesto_qr' => 1197710.57,
        ]);
        $this->assertSame(92505143.89, $desdeInputs);

        // Ese monto (mezcla) NO es el depósito correcto del Completo:
        // el de la Mañana del mismo día es 127272498.31.
        $this->assertNotEquals(127272498.31, $desdeInputs);
    }

    public function test_lineas_precarga_acepta_monto_override(): void
    {
        $lineas = RendicionMaquinaValorQrPrecargaSupport::lineasPrecarga(
            ['dropqr_rodillo' => 80, 'impuesto_qr' => 20],
            [
                ['cuentacaja_id' => 225, 'nombre' => 'TotalCoin QR Maquina', 'nombre_maestro' => 'TOTAL COIN MAQUINAS'],
            ],
            150606588.07
        );

        $this->assertCount(1, $lineas);
        $this->assertSame(150606588.07, $lineas[0]['monto']);
    }
}
