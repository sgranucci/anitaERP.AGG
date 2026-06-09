<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloSkuMatchSupport;
use Tests\TestCase;

final class ArticuloSkuMatchSupportTest extends TestCase
{
    public function test_normalizar_sku(): void
    {
        $this->assertSame('V0432', ArticuloSkuMatchSupport::normalizar(' v0432 '));
    }

    public function test_sku_legacy_duplicado(): void
    {
        $art = new \App\Models\Stock\Articulo;
        $art->id = 22394;
        $art->sku = 'V0432';

        $this->assertSame('DUP-22394-V0432', ArticuloSkuMatchSupport::skuLegacyDuplicado($art));
    }
}
