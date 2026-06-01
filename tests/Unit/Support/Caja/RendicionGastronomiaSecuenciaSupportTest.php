<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\RendicionGastronomiaSecuenciaSupport;
use PHPUnit\Framework\TestCase;

class RendicionGastronomiaSecuenciaSupportTest extends TestCase
{
    public function test_siguiente_desde_anita_cuando_es_mayor(): void
    {
        $r = RendicionGastronomiaSecuenciaSupport::calcularSiguiente(50, 30);

        $this->assertSame(51, $r['siguiente']);
        $this->assertSame(RendicionGastronomiaSecuenciaSupport::FUENTE_COMBINADO, $r['fuente']);
    }

    public function test_siguiente_desde_erp_cuando_anita_vacio(): void
    {
        $r = RendicionGastronomiaSecuenciaSupport::calcularSiguiente(0, 12);

        $this->assertSame(13, $r['siguiente']);
        $this->assertSame(RendicionGastronomiaSecuenciaSupport::FUENTE_ERP, $r['fuente']);
    }

    public function test_extrae_nro_oper_numerico(): void
    {
        $this->assertSame(42, RendicionGastronomiaSecuenciaSupport::extraerNroOperDesdeCodigo('42'));
        $this->assertNull(RendicionGastronomiaSecuenciaSupport::extraerNroOperDesdeCodigo('RG-42'));
    }
}
