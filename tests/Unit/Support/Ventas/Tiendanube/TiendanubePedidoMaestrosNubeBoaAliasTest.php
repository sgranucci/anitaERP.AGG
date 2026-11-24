<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Tiendanube;

use App\Support\Ventas\Tiendanube\TiendanubePedidoMaestrosSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use PHPUnit\Framework\TestCase;

final class TiendanubePedidoMaestrosNubeBoaAliasTest extends TestCase
{
    public function test_es_boaonda_por_clave_configurada(): void
    {
        $this->assertTrue(TiendanubeTiendasSupport::esBoaonda('6250382'));
        $this->assertFalse(TiendanubeTiendasSupport::esBoaonda('3796054'));
        $this->assertFalse(TiendanubeTiendasSupport::esBoaonda(null));
    }

    public function test_prioriza_nube_boa_solo_en_boaonda_con_pago_nube(): void
    {
        $keys = TiendanubePedidoMaestrosSupport::priorizarAliasesNubeBoa(
            '6250382',
            ['pago-nube', 'pago nube']
        );
        $this->assertSame(['nube boa', 'boa', 'pago-nube', 'pago nube'], $keys);

        $ferli = TiendanubePedidoMaestrosSupport::priorizarAliasesNubeBoa(
            '3796054',
            ['pago-nube', 'pago nube']
        );
        $this->assertSame(['pago-nube', 'pago nube'], $ferli);

        $sinPago = TiendanubePedidoMaestrosSupport::priorizarAliasesNubeBoa(
            '6250382',
            ['gocuotas']
        );
        $this->assertSame(['gocuotas'], $sinPago);
    }
}
