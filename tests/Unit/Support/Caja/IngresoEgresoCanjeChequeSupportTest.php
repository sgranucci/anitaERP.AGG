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

    public function test_detalle_marca_reemplazo_por_transferencia(): void
    {
        $texto = IngresoEgresoCanjeChequeSupport::detalleDeterministico([
            [
                'numerocheque' => '8200303',
                'banco' => 'Macro',
                'origen' => 'E',
                'monto' => 437151.80,
                'medio' => 'T',
                'cuentacaja_nombre' => 'Banco Macro',
            ],
        ]);

        $this->assertStringContainsString('reemplazado por transferencia', $texto);
        $this->assertStringContainsString('Banco Macro', $texto);
    }

    public function test_transferencia_exige_cuenta_e_importe_y_no_numero(): void
    {
        IngresoEgresoCanjeChequeSupport::assertFilasReemplazo([
            'cheque_anulado_ids' => [10],
            'origen_reemplazo' => ['T'],
            'numerocheque_reemplazo' => [''],
            'montocheque_reemplazo' => [1000],
            'cuentacaja_reemplazo_ids' => [4],
        ]);
        $this->assertTrue(true);
    }

    public function test_transferencia_sin_cuenta_falla(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cuenta de banco');

        IngresoEgresoCanjeChequeSupport::assertFilasReemplazo([
            'cheque_anulado_ids' => [10],
            'origen_reemplazo' => ['T'],
            'montocheque_reemplazo' => [1000],
            'cuentacaja_reemplazo_ids' => [0],
        ]);
    }

    public function test_assert_tiene_reemplazos_omite_sin_tipo_canje(): void
    {
        IngresoEgresoCanjeChequeSupport::assertTieneReemplazos([
            'tipotransaccion_caja_id' => 0,
        ]);
        $this->assertTrue(true);
    }
}
