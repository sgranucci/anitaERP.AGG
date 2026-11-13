<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ClienteExclusionPercepcionSupport;
use Tests\TestCase;

class ClienteExclusionPercepcionSupportTest extends TestCase
{
    public function test_elige_iva_vigente_hoy(): void
    {
        [$desde, $hasta] = ClienteExclusionPercepcionSupport::elegirFechasIvaParaCabecera([
            ['tipo' => 'IVA', 'provincia_id' => null, 'porcentaje' => 0, 'desde' => '2026-04-01', 'hasta' => '2026-09-30'],
            ['tipo' => 'IVA', 'provincia_id' => null, 'porcentaje' => 0, 'desde' => '2026-10-01', 'hasta' => '2027-03-31'],
            ['tipo' => 'IIBB', 'provincia_id' => 1, 'porcentaje' => 0, 'desde' => '2026-01-01', 'hasta' => '2026-12-31'],
        ], '2026-11-13');

        $this->assertSame('2026-10-01', $desde);
        $this->assertSame('2027-03-31', $hasta);
    }

    public function test_elige_proxima_futura_si_no_hay_vigente(): void
    {
        [$desde, $hasta] = ClienteExclusionPercepcionSupport::elegirFechasIvaParaCabecera([
            ['tipo' => 'IVA', 'provincia_id' => null, 'porcentaje' => 0, 'desde' => '2026-04-01', 'hasta' => '2026-09-30'],
            ['tipo' => 'IVA', 'provincia_id' => null, 'porcentaje' => 0, 'desde' => '2027-04-01', 'hasta' => '2027-09-30'],
        ], '2026-11-13');

        $this->assertSame('2027-04-01', $desde);
        $this->assertSame('2027-09-30', $hasta);
    }

    public function test_sin_iva_devuelve_nulos(): void
    {
        [$desde, $hasta] = ClienteExclusionPercepcionSupport::elegirFechasIvaParaCabecera([
            ['tipo' => 'IIBB', 'provincia_id' => 2, 'porcentaje' => 1.5, 'desde' => '2026-01-01', 'hasta' => '2026-12-31'],
        ], '2026-11-13');

        $this->assertNull($desde);
        $this->assertNull($hasta);
    }
}
