<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoComprobanteIvaValidacionSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IngresoEgresoComprobanteIvaValidacionSupportTest extends TestCase
{
    public function test_override_de_referencia_permite_cuadrar_sin_montos_caja(): void
    {
        $comprobantes = [
            ['total' => 100, 'moneda_id' => 1, 'cotizacion' => 1],
        ];

        // Override genérico (p. ej. total de asiento): sin montos de caja no debe fallar.
        IngresoEgresoComprobanteIvaValidacionSupport::validarTotales(
            $comprobantes,
            [],
            1,
            100.0,
        );

        $this->assertTrue(true);
    }

    public function test_sin_override_exige_montos_caja(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El movimiento de caja debe tener montos para cuadrar con los comprobantes IVA.');

        IngresoEgresoComprobanteIvaValidacionSupport::validarTotales(
            [['total' => 100, 'moneda_id' => 1, 'cotizacion' => 1]],
            [],
            1,
            null,
        );
    }

    public function test_override_en_cero_mensaje_especifico(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe tener asiento contable');

        IngresoEgresoComprobanteIvaValidacionSupport::validarTotales(
            [['total' => 100, 'moneda_id' => 1, 'cotizacion' => 1]],
            [],
            1,
            0.0,
        );
    }
}
