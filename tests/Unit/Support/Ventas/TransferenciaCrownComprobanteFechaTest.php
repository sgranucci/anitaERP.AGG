<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownComprobante;
use PHPUnit\Framework\TestCase;

final class TransferenciaCrownComprobanteFechaTest extends TestCase
{
    public function test_la_emision_y_la_jornada_quedan_separadas_si_el_cierre_pasa_la_medianoche(): void
    {
        $fechas = TransferenciaCrownComprobante::fechasEmisionYJornada('20261101', '20261031', '2026-11-03');

        $this->assertSame('2026-11-01', $fechas['fecha']);
        $this->assertSame('2026-10-31', $fechas['fechajornada']);
    }

    public function test_si_falta_una_fecha_usa_la_otra(): void
    {
        $soloEmision = TransferenciaCrownComprobante::fechasEmisionYJornada('20261031', null, '2026-11-03');
        $soloJornada = TransferenciaCrownComprobante::fechasEmisionYJornada('', '20261031', '2026-11-03');

        $this->assertSame('2026-10-31', $soloEmision['fecha']);
        $this->assertSame('2026-10-31', $soloEmision['fechajornada']);
        $this->assertSame('2026-10-31', $soloJornada['fecha']);
        $this->assertSame('2026-10-31', $soloJornada['fechajornada']);
    }

    public function test_sin_fechas_usa_el_dia_de_hoy(): void
    {
        $fechas = TransferenciaCrownComprobante::fechasEmisionYJornada(null, null, '2026-11-03');

        $this->assertSame('2026-11-03', $fechas['fecha']);
        $this->assertSame('2026-11-03', $fechas['fechajornada']);
    }
}
