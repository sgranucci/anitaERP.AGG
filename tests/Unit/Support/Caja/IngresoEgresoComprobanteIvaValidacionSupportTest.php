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

    public function test_fechaiva_igual_a_fecha_ie_ok(): void
    {
        IngresoEgresoComprobanteIvaValidacionSupport::validarFechasContabilizacion(
            [
                ['letra' => 'A', 'sucursal' => 20, 'numerocomprobante' => 1, 'fechaiva' => '2026-10-28'],
                ['letra' => 'A', 'sucursal' => 84, 'numerocomprobante' => 2, 'fechaiva' => '2026-10-28'],
            ],
            '2026-10-28',
        );

        $this->assertTrue(true);
    }

    public function test_fechaiva_distinta_a_fecha_ie_falla(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe coincidir con la fecha del ingreso/egreso');

        IngresoEgresoComprobanteIvaValidacionSupport::validarFechasContabilizacion(
            [
                ['letra' => 'A', 'sucursal' => 20, 'numerocomprobante' => 26960, 'fechaiva' => '2026-10-16'],
            ],
            '2026-11-11',
        );
    }
}
