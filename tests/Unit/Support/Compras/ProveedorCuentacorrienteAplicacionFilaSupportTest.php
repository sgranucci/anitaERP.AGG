<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use PHPUnit\Framework\TestCase;

class ProveedorCuentacorrienteAplicacionFilaSupportTest extends TestCase
{
    public function test_abreviatura_usa_tipo_de_transaccion(): void
    {
        $this->assertSame(
            'FNB',
            ProveedorCuentacorrienteAplicacionFilaSupport::abreviaturaDesdePartes('fnb', 'OPA', 'FAC')
        );
    }

    public function test_abreviatura_de_pago_usa_tipocomprobante(): void
    {
        $this->assertSame(
            'OPA',
            ProveedorCuentacorrienteAplicacionFilaSupport::abreviaturaDesdePartes('', 'opa', 'PAGO')
        );
    }

    public function test_abreviatura_cae_al_tipo_generico(): void
    {
        $this->assertSame(
            'NC',
            ProveedorCuentacorrienteAplicacionFilaSupport::abreviaturaDesdePartes('', '', 'nc')
        );
    }
}
