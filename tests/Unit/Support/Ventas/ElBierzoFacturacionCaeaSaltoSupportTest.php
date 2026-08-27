<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ElBierzoFacturacionCaeaSaltoSupport as S;
use Tests\TestCase;

class ElBierzoFacturacionCaeaSaltoSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.empresa', 'EL BIERZO');
        config()->set('facturacion.SALTO_CAEA_ADMINISTRATIVA', true);
        config()->set('facturacion.SALTO_CAEA_MAPEO_CODIGOS', [
            '00010' => '00005',
        ]);
        config()->set('arca_mtxca.emision.forzar_modo_caea', false);
        config()->set('arca_mtxca.emision.reintentar_caea_si_falla_comunicacion', true);
        config()->set('arca_wsfe.emision.forzar_modo_caea', false);
        config()->set('arca_wsfe.emision.reintentar_caea_si_falla_comunicacion', true);
    }

    public function test_habilitado_solo_el_bierzo(): void
    {
        config()->set('app.empresa', 'EL BIERZO');
        self::assertTrue(S::habilitado());

        config()->set('app.empresa', 'AGG');
        self::assertFalse(S::habilitado());
    }

    public function test_no_reintenta_fuera_de_el_bierzo(): void
    {
        config()->set('app.empresa', 'AGG');
        $llamadas = 0;
        $out = S::ejecutarConReintento(
            ['puntoventa_id' => 8],
            function (array $d) use (&$llamadas) {
                $llamadas++;

                return ['error' => 'Connection timed out after 60001 milliseconds'];
            },
            fn (int $id) => [
                'cae_id' => 8,
                'caea_id' => 1,
                'webservice' => 'wsmtxca',
                'ya_caea' => false,
            ],
        );

        self::assertSame(1, $llamadas);
        self::assertSame('Connection timed out after 60001 milliseconds', $out['error']);
    }

    public function test_reintenta_en_pv_caea_si_arca_timeout(): void
    {
        $vistos = [];
        $out = S::ejecutarConReintento(
            ['puntoventa_id' => 8],
            function (array $d) use (&$vistos) {
                $vistos[] = (int) $d['puntoventa_id'];
                if ((int) $d['puntoventa_id'] === 8) {
                    return ['error' => 'Connection timed out after 60001 milliseconds'];
                }

                return ['factura' => 'FAC A-00005-00000044', 'error' => ''];
            },
            fn (int $id) => [
                'cae_id' => 8,
                'caea_id' => 1,
                'webservice' => 'wsmtxca',
                'ya_caea' => false,
            ],
        );

        self::assertSame([8, 1], $vistos);
        self::assertSame('FAC A-00005-00000044', $out['factura']);
        self::assertNotEmpty($out['aviso_caea'] ?? null);
        self::assertSame(1, $out['puntoventa_caea_id']);
    }

    public function test_no_reintenta_error_de_datos(): void
    {
        $llamadas = 0;
        $out = S::ejecutarConReintento(
            ['puntoventa_id' => 8],
            function () use (&$llamadas) {
                $llamadas++;

                return ['error' => 'WSFE — FECAESolicitar: 10015 Falta dato obligatorio: DocTipo'];
            },
            fn (int $id) => [
                'cae_id' => 8,
                'caea_id' => 1,
                'webservice' => 'wsmtxca',
                'ya_caea' => false,
            ],
        );

        self::assertSame(1, $llamadas);
        self::assertStringContainsString('10015', $out['error']);
    }

    public function test_si_ya_esta_en_caea_no_reintenta(): void
    {
        $llamadas = 0;
        S::ejecutarConReintento(
            ['puntoventa_id' => 1],
            function () use (&$llamadas) {
                $llamadas++;

                return ['error' => 'Connection timed out after 60001 milliseconds'];
            },
            fn (int $id) => [
                'cae_id' => 1,
                'caea_id' => 1,
                'webservice' => 'wsmtxca',
                'ya_caea' => true,
            ],
        );

        self::assertSame(1, $llamadas);
    }

    public function test_inyecta_timeout_pos_en_opciones_emision(): void
    {
        $visto = null;
        S::ejecutarConReintento(
            ['puntoventa_id' => 8],
            function (array $d) use (&$visto) {
                $visto = $d;

                return ['factura' => 'ok', 'error' => ''];
            },
            fn (int $id) => [
                'cae_id' => 8,
                'caea_id' => 1,
                'webservice' => 'wsmtxca',
                'ya_caea' => false,
            ],
        );

        self::assertTrue($visto['opciones_emision'][S::FLAG_TIMEOUT_POS] ?? false);
    }

    public function test_mapeo_codigo_pv10_a_pv5(): void
    {
        self::assertSame('00005', S::codigoCaeaParaCodigoCae('00010'));
        self::assertSame('00005', S::codigoCaeaParaCodigoCae('10'));
        self::assertNull(S::codigoCaeaParaCodigoCae('00015'));
    }
}
