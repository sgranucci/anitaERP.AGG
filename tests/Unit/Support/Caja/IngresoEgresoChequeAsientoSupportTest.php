<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoChequeAsientoSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoChequeAsientoSupportTest extends TestCase
{
    public function test_cheque_emitido_siempre_haber_en_op_egreso(): void
    {
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhEmitido());
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhEmitido(false));
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhEmitido(true));
    }

    public function test_cheque_recibido_ingreso_debe_egreso_haber(): void
    {
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhRecibido(1));
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhRecibido(-1));
        $this->assertSame('H', IngresoEgresoChequeAsientoSupport::dhRecibido(1, true));
        $this->assertSame('D', IngresoEgresoChequeAsientoSupport::dhRecibido(-1, true));
    }
}
