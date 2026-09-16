<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequePropioInstrumentoSupport;
use PHPUnit\Framework\TestCase;

class ChequePropioInstrumentoSupportTest extends TestCase
{
    public function test_defaults_emision_e_cheq(): void
    {
        $this->assertSame('N', ChequePropioInstrumentoSupport::caracterDefault());
        $this->assertSame('E', ChequePropioInstrumentoSupport::paraDepDefault());
        $this->assertSame('E', ChequePropioInstrumentoSupport::negociableDefault());
        $this->assertSame('E', ChequePropioInstrumentoSupport::negociable('', null));
        $this->assertSame('E', ChequePropioInstrumentoSupport::negociableDesdeChequera(''));
        $this->assertSame('E', ChequePropioInstrumentoSupport::negociableDesdeChequera('E'));
        $this->assertSame('N', ChequePropioInstrumentoSupport::negociableDesdeChequera('F'));
    }
}
