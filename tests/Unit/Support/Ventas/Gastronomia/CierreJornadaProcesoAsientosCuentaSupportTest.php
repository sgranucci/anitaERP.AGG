<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Contable\Cuentacontable;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosCuentaSupport;
use Tests\TestCase;

final class CierreJornadaProcesoAsientosCuentaSupportTest extends TestCase
{
    public function test_remap_211010017_a_vales_gastronomia_por_empresa(): void
    {
        $legacy = Cuentacontable::query()
            ->where('codigo', CierreJornadaProcesoAsientosCuentaSupport::CODIGO_CUENTA_MEDIO_LEGACY)
            ->where('empresa_id', 1)
            ->first(['id']);

        $vales = Cuentacontable::query()
            ->where('codigo', CierreJornadaProcesoAsientosCuentaSupport::CODIGO_CUENTA_VALES_GASTRONOMIA)
            ->where('empresa_id', 1)
            ->first(['id']);

        if ($legacy === null || $vales === null) {
            $this->markTestSkipped('Sin cuentas 211010017/211010020 para empresa 1.');
        }

        $remap = CierreJornadaProcesoAsientosCuentaSupport::aplicarRemapCuentacontableMedioCobro(
            (int) $legacy->id,
            1,
            116,
        );

        $this->assertSame((int) $vales->id, $remap);
    }

    public function test_no_remap_canje_estacionamiento(): void
    {
        $legacy = Cuentacontable::query()
            ->where('codigo', CierreJornadaProcesoAsientosCuentaSupport::CODIGO_CUENTA_MEDIO_LEGACY)
            ->where('empresa_id', 1)
            ->value('id');

        if ($legacy === null) {
            $this->markTestSkipped('Sin cuenta 211010017 para empresa 1.');
        }

        $remap = CierreJornadaProcesoAsientosCuentaSupport::aplicarRemapCuentacontableMedioCobro(
            (int) $legacy,
            1,
            200,
        );

        $this->assertSame((int) $legacy, $remap);
    }

    public function test_etiqueta_refleja_cuenta_vales(): void
    {
        $legacy = Cuentacontable::query()
            ->where('codigo', CierreJornadaProcesoAsientosCuentaSupport::CODIGO_CUENTA_MEDIO_LEGACY)
            ->where('empresa_id', 1)
            ->first(['id']);

        if ($legacy === null) {
            $this->markTestSkipped('Sin cuenta 211010017 para empresa 1.');
        }

        $etiq = CierreJornadaProcesoAsientosCuentaSupport::etiquetaCuentacontableMedioCobro(
            (int) $legacy->id,
            1,
            116,
        );

        $this->assertNotNull($etiq);
        $this->assertSame(
            CierreJornadaProcesoAsientosCuentaSupport::CODIGO_CUENTA_VALES_GASTRONOMIA,
            $etiq['codigo'],
        );
        $this->assertStringContainsStringIgnoringCase('vales', $etiq['nombre']);
    }
}
