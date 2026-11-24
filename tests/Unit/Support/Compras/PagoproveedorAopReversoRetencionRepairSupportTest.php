<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorAopReversoRetencionRepairSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorAopReversoRetencionRepairSupportTest extends TestCase
{
    public function test_desde_default_es_octubre_2026(): void
    {
        $this->assertSame('2026-10-01', PagoproveedorAopReversoRetencionRepairSupport::DESDE_DEFAULT);
    }
}
