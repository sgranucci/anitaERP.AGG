<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloStkmaeAnitaBridgeSupport;
use PHPUnit\Framework\TestCase;

class ArticuloStkmaeAnitaBridgeSupportTest extends TestCase
{
    public function test_sql_texto_duplica_apostrofos_para_informix(): void
    {
        $this->assertSame("PHARAOH''S FORTUNE", ArticuloStkmaeAnitaBridgeSupport::sqlTexto("PHARAOH'S FORTUNE"));
    }

    public function test_sql_texto_null_y_vacio(): void
    {
        $this->assertSame('', ArticuloStkmaeAnitaBridgeSupport::sqlTexto(null));
        $this->assertSame('', ArticuloStkmaeAnitaBridgeSupport::sqlTexto(''));
    }

    public function test_sql_texto_sin_apostrofo_queda_igual(): void
    {
        $this->assertSame('SIN APOSTROFO', ArticuloStkmaeAnitaBridgeSupport::sqlTexto('SIN APOSTROFO'));
    }
}
