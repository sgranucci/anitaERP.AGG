<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoCotizacionMonedaSupport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IngresoEgresoCotizacionMonedaSupportTest extends TestCase
{
    public function test_corta_la_cuenta_de_caja_en_dolares_sin_cotizacion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IngresoEgresoCotizacionMonedaSupport::assertCotizacionEnMonedaExtranjera([
            'cuentacaja_ids' => [5],
            'moneda_ids' => [2],
            'cotizaciones' => [1],
            'montos' => [100],
        ]);
    }

    public function test_corta_el_asiento_y_los_cheques_en_dolares_sin_cotizacion(): void
    {
        $asiento = [
            'monedaasiento_ids' => [2],
            'cotizacionasientos' => [1],
            'debeasientos' => [100],
            'haberasientos' => [0],
        ];
        $this->assertSame('cotizacionasientos', IngresoEgresoCotizacionMonedaSupport::campoConError($asiento));

        $cheque = [
            'moneda_emitido_ids' => [2],
            'cotizacioncheque_emitidos' => [0],
            'montocheque_emitidos' => [50],
        ];
        $this->assertSame('cotizacioncheque_emitidos', IngresoEgresoCotizacionMonedaSupport::campoConError($cheque));

        foreach ([$asiento, $cheque] as $data) {
            try {
                IngresoEgresoCotizacionMonedaSupport::assertCotizacionEnMonedaExtranjera($data);
                $this->fail('Debía cortar por cotización en moneda extranjera.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('cotización', $e->getMessage());
            }
        }
    }

    public function test_pasa_en_pesos_con_cotizacion_valida_y_en_renglones_sin_importe(): void
    {
        $casos = [
            'pesos' => [
                'cuentacaja_ids' => [5],
                'moneda_ids' => [1],
                'cotizaciones' => [1],
                'montos' => [100],
            ],
            'dolares con cotización' => [
                'cuentacaja_ids' => [5],
                'moneda_ids' => [2],
                'cotizaciones' => [1490],
                'montos' => [100],
            ],
            'renglón en dólares sin importe' => [
                'cuentacaja_ids' => [5],
                'moneda_ids' => [2],
                'cotizaciones' => [0],
                'montos' => [0],
            ],
        ];

        foreach ($casos as $nombre => $data) {
            IngresoEgresoCotizacionMonedaSupport::assertCotizacionEnMonedaExtranjera($data);
            $this->assertTrue(true, $nombre);
        }
    }
}
