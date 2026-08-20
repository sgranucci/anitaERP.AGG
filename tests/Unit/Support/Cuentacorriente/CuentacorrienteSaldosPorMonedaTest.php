<?php

namespace Tests\Unit\Support\Cuentacorriente;

use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use PHPUnit\Framework\TestCase;

class CuentacorrienteSaldosPorMonedaTest extends TestCase
{
    public function test_consolidar_no_mezcla_ars_con_usd(): void
    {
        $saldos = [
            ['moneda_id' => 1, 'abreviatura' => 'ARS', 'saldo_cc' => 400000.0],
            ['moneda_id' => 2, 'abreviatura' => 'USD', 'saldo_cc' => 1000.0],
        ];
        $deudas = [
            ['moneda_id' => 1, 'abreviatura' => 'ARS', 'deuda' => 500000.0],
            ['moneda_id' => 2, 'abreviatura' => 'USD', 'deuda' => 1000.0],
        ];

        $consolidado = CuentacorrienteSaldosPorMoneda::consolidar($saldos, $deudas);

        $this->assertCount(2, $consolidado);
        $this->assertSame(1, $consolidado[0]['moneda_id']);
        $this->assertSame(400000.0, $consolidado[0]['saldo_cc']);
        $this->assertSame(500000.0, $consolidado[0]['deuda']);
        $this->assertSame(2, $consolidado[1]['moneda_id']);
        $this->assertSame(1000.0, $consolidado[1]['saldo_cc']);
        $this->assertSame(1000.0, $consolidado[1]['deuda']);

        $sumaMezclada = $consolidado[0]['saldo_cc'] + $consolidado[1]['saldo_cc'];
        $this->assertSame(401000.0, $sumaMezclada);
        $this->assertNotEquals(501000.0, $consolidado[0]['saldo_cc']);
        $this->assertNotEquals(501000.0, $consolidado[1]['saldo_cc']);
    }

    public function test_deuda_desde_filas_agrupa_por_moneda(): void
    {
        $filas = [
            (object) ['moneda_id' => 2, 'abreviatura' => 'USD', 'total' => 1000.0, 'aplicado' => 0.0],
            (object) ['moneda_id' => 1, 'abreviatura' => 'ARS', 'total' => 500000.0, 'aplicado' => -100000.0],
        ];

        $deudas = CuentacorrienteSaldosPorMoneda::deudaDesdeFilas($filas);

        $this->assertSame(400000.0, $deudas[0]['deuda']);
        $this->assertSame('ARS', $deudas[0]['abreviatura']);
        $this->assertSame(1000.0, $deudas[1]['deuda']);
        $this->assertSame('USD', $deudas[1]['abreviatura']);
    }

    public function test_saldo_anterior_con_filtro_usd_no_incluye_ars(): void
    {
        $movimientos = [
            (object) ['id' => 1, 'fecha' => '2026-01-01', 'moneda_id' => 1, 'total' => 500000.0],
            (object) ['id' => 2, 'fecha' => '2026-01-02', 'moneda_id' => 2, 'total' => 1000.0],
            (object) ['id' => 3, 'fecha' => '2026-01-03', 'moneda_id' => 1, 'total' => -100000.0],
            (object) ['id' => 4, 'fecha' => '2026-01-04', 'moneda_id' => 2, 'total' => 250.0],
        ];
        $primerRegistro = (object) ['id' => 4, 'fecha' => '2026-01-04'];

        $this->assertSame(
            1000.0,
            CuentacorrienteSaldosPorMoneda::saldoAnteriorDe($movimientos, $primerRegistro, 2)
        );
        $this->assertSame(
            400000.0,
            CuentacorrienteSaldosPorMoneda::saldoAnteriorDe($movimientos, $primerRegistro, 1)
        );
        $this->assertSame(
            401000.0,
            CuentacorrienteSaldosPorMoneda::saldoAnteriorDe($movimientos, $primerRegistro, null)
        );
    }

    public function test_resolver_moneda_id_trata_todas_como_null(): void
    {
        $this->assertNull(CuentacorrienteSaldosPorMoneda::resolverMonedaId(null));
        $this->assertNull(CuentacorrienteSaldosPorMoneda::resolverMonedaId(''));
        $this->assertNull(CuentacorrienteSaldosPorMoneda::resolverMonedaId('todas'));
        $this->assertNull(CuentacorrienteSaldosPorMoneda::resolverMonedaId(0));
        $this->assertSame(2, CuentacorrienteSaldosPorMoneda::resolverMonedaId('2'));
        $this->assertSame(2, CuentacorrienteSaldosPorMoneda::resolverMonedaId(2));
    }

