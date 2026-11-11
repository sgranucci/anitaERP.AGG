<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\FacturacionJuegoTipoSupport;
use PHPUnit\Framework\TestCase;

final class FacturacionJuegoTipoSupportTest extends TestCase
{
    public function test_es_ganancia_respeta_accessor_s_r(): void
    {
        $fme = new Tipotransaccion();
        $fme->setRawAttributes(['signo' => 1], true);
        self::assertSame('S', $fme->signo);
        self::assertTrue(FacturacionJuegoTipoSupport::esGanancia($fme));

        $cme = new Tipotransaccion();
        $cme->setRawAttributes(['signo' => -1], true);
        self::assertSame('R', $cme->signo);
        self::assertFalse(FacturacionJuegoTipoSupport::esGanancia($cme));
    }

    public function test_concepto_mayor_incluye_codigo_y_trunca_a_30(): void
    {
        $concepto = FacturacionJuegoTipoSupport::conceptoMayor(
            'CME B-00015-00017046',
            'Mesas de juego',
        );
        self::assertSame('CME B-00015-00017046 Mesas de ', $concepto);
        self::assertSame(30, mb_strlen($concepto));
    }
}
