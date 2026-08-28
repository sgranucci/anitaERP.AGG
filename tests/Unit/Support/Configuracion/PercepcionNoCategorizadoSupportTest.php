<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): rotulado y detección del concepto RG 2126.
 */
class PercepcionNoCategorizadoSupportTest extends TestCase
{
    public function test_el_concepto_incluye_la_tasa(): void
    {
        self::assertSame(
            'Percepcion no categorizado 10.5%',
            PercepcionNoCategorizadoSupport::concepto(10.5),
        );
        self::assertSame(
            'Percepcion no categorizado 5.25%',
            PercepcionNoCategorizadoSupport::concepto(5.25),
        );
    }

    public function test_detecta_el_concepto_sin_confundirlo_con_perc_iva(): void
    {
        self::assertTrue(PercepcionNoCategorizadoSupport::esConcepto('Percepcion no categorizado 10.5%'));
        self::assertTrue(PercepcionNoCategorizadoSupport::esConcepto('Perc. no categorizado'));
        self::assertFalse(PercepcionNoCategorizadoSupport::esConcepto('Percepcion IVA 3%'));
        self::assertFalse(PercepcionNoCategorizadoSupport::esConcepto('Perc. IIBB CABA'));
    }

    public function test_suma_solo_el_concepto_de_no_categorizado(): void
    {
        $importe = PercepcionNoCategorizadoSupport::importeDesdeConceptos([
            ['concepto' => 'Iva 21%', 'importe' => 210],
            ['concepto' => 'Percepcion IVA 3%', 'importe' => 30],
            ['concepto' => 'Percepcion no categorizado 10.5%', 'importe' => 3783.07],
        ]);

        self::assertSame(3783.07, $importe);
    }

    public function test_corresponde_por_codigo_afip_7(): void
    {
        $noCateg = (object) ['id' => 6, 'codigoexterno' => '7', 'letra' => 'B'];
        $monotributo = (object) ['id' => 4, 'codigoexterno' => '6', 'letra' => 'A'];
        $cf = (object) ['id' => 3, 'codigoexterno' => '5', 'letra' => 'B'];

        self::assertTrue(PercepcionNoCategorizadoSupport::corresponde($noCateg));
        self::assertFalse(PercepcionNoCategorizadoSupport::corresponde($monotributo));
        self::assertFalse(PercepcionNoCategorizadoSupport::corresponde($cf));
    }

    public function test_mostrador_letra_b_aplica_pnc_salvo_omitir_forzado(): void
    {
        $noCateg = (object) ['id' => 6, 'codigoexterno' => '7', 'letra' => 'B'];
        $cf = (object) ['id' => 3, 'codigoexterno' => '5', 'letra' => 'B'];

        self::assertTrue(PercepcionNoCategorizadoSupport::aplicarAunqueSeOmitanOtras(false, $noCateg));
        self::assertFalse(PercepcionNoCategorizadoSupport::aplicarAunqueSeOmitanOtras(true, $noCateg));
        self::assertFalse(PercepcionNoCategorizadoSupport::aplicarAunqueSeOmitanOtras(false, $cf));
    }

    public function test_la_base_legal_es_gravado_mas_iva(): void
    {
        $totFact = round(33287.89 + 6990.46, 2);
        self::assertSame(40278.35, $totFact);
        self::assertSame(4229.23, round($totFact * 10.5 / 100, 2));
    }
}
