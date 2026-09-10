<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaFceNcMostradorSupport;
use Tests\TestCase;

final class ArcaFceNcMostradorSupportTest extends TestCase
{
    public function test_tope_mypime_solo_para_emitir_fce_no_bloquea_asoc_nce(): void
    {
        config(['facturacion.LIMITE_FCE' => 1_000_000]);
        \App\Support\Configuracion\ParametroSistemaSupport::olvidarCache();

        self::assertFalse(ArcaFceNcMostradorSupport::correspondeEmitirNcNdFce(500.0));
        self::assertTrue(ArcaFceNcMostradorSupport::correspondeEmitirNcNdFce(1_000_000.0));
    }

    public function test_nce_203_asocia_fce_con_cuit(): void
    {
        $empresa = (object) ['nroinscripcion' => '30-71234567-8'];
        $asocs = ArcaFceNcMostradorSupport::asociadosParaArca(
            [['tipo' => 201, 'ptovta' => 10, 'nro' => 9]],
            203,
            $empresa,
            null
        );
        self::assertCount(1, $asocs);
        self::assertSame(201, $asocs[0]['tipo']);
        self::assertSame(10, $asocs[0]['ptovta']);
        self::assertSame(9, $asocs[0]['nro']);
        self::assertSame('30712345678', $asocs[0]['cuit']);
    }

    public function test_nc_003_no_asocia_fce(): void
    {
        $empresa = (object) ['nroinscripcion' => '30-71234567-8'];
        $asocs = ArcaFceNcMostradorSupport::asociadosParaArca(
            [['tipo' => 201, 'ptovta' => 10, 'nro' => 9]],
            3,
            $empresa,
            null
        );
        self::assertSame([], $asocs);
    }

    public function test_nc_003_asocia_fac_sin_cuit(): void
    {
        $empresa = (object) ['nroinscripcion' => '30-71234567-8'];
        $asocs = ArcaFceNcMostradorSupport::asociadosParaArca(
            [['tipo' => 1, 'ptovta' => 10, 'nro' => 9]],
            3,
            $empresa,
            null
        );
        self::assertCount(1, $asocs);
        self::assertSame(1, $asocs[0]['tipo']);
        self::assertArrayNotHasKey('cuit', $asocs[0]);
    }

    public function test_nce_exige_asociacion_fce_nc_no(): void
    {
        self::assertTrue(ArcaFceNcMostradorSupport::exigeAsociacionFce(203));
        self::assertTrue(ArcaFceNcMostradorSupport::exigeAsociacionFce(202));
        self::assertFalse(ArcaFceNcMostradorSupport::exigeAsociacionFce(3));
        self::assertFalse(ArcaFceNcMostradorSupport::exigeAsociacionFce(8));
        self::assertSame(203, ArcaFceNcMostradorSupport::codigoAfipDesdeTipo((object) ['codigo' => '203']));
    }
}
