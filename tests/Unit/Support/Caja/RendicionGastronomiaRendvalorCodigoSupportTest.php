<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Support\Caja\AnitaSync\RendicionGastronomiaRendvalorCodigoSupport;
use Tests\TestCase;

class RendicionGastronomiaRendvalorCodigoSupportTest extends TestCase
{
    public function test_detecta_familia_medio_cobro(): void
    {
        $mp = new Cuentacaja(['nombre' => 'Mercado pago', 'codigo' => 'GMEP']);
        $this->assertSame(
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_MERCADOPAGO,
            RendicionGastronomiaRendvalorCodigoSupport::familiaDesdeCuentacaja($mp),
        );

        $fiserv = new Cuentacaja(['nombre' => 'MEDIO FISERV', 'codigo' => 'X']);
        $this->assertSame(
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_FISERV,
            RendicionGastronomiaRendvalorCodigoSupport::familiaDesdeCuentacaja($fiserv),
        );

        $ctg = new Cuentacaja(['nombre' => 'Canje Tarjetas Gastro (CAJA)', 'codigo' => 'CTG']);
        $this->assertSame(
            RendicionGastronomiaRendvalorCodigoSupport::FAMILIA_CANJE_TARJETA,
            RendicionGastronomiaRendvalorCodigoSupport::familiaDesdeCuentacaja($ctg),
        );
    }

    public function test_codigo_canje_tarjeta_por_empresa(): void
    {
        $ctg = new Cuentacaja(['nombre' => 'Canje Tarjetas Gastro (CAJA)', 'codigo' => 'CTG']);

        $this->assertSame(15, RendicionGastronomiaRendvalorCodigoSupport::codigoDesdeCuentacaja(1, $ctg));
        $this->assertSame(67, RendicionGastronomiaRendvalorCodigoSupport::codigoDesdeCuentacaja(2, $ctg));
        $this->assertSame(97, RendicionGastronomiaRendvalorCodigoSupport::codigoDesdeCuentacaja(3, $ctg));
    }
}
