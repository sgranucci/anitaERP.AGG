<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionMatcherSupport;
use PHPUnit\Framework\TestCase;

class ProveedorCuentacorrienteAplicacionMatcherSupportTest extends TestCase
{
    public function test_fifo_aplica_credito_viejo_contra_deuda_mas_vencida(): void
    {
        $creditos = [
            ['id' => 2, 'saldo' => 80.0, 'moneda_id' => 1, 'fecha' => '2026-03-01', 'vencimiento' => '2026-03-01'],
            ['id' => 1, 'saldo' => 100.0, 'moneda_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-01'],
        ];
        $deudas = [
            ['id' => 10, 'saldo' => 60.0, 'moneda_id' => 1, 'fecha' => '2026-02-01', 'vencimiento' => '2026-04-01'],
            ['id' => 11, 'saldo' => 90.0, 'moneda_id' => 1, 'fecha' => '2026-01-15', 'vencimiento' => '2026-02-01'],
        ];

        $lineas = ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifo($creditos, $deudas);

        $this->assertSame([
            ['credito_id' => 1, 'deuda_id' => 11, 'monto' => 90.0],
            ['credito_id' => 1, 'deuda_id' => 10, 'monto' => 10.0],
            ['credito_id' => 2, 'deuda_id' => 10, 'monto' => 50.0],
        ], $lineas);
    }

    public function test_fifo_no_cruza_monedas(): void
    {
        $creditos = [
            ['id' => 1, 'saldo' => 100.0, 'moneda_id' => 2, 'fecha' => '2026-01-01', 'vencimiento' => null],
        ];
        $deudas = [
            ['id' => 10, 'saldo' => 100.0, 'moneda_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-01'],
        ];

        $this->assertSame([], ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifo($creditos, $deudas));
    }

