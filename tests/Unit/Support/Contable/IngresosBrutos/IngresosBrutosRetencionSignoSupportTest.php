<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\IngresosBrutos;

use App\Support\Contable\IngresosBrutos\IngresosBrutosRetencionSignoSupport;
use App\Support\Contable\Sicore\SicoreErpComplementoSupport;
use PHPUnit\Framework\TestCase;

class IngresosBrutosRetencionSignoSupportTest extends TestCase
{
    public function test_aop_y_nc_restan(): void
    {
        $this->assertTrue(IngresosBrutosRetencionSignoSupport::movimientoResta('AOP'));
        $this->assertTrue(IngresosBrutosRetencionSignoSupport::movimientoResta('NC'));
        $this->assertFalse(IngresosBrutosRetencionSignoSupport::movimientoResta('OPP'));
    }

    public function test_detalle_dev_ret_es_devolucion(): void
    {
        $this->assertTrue(IngresosBrutosRetencionSignoSupport::esDevolucionRetencionDetalle(
            'DEV RET OPP 125457: Orden de pago BANCO MACRO S.A.'
        ));
        $this->assertFalse(IngresosBrutosRetencionSignoSupport::esDevolucionRetencionDetalle(
            'Orden de pago Nro. 125457'
        ));
    }

    public function test_pago_dev_ret_por_detalle(): void
    {
        $this->assertTrue(IngresosBrutosRetencionSignoSupport::esDevolucionRetencionPago([
            'tipocomprobante' => 'OPP',
            'detalle' => 'DEV RET OPP 125457',
            'pagoproveedor_origen_id' => 28269,
        ]));
    }

    public function test_merge_erp_agrega_devolucion_ausente_en_anita(): void
    {
        $anita = [
            ['nro_comp' => 125457, 'nro_cert' => 53036, 'importe' => 21120.00],
        ];
        $erp = [
            ['nro_comp' => 125457, 'nro_cert' => 53036, 'importe' => 21120.00],
            ['nro_comp' => 125506, 'nro_cert' => 53036, 'importe' => -1920.00],
        ];

        $nuevos = SicoreErpComplementoSupport::soloNuevos($erp, $anita);

        $this->assertCount(1, $nuevos);
        $this->assertSame(125506, $nuevos[0]['nro_comp']);
        $this->assertSame(-1920.0, (float) $nuevos[0]['importe']);
    }
}
