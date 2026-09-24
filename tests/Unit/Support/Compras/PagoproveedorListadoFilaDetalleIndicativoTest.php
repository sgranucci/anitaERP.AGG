<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorListadoFila;
use PHPUnit\Framework\TestCase;

class PagoproveedorListadoFilaDetalleIndicativoTest extends TestCase
{
    public function test_opp_confirmada_no_cambia(): void
    {
        $this->assertSame(
            'Orden de pago Nro. 125102',
            PagoproveedorListadoFila::formatearDetalleIndicativo(
                'Orden de pago Nro. 125102',
                'CONFIRMADA',
                'OPP 1-125102'
            )
        );
    }

    public function test_opp_revertida_prefija_texto(): void
    {
        $this->assertSame(
            'REVERTIDA: Orden de pago Nro. 125102',
            PagoproveedorListadoFila::formatearDetalleIndicativo(
                'Orden de pago Nro. 125102',
                'REVERTIDA',
                'OPP 1-125102'
            )
        );
    }

    public function test_aop_conserva_anula(): void
    {
        $this->assertSame(
            'ANULA OPP 125102: Orden de pago Nro. 125102',
            PagoproveedorListadoFila::formatearDetalleIndicativo(
                'ANULA OPP 125102: Orden de pago Nro. 125102',
                'REVERTIDA',
                'AOP 1-125102'
            )
        );
    }

    public function test_no_duplica_prefijo_revertida(): void
    {
        $this->assertSame(
            'REVERTIDA: Orden de pago Nro. 125102',
            PagoproveedorListadoFila::formatearDetalleIndicativo(
                'REVERTIDA: Orden de pago Nro. 125102',
                'REVERTIDA',
                'OPP 1-125102'
            )
        );
    }
}
