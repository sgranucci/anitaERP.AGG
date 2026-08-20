<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ClienteCuentacorrientePreferenciasUsuario;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClienteCuentacorrientePreferenciasUsuarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_request_gana_sobre_cache_en_moneda(): void
    {
        ClienteCuentacorrientePreferenciasUsuario::persistirMonedaId(1);

        $this->assertSame(
            2,
            ClienteCuentacorrientePreferenciasUsuario::resolverMonedaId('2', true)
        );
    }

    public function test_sin_request_usa_cache(): void
    {
        ClienteCuentacorrientePreferenciasUsuario::persistirMonedaId(2);

        $this->assertSame(
            2,
            ClienteCuentacorrientePreferenciasUsuario::resolverMonedaId(null, false)
        );
    }

    public function test_default_es_todas(): void
    {
        $this->assertNull(ClienteCuentacorrientePreferenciasUsuario::resolverMonedaId(null, false));
    }

    public function test_request_todas_limpia_filtro(): void
    {
        ClienteCuentacorrientePreferenciasUsuario::persistirMonedaId(2);

        $this->assertNull(ClienteCuentacorrientePreferenciasUsuario::resolverMonedaId('todas', true));
    }

    public function test_expresion_default_es_origen(): void
    {
        $this->assertSame('origen', ClienteCuentacorrientePreferenciasUsuario::resolverExpresion(null, false));
    }

    public function test_request_expresion_gana_sobre_cache(): void
    {
        ClienteCuentacorrientePreferenciasUsuario::persistirExpresion('pesos');

        $this->assertSame(
            'origen',
            ClienteCuentacorrientePreferenciasUsuario::resolverExpresion('origen', true)
        );
    }

    public function test_sin_request_usa_cache_de_expresion(): void
    {
        ClienteCuentacorrientePreferenciasUsuario::persistirExpresion('pesos');

        $this->assertSame(
            'pesos',
            ClienteCuentacorrientePreferenciasUsuario::resolverExpresion(null, false)
        );
    }
}