    public function test_mostrar_saldo_corrido_solo_con_una_moneda(): void
    {
        $dos = [
            ['moneda_id' => 1, 'abreviatura' => 'ARS', 'saldo_cc' => 1.0, 'deuda' => 0.0],
            ['moneda_id' => 2, 'abreviatura' => 'USD', 'saldo_cc' => 1.0, 'deuda' => 0.0],
        ];

        $this->assertFalse(CuentacorrienteSaldosPorMoneda::mostrarSaldoCorrido(null, $dos));
        $this->assertTrue(CuentacorrienteSaldosPorMoneda::mostrarSaldoCorrido(2, $dos));
        $this->assertTrue(CuentacorrienteSaldosPorMoneda::mostrarSaldoCorrido(null, [array_shift($dos)]));
    }

    public function test_formatear_resumen_etiqueta_cada_moneda(): void
    {
        $items = [
            ['moneda_id' => 1, 'abreviatura' => 'ARS', 'saldo_cc' => 400000.0],
            ['moneda_id' => 2, 'abreviatura' => 'USD', 'saldo_cc' => 1000.0],
        ];

        $this->assertSame(
            'ARS 400.000,00 | USD 1.000,00',
            CuentacorrienteSaldosPorMoneda::formatearResumen($items, 'saldo_cc')
        );
    }

    public function test_saldo_corrido_acumula_por_moneda_sin_mezclar(): void
    {
        $movimientos = [
            (object) ['id' => 1, 'fecha' => '2026-01-01', 'moneda_id' => 2, 'total' => 1000.0],
            (object) ['id' => 2, 'fecha' => '2026-01-02', 'moneda_id' => 1, 'total' => 500000.0],
            (object) ['id' => 3, 'fecha' => '2026-01-03', 'moneda_id' => 1, 'total' => -100000.0],
            (object) ['id' => 4, 'fecha' => '2026-01-04', 'moneda_id' => 2, 'total' => 250.0],
        ];

        $saldos = [];
        $saldosFila = [];
        foreach ($movimientos as $movimiento) {
            $saldos = CuentacorrienteSaldosPorMoneda::acumularSaldoCorrido(
                $saldos,
                (int) $movimiento->moneda_id,
                (float) $movimiento->total
            );
            $saldosFila[$movimiento->id] = $saldos[(int) $movimiento->moneda_id];
        }

        $this->assertSame(1000.0, $saldosFila[1]);
        $this->assertSame(500000.0, $saldosFila[2]);
        $this->assertSame(400000.0, $saldosFila[3]);
        $this->assertSame(1250.0, $saldosFila[4]);
        $this->assertSame(400000.0, $saldos[1]);
        $this->assertSame(1250.0, $saldos[2]);
        $this->assertNotContains(501000.0, $saldosFila);
        $this->assertNotContains(401250.0, $saldosFila);
    }

    public function test_saldos_anteriores_por_moneda_separan_ars_de_usd(): void
    {
        $movimientos = [
            (object) ['id' => 1, 'fecha' => '2026-01-01', 'moneda_id' => 1, 'total' => 500000.0],
            (object) ['id' => 2, 'fecha' => '2026-01-02', 'moneda_id' => 2, 'total' => 1000.0],
            (object) ['id' => 3, 'fecha' => '2026-01-03', 'moneda_id' => 1, 'total' => -100000.0],
            (object) ['id' => 4, 'fecha' => '2026-01-04', 'moneda_id' => 2, 'total' => 250.0],
        ];
        $primerRegistro = (object) ['id' => 4, 'fecha' => '2026-01-04'];

        $anteriores = CuentacorrienteSaldosPorMoneda::saldosAnterioresPorMonedaDe(
            $movimientos,
            $primerRegistro
        );

        $this->assertSame(400000.0, $anteriores[1]);
        $this->assertSame(1000.0, $anteriores[2]);
    }

    public function test_resolver_expresion_solo_acepta_pesos(): void
    {
        $this->assertSame(
            CuentacorrienteSaldosPorMoneda::EXPRESION_ORIGEN,
            CuentacorrienteSaldosPorMoneda::resolverExpresion(null)
        );
        $this->assertSame(
            CuentacorrienteSaldosPorMoneda::EXPRESION_ORIGEN,
            CuentacorrienteSaldosPorMoneda::resolverExpresion('otra')
        );
        $this->assertSame(
            CuentacorrienteSaldosPorMoneda::EXPRESION_PESOS,
            CuentacorrienteSaldosPorMoneda::resolverExpresion('pesos')
        );
        $this->assertTrue(CuentacorrienteSaldosPorMoneda::esExpresionPesos('pesos'));
        $this->assertFalse(CuentacorrienteSaldosPorMoneda::esExpresionPesos('origen'));
    }
}
