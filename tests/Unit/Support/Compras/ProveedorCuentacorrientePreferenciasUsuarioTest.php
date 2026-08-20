<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProveedorCuentacorrientePreferenciasUsuarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_request_gana_sobre_cache_en_moneda(): void
    {
        ProveedorCuentacorrientePreferenciasUsuario::persistirMonedaId(1);

        $this->assertSame(
            2,
            ProveedorCuentacorrientePreferenciasUsuario::resolverMonedaId('2', true)
        );
    }

    public function test_sin_request_usa_cache(): void
    {
        ProveedorCuentacorrientePreferenciasUsuario::persistirMonedaId(2);

        $this->assertSame(
            2,
            ProveedorCuentacorrientePreferenciasUsuario::resolverMonedaId(null, false)
        );
    }

    public function test_default_es_todas(): void
    {
        $this->assertNull(ProveedorCuentacorrientePreferenciasUsuario::resolverMonedaId(null, false));
    }

    public function test_expresion_default_es_origen(): void
    {
        $this->assertSame('origen', ProveedorCuentacorrientePreferenciasUsuario::resolverExpresion(null, false));
    }

    public function test_request_expresion_gana_sobre_cache(): void
    {
        ProveedorCuentacorrientePreferenciasUsuario::persistirExpresion('pesos');

        $this->assertSame(
            'origen',
            ProveedorCuentacorrientePreferenciasUsuario::resolverExpresion('origen', true)
        );
    }

    public function test_sin_request_usa_cache_de_expresion(): void
    {
        ProveedorCuentacorrientePreferenciasUsuario::persistirExpresion('pesos');

        $this->assertSame(
            'pesos',
            ProveedorCuentacorrientePreferenciasUsuario::resolverExpresion(null, false)
        );
    }
}
