<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecuentoModoCierreSupport;
use Tests\TestCase;

class RecuentoModoCierreSupportTest extends TestCase
{
    public function test_resolver_modo_invalido_usa_saldo_actual(): void
    {
        $this->assertSame(
            RecuentoModoCierreSupport::MODO_SALDO_ACTUAL,
            RecuentoModoCierreSupport::resolverModo('otro')
        );
    }

    public function test_etiquetas_modo(): void
    {
        $this->assertSame(
            'A fecha del recuento',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_FECHA_RECUENTO)
        );
        $this->assertSame(
            'Al saldo actual',
            RecuentoModoCierreSupport::etiqueta(RecuentoModoCierreSupport::MODO_SALDO_ACTUAL)
        );
    }
}
