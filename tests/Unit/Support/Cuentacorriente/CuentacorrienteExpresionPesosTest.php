<?php

namespace Tests\Unit\Support\Cuentacorriente;

use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use Tests\TestCase;

class CuentacorrienteExpresionPesosTest extends TestCase
{
    public function test_equivalente_en_pesos_usa_tc_de_cada_comprobante_y_no_suma_cruda(): void
    {
        $movimientos = [
            (object) ['moneda_id' => 1, 'total' => 400000.0, 'cotizacion' => 1],
            (object) ['moneda_id' => 2, 'total' => 1000.0, 'cotizacion' => 1200],
        ];

        $equivalente = CuentacorrienteSaldosPorMoneda::equivalenteDesdeFilas($movimientos);

        $this->assertEqualsWithDelta(1600000.0, $equivalente['saldo_cc'], 0.01);
        $this->assertNotEquals(401000.0, $equivalente['saldo_cc']);
        $this->assertSame(CuentacorrienteSaldosPorMoneda::abreviaturaLocal(), $equivalente['abreviatura']);
    }

    public function test_equivalente_deuda_convierte_el_residual_al_tc_del_comprobante(): void
    {
        $deudas = [
            (object) ['moneda_id' => 1, 'total' => 400000.0, 'aplicado' => 0.0, 'cotizacion' => 1],
            (object) ['moneda_id' => 2, 'total' => 1000.0, 'aplicado' => 0.0, 'cotizacion' => 1200],
        ];

        $equivalente = CuentacorrienteSaldosPorMoneda::equivalenteDesdeFilas([], $deudas);

        $this->assertEqualsWithDelta(1600000.0, $equivalente['deuda'], 0.01);
        $this->assertEqualsWithDelta(0.0, $equivalente['saldo_cc'], 0.01);
    }

    public function test_saldo_anterior_en_pesos_no_mezcla_sin_convertir(): void
    {
        $movimientos = [
            (object) ['id' => 1, 'fecha' => '2026-01-01', 'moneda_id' => 1, 'total' => 400000.0, 'cotizacion' => 1],
            (object) ['id' => 2, 'fecha' => '2026-01-02', 'moneda_id' => 2, 'total' => 1000.0, 'cotizacion' => 1200],
            (object) ['id' => 3, 'fecha' => '2026-01-03', 'moneda_id' => 2, 'total' => 250.0, 'cotizacion' => 1300],
        ];
        $primerRegistro = (object) ['id' => 3, 'fecha' => '2026-01-03'];

        $this->assertEqualsWithDelta(
            1600000.0,
            CuentacorrienteSaldosPorMoneda::saldoAnteriorEnPesosDe($movimientos, $primerRegistro),
            0.01
        );
        $this->assertEqualsWithDelta(
            1200000.0,
            CuentacorrienteSaldosPorMoneda::saldoAnteriorEnPesosDe($movimientos, $primerRegistro, 2),
            0.01
        );
    }

    public function test_etiqueta_moneda_muestra_tc_al_expresar_en_pesos(): void
    {
        $usd = (object) [
            'moneda_id' => 2,
            'abreviatura' => 'USD',
            'cotizacion' => 1200,
        ];

        $local = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();
        $this->assertSame('USD', CuentacorrienteSaldosPorMoneda::etiquetaMonedaFila($usd, false));
        $this->assertSame(
            'USD → '.$local.' · TC 1.200,00',
            CuentacorrienteSaldosPorMoneda::etiquetaMonedaFila($usd, true)
        );
        $this->assertSame(
            $local,
            CuentacorrienteSaldosPorMoneda::etiquetaMonedaFila(
                (object) ['moneda_id' => 1, 'abreviatura' => 'ARS', 'cotizacion' => 1],
                true
            )
        );
    }

    public function test_saldo_corrido_nativo_y_en_pesos_conviven_sin_mezclar(): void
    {
        $movimientos = [
            (object) ['moneda_id' => 2, 'abreviatura' => 'USD', 'total' => 1000.0, 'cotizacion' => 1200],
            (object) ['moneda_id' => 1, 'abreviatura' => 'ARS', 'total' => 400000.0, 'cotizacion' => 1],
            (object) ['moneda_id' => 2, 'abreviatura' => 'USD', 'total' => 250.0, 'cotizacion' => 1200],
        ];

        $saldosNativos = [];
        $saldoPesos = 0.0;
        $nativosFila = [];
        $pesosFila = [];

        foreach ($movimientos as $i => $movimiento) {
            $saldosNativos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorrido(
                $saldosNativos,
                (int) $movimiento->moneda_id,
                (float) $movimiento->total
            );
            $saldoPesos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorridoPesos(
                $saldoPesos,
                $movimiento,
                (float) $movimiento->total
            );
            $nativosFila[$i] = $saldosNativos[(int) $movimiento->moneda_id];
            $pesosFila[$i] = $saldoPesos;
        }

        $this->assertSame(1000.0, $nativosFila[0]);
        $this->assertSame(400000.0, $nativosFila[1]);
        $this->assertSame(1250.0, $nativosFila[2]);
        $this->assertEqualsWithDelta(1200000.0, $pesosFila[0], 0.01);
        $this->assertEqualsWithDelta(1600000.0, $pesosFila[1], 0.01);
        $this->assertEqualsWithDelta(1900000.0, $pesosFila[2], 0.01);
        $this->assertNotContains(401000.0, $nativosFila);
        $this->assertNotContains(401250.0, $nativosFila);
        $this->assertSame('Saldo '.CuentacorrienteSaldosPorMoneda::abreviaturaLocal().' (TC)', CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPesos());
    }

    public function test_saldo_pendiente_en_pesos_usa_tc_del_comprobante(): void
    {
        $fila = (object) [
            'moneda_id' => 2,
            'abreviatura' => 'USD',
            'total' => 1000.0,
            'aplicado' => -200.0,
            'cotizacion' => 1200,
        ];

        $importes = CuentacorrienteSaldosPorMoneda::importesParaGrilla(
            $fila,
            false,
            static fn (float $total, $aplicado): float => abs($total + (float) $aplicado)
        );

        $this->assertEqualsWithDelta(800.0, $importes['saldo_pendiente_origen'], 0.01);
        $this->assertEqualsWithDelta(960000.0, $importes['saldo_pendiente_pesos'], 0.01);
        $this->assertSame(
            'Saldo pend. '.CuentacorrienteSaldosPorMoneda::abreviaturaLocal().' (TC)',
            CuentacorrienteSaldosPorMoneda::etiquetaColumnaSaldoPendientePesos()
        );
    }

    public function test_conversion_fnb_redondea_a_dos_decimales_como_anita(): void
    {
        $fnb868 = (object) ['moneda_id' => 2, 'total' => 9055.91, 'cotizacion' => 1515];
        $opaRelacionado = (object) ['moneda_id' => 2, 'total' => 9055.89, 'cotizacion' => 1515];

        $this->assertSame(
            13719703.65,
            CuentacorrienteSaldosPorMoneda::importeEnPesos($fnb868)
        );
        $this->assertSame(
            13719673.35,
            CuentacorrienteSaldosPorMoneda::importeEnPesos($opaRelacionado)
        );
        $this->assertEqualsWithDelta(
            30.30,
            CuentacorrienteSaldosPorMoneda::importeEnPesos($fnb868)
                - CuentacorrienteSaldosPorMoneda::importeEnPesos($opaRelacionado),
            0.001
        );
    }
}
