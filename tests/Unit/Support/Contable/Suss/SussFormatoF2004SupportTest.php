<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Suss;

use App\Support\Contable\Suss\SussFormatoF2004Support;
use PHPUnit\Framework\TestCase;

class SussFormatoF2004SupportTest extends TestCase
{
    public function test_tolerancia_de_conciliacion_es_cien_pesos(): void
    {
        $this->assertSame(100.0, SussFormatoF2004Support::tolerancia());
    }

    public function test_cuadra_dentro_de_cien_pesos(): void
    {
        $this->assertTrue(SussFormatoF2004Support::cuadra(337172.56, 337168.56));
        $this->assertTrue(SussFormatoF2004Support::cuadra(1000.00, 1100.00));
        $this->assertTrue(SussFormatoF2004Support::cuadra(1000.00, 900.00));
    }

    public function test_no_cuadra_fuera_de_cien_pesos(): void
    {
        $this->assertFalse(SussFormatoF2004Support::cuadra(337172.56, 337071.56));
        $this->assertFalse(SussFormatoF2004Support::cuadra(1000.00, 1100.01));
    }
}