    public function test_parear_importes_solo_match_exacto(): void
    {
        $creditos = [
            ['id' => 1, 'saldo' => 150.0, 'moneda_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => null],
            ['id' => 2, 'saldo' => 40.0, 'moneda_id' => 1, 'fecha' => '2026-01-02', 'vencimiento' => null],
        ];
        $deudas = [
            ['id' => 10, 'saldo' => 40.0, 'moneda_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-10'],
            ['id' => 11, 'saldo' => 200.0, 'moneda_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-05'],
        ];

        $lineas = ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirParearImportes($creditos, $deudas);

        $this->assertSame([
            ['credito_id' => 2, 'deuda_id' => 10, 'monto' => 40.0],
        ], $lineas);
    }

    public function test_validar_lineas_detecta_sobreaplicacion_y_moneda(): void
    {
        $creditos = [
            1 => ['id' => 1, 'saldo' => 50.0, 'moneda_id' => 1, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-10'],
        ];
        $deudas = [
            10 => ['id' => 10, 'saldo' => 80.0, 'moneda_id' => 1, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-01'],
            11 => ['id' => 11, 'saldo' => 80.0, 'moneda_id' => 2, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-01'],
        ];

        $errores = ProveedorCuentacorrienteAplicacionMatcherSupport::validarLineas($creditos, $deudas, [
            ['credito_id' => 1, 'deuda_id' => 10, 'monto' => 40],
            ['credito_id' => 1, 'deuda_id' => 11, 'monto' => 20],
        ], '2026-01-05');

        $this->assertNotEmpty($errores);
        $texto = implode(' ', $errores);
        $this->assertStringContainsString('cotización de liquidación', $texto);
        $this->assertStringContainsString('fecha de aplicación', $texto);

        $erroresSobre = ProveedorCuentacorrienteAplicacionMatcherSupport::validarLineas($creditos, $deudas, [
            ['credito_id' => 1, 'deuda_id' => 10, 'monto' => 80],
        ], '2026-01-10');
        $this->assertStringContainsString('se aplica por', implode(' ', $erroresSobre));
    }

    public function test_validar_lineas_ok(): void
    {
        $creditos = [
            1 => ['id' => 1, 'saldo' => 50.0, 'moneda_id' => 1, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-01'],
        ];
        $deudas = [
            10 => ['id' => 10, 'saldo' => 80.0, 'moneda_id' => 1, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-02'],
        ];

        $errores = ProveedorCuentacorrienteAplicacionMatcherSupport::validarLineas($creditos, $deudas, [
            ['credito_id' => 1, 'deuda_id' => 10, 'monto' => 50],
        ], '2026-01-02');

        $this->assertSame([], $errores);
    }

    public function test_aging_labels(): void
    {
        $this->assertSame('60', ProveedorCuentacorrienteAplicacionFilaSupport::aging(61, 'deuda'));
        $this->assertSame('30', ProveedorCuentacorrienteAplicacionFilaSupport::aging(31, 'deuda'));
        $this->assertSame('hoy', ProveedorCuentacorrienteAplicacionFilaSupport::aging(0, 'deuda'));
        $this->assertSame('a_vencer', ProveedorCuentacorrienteAplicacionFilaSupport::aging(-5, 'deuda'));
        $this->assertSame('credito', ProveedorCuentacorrienteAplicacionFilaSupport::aging(10, 'credito'));
    }

    public function test_fifo_restante_respeta_reservas_y_omisiones(): void
    {
        $creditos = [
            ['id' => 1, 'saldo' => 100.0, 'moneda_id' => 1, 'empresa_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => null],
        ];
        $deudas = [
            ['id' => 10, 'saldo' => 40.0, 'moneda_id' => 1, 'empresa_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-01'],
            ['id' => 11, 'saldo' => 70.0, 'moneda_id' => 1, 'empresa_id' => 1, 'fecha' => '2026-01-02', 'vencimiento' => '2026-01-02'],
        ];

        $lineas = ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifoRestante(
            $creditos,
            $deudas,
            [['credito_id' => 1, 'deuda_id' => 10, 'monto' => 40.0]],
            [11],
            []
        );

        $this->assertSame([], $lineas);

        $lineas = ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifoRestante(
            $creditos,
            $deudas,
            [['credito_id' => 1, 'deuda_id' => 10, 'monto' => 40.0]],
            [],
            []
        );

        $this->assertSame([
            ['credito_id' => 1, 'deuda_id' => 11, 'monto' => 60.0],
        ], $lineas);
    }

    public function test_validar_lineas_acepta_cruzada_con_cotizacion(): void
    {
        $creditos = [
            1 => ['id' => 1, 'saldo' => 1100000.0, 'moneda_id' => 1, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-01', 'cotizacion' => 1],
        ];
        $deudas = [
            10 => ['id' => 10, 'saldo' => 1000.0, 'moneda_id' => 2, 'empresa_id' => 1, 'proveedor_id' => 9, 'fecha' => '2026-01-01', 'cotizacion' => 1200],
        ];

        $errores = ProveedorCuentacorrienteAplicacionMatcherSupport::validarLineas($creditos, $deudas, [
            ['credito_id' => 1, 'deuda_id' => 10, 'monto' => 1000, 'cotizacion_liquidacion' => 1100],
        ], '2026-01-10');

        $this->assertSame([], $errores);
    }

    public function test_fifo_no_cruza_empresa(): void
    {
        $creditos = [
            ['id' => 1, 'saldo' => 100.0, 'moneda_id' => 1, 'empresa_id' => 1, 'fecha' => '2026-01-01', 'vencimiento' => null],
        ];
        $deudas = [
            ['id' => 10, 'saldo' => 100.0, 'moneda_id' => 1, 'empresa_id' => 2, 'fecha' => '2026-01-01', 'vencimiento' => '2026-01-01'],
        ];

        $this->assertSame([], ProveedorCuentacorrienteAplicacionMatcherSupport::sugerirFifo($creditos, $deudas));
    }
}
