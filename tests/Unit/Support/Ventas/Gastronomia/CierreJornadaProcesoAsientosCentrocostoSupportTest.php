<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosCentrocostoSupport;
use PHPUnit\Framework\TestCase;

final class CierreJornadaProcesoAsientosCentrocostoSupportTest extends TestCase
{
    public function test_codigo_centrocosto_default(): void
    {
        $this->assertSame('85', CierreJornadaProcesoAsientosCentrocostoSupport::CODIGO_CENTROCOSTO_DEFAULT);
    }
}
