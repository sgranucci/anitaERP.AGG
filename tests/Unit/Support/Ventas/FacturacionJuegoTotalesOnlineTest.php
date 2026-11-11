<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Models\Caja\Flash\FlashCrown;
use App\Support\Ventas\FacturacionJuegoTipoSupport;
use App\Support\Ventas\FacturacionJuegoTotalesSupport;
use PHPUnit\Framework\TestCase;

final class FacturacionJuegoTotalesOnlineTest extends TestCase
{
    public function test_resultado_online_desde_ol_win_y_bs_win(): void
    {
        $flash = new FlashCrown();
        $flash->forceFill([
            'ol_win' => 1250.50,
            'bs_win' => -320.10,
            'proc_del_rio' => ' ',
            'proc_betsur' => '*',
        ]);

        self::assertSame(
            1250.50,
            FacturacionJuegoTotalesSupport::resultadoRubro($flash, FacturacionJuegoTipoSupport::RUBRO_DEL_RIO),
        );
        self::assertSame(
            -320.10,
            FacturacionJuegoTotalesSupport::resultadoRubro($flash, FacturacionJuegoTipoSupport::RUBRO_BETSUR),
        );
        self::assertFalse(
            FacturacionJuegoTotalesSupport::procesado($flash, FacturacionJuegoTipoSupport::RUBRO_DEL_RIO),
        );
        self::assertTrue(
            FacturacionJuegoTotalesSupport::procesado($flash, FacturacionJuegoTipoSupport::RUBRO_BETSUR),
        );
    }

    public function test_rubros_incluye_online_y_betsur(): void
    {
        $rubros = FacturacionJuegoTipoSupport::rubros();
        self::assertContains(FacturacionJuegoTipoSupport::RUBRO_DEL_RIO, $rubros);
        self::assertContains(FacturacionJuegoTipoSupport::RUBRO_BETSUR, $rubros);
        self::assertTrue(FacturacionJuegoTipoSupport::esOnline(FacturacionJuegoTipoSupport::RUBRO_DEL_RIO));
        self::assertFalse(FacturacionJuegoTipoSupport::esOnline(FacturacionJuegoTipoSupport::RUBRO_SLOT));
    }
}
