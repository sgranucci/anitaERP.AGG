<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoComprobanteIvaValidacionSupport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IngresoEgresoComprobanteIvaValidacionSupportTest extends TestCase
{
    public function test_opv_usa_asiento_como_referencia_sin_montos_caja(): void
    {
        $comprobantes = [
            ['total' => 100, 'moneda_id' => 1, 'cotizacion' => 1],
        ];

        // Sin montos de caja: con override de asiento no debe fallar.
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

    public function test_opv_sin_asiento_mensaje_especifico(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('La operación varia (OPV) debe tener asiento contable');

        IngresoEgresoComprobanteIvaValidacionSupport::validarTotales(
            [['total' => 100, 'moneda_id' => 1, 'cotizacion' => 1]],
            [],
            1,
            0.0,
        );
    }
}
