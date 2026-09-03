<?php

namespace Tests\Unit\Support\Numerico;

use App\Support\Numerico\NumeroDecimalLocalSupport;
use PHPUnit\Framework\TestCase;

class NumeroDecimalLocalSupportTest extends TestCase
{
    public function test_formato_en_con_miles_no_se_achica(): void
    {
        $this->assertSame(1535.0, NumeroDecimalLocalSupport::aFloat('1,535.00'));
        $this->assertSame(16462.78, NumeroDecimalLocalSupport::aFloat('16,462.78'));
        $this->assertSame(19919.96, NumeroDecimalLocalSupport::aFloat('19,919.96'));
    }

    public function test_formato_ar_con_miles(): void
    {
        $this->assertSame(1535.0, NumeroDecimalLocalSupport::aFloat('1.535,00'));
        $this->assertSame(16462.78, NumeroDecimalLocalSupport::aFloat('16.462,78'));
    }
}
