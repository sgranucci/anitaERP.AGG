<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CierreRendicionEstacionamientoCentrocostoSupport;
use PHPUnit\Framework\TestCase;

final class CierreRendicionEstacionamientoCentrocostoSupportTest extends TestCase
{
    public function test_codigo_centrocosto_default(): void
    {
        $this->assertSame('80', CierreRendicionEstacionamientoCentrocostoSupport::CODIGO_CENTROCOSTO_DEFAULT);
    }
}
