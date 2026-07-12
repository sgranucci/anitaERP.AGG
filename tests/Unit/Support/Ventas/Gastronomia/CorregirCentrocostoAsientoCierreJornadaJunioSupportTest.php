<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CorregirCentrocostoAsientoCierreJornadaJunioSupport;
use PHPUnit\Framework\TestCase;

final class CorregirCentrocostoAsientoCierreJornadaJunioSupportTest extends TestCase
{
    public function test_mes_es_junio(): void
    {
        $this->assertSame(6, CorregirCentrocostoAsientoCierreJornadaJunioSupport::MES);
    }
}
