<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaTitoRecalculoSupport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TransferenciaMercaderiaTitoRecalculoSupportTest extends TestCase
{
    public function test_rango_mes_en_curso_usa_primer_y_ultimo_dia(): void
    {
        $rango = TransferenciaMercaderiaTitoRecalculoSupport::rangoMesEnCurso(
            new DateTimeImmutable('2026-09-01 13:00:00')
        );

        $this->assertSame('2026-09-01', $rango['desde']);
        $this->assertSame('2026-09-30', $rango['hasta']);
    }

    public function test_precio_requiere_cambio_tolera_redondeo_minimo(): void
    {
        $this->assertFalse(TransferenciaMercaderiaTitoRecalculoSupport::precioRequiereCambio(10.216233, 10.216233));
        $this->assertTrue(TransferenciaMercaderiaTitoRecalculoSupport::precioRequiereCambio(10.170233, 10.216233));
    }
}
