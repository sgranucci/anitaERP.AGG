<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoCanjeChequeSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoCanjeChequeSupportTest extends TestCase
{
    public function test_detalle_deterministico_basico(): void
    {
        $texto = IngresoEgresoCanjeChequeSupport::detalleDeterministico([
            [
                'numerocheque' => '123',
                'banco' => 'Macro',
                'origen' => 'E',
                'monto' => 1000,
            ],
        ]);

        $this->assertStringContainsString('Canje:', $texto);
        $this->assertStringContainsString('123', $texto);
        $this->assertStringContainsString('Macro', $texto);
    }

    public function test_assert_tiene_reemplazos_omite_sin_tipo_canje(): void
    {
        IngresoEgresoCanjeChequeSupport::assertTieneReemplazos([
            'tipotransaccion_caja_id' => 0,
        ]);
        $this->assertTrue(true);
    }
}
