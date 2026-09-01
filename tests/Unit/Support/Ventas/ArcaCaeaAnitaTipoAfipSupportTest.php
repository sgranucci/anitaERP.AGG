<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaAnitaTipoAfipSupport;
use PHPUnit\Framework\TestCase;

final class ArcaCaeaAnitaTipoAfipSupportTest extends TestCase
{
    public function test_ndp_a_es_nota_debito_afip_2(): void
    {
        self::assertSame(2, ArcaCaeaAnitaTipoAfipSupport::tipoAfipDesdeAnita('NDP', 'A'));
    }
}
