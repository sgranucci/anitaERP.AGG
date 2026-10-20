<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Sicore;

use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use PHPUnit\Framework\TestCase;

class SicoreMayorComparableSupportTest extends TestCase
{
    public function test_opp_y_chp_son_tipos_de_devolucion_de_retencion(): void
    {
        $this->assertTrue(SicoreMayorComparableSupport::esTipoDevolucionRetencion('CHP'));
        $this->assertTrue(SicoreMayorComparableSupport::esTipoDevolucionRetencion('OPP'));
        $this->assertTrue(SicoreMayorComparableSupport::esTipoDevolucionCheque('OPP'));
        $this->assertFalse(SicoreMayorComparableSupport::esTipoDevolucionRetencion('AOP'));
        $this->assertFalse(SicoreMayorComparableSupport::esTipoDevolucionRetencion('OPA'));
    }

    public function test_opp_de_devolucion_con_emisor_proveedor_es_comparable(): void
    {
        $emisor = SicoreMayorComparableSupport::normalizarEmisor('5212');
        $emisores = [$emisor => true];
        $mov = [
            'subd_tipo' => 'OPP',
            'subd_emisor' => '5212',
            'detalle' => 'Orden de pago BANCO MACRO S.A.',
        ];

        $this->assertTrue(SicoreMayorComparableSupport::esLineaPagoProveedor($mov, $emisores));
        $this->assertTrue(SicoreMayorComparableSupport::esTipoComparableRetencion('OPP'));
    }

    public function test_excluye_pago_arba_aunque_sea_opp(): void
    {
        $emisor = SicoreMayorComparableSupport::normalizarEmisor('5212');
        $particion = SicoreMayorComparableSupport::particionar([
            [
                'detalle' => 'RETENCIONES IIBB ARBA 1Q 10.26',
                'subd_tipo' => 'OPP',
                'subd_emisor' => '5212',
                'importe' => 1000.0,
            ],
        ], [$emisor => true]);

        $this->assertCount(0, $particion['comparables']);
        $this->assertCount(1, $particion['excluidos']);
        $this->assertSame('pago_arba', $particion['excluidos'][0]['motivo_exclusion']);
    }
}
