<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use PHPUnit\Framework\TestCase;

final class ComprobanteProveedorAuditorAnticipoBsUsoCapexSupportTest extends TestCase
{
    public function test_ok_con_capex(): void
    {
        $eval = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::evaluar(1000.0, true, 10);
        $this->assertTrue($eval['ok']);
    }

    public function test_desvio_sin_capex(): void
    {
        $eval = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::evaluar(1000.0, false, 10);
        $this->assertFalse($eval['ok']);
        $this->assertStringContainsString('Capex', (string) $eval['motivo']);
    }

    public function test_desvio_sin_oc(): void
    {
        $eval = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::evaluar(1000.0, false, null);
        $this->assertFalse($eval['ok']);
        $this->assertStringContainsString('orden de compra', (string) $eval['motivo']);
    }

    public function test_ok_sin_monto(): void
    {
        $eval = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::evaluar(0.0, false, null);
        $this->assertTrue($eval['ok']);
    }

    public function test_apertura_solo_b(): void
    {
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esApertura('B', null, null, null, null));
        $this->assertFalse(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esApertura('B', 'FIS', null, null, null));
        $this->assertFalse(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esApertura('B FIS A 2 1415', null, null, null, null));
    }

    public function test_fuera_alcance_anita_import(): void
    {
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            ComprobanteProveedorOrigenEntrada::ANITA_IMPORT,
            null,
            null,
        ));
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            null,
            'subdiario',
            null,
        ));
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            null,
            null,
            '[SUBD] C COM X 1 161324',
        ));
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            null,
            null,
            'B FIS A 2 1415',
        ));
        $this->assertFalse(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            ComprobanteProveedorOrigenEntrada::MANUAL,
            null,
            'Comprobante proveedor FIB A-5-34343',
        ));
        // CP ERP gana aunque el asiento tenga anita_origen por sync.
        $this->assertFalse(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            ComprobanteProveedorOrigenEntrada::ORDENCOMPRA,
            'subdiario',
            '[SUBD] algo',
        ));
        // OPP/cuota Anita sin CP/COM.
        $this->assertTrue(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            null,
            null,
            'CUOTA OCTUBRE 2026',
            'OPP',
            '68663',
            false,
        ));
        // COM ERP con anita_tipo post-sync: no omitir solo por eso.
        $this->assertFalse(ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::esFueraAlcanceErp(
            null,
            null,
            'Recepción ERP',
            'COM',
            '999',
            true,
        ));
    }

    public function test_clasificar_omite_apertura_y_anita_alerta_erp(): void
    {
        $apertura = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
            100.0, false, null, true, false,
        );
        $this->assertTrue($apertura['omitido']);

        $anitaSinCapex = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
            100.0, false, 10, false, true,
        );
        $this->assertTrue($anitaSinCapex['omitido']);

        $anitaConCapex = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
            100.0, true, 10, false, true,
        );
        $this->assertTrue($anitaConCapex['ok']);

        $erpSinCapex = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
            100.0, false, 10, false, false,
        );
        $this->assertFalse($erpSinCapex['ok']);
        $this->assertFalse($erpSinCapex['omitido']);
        $this->assertSame(
            ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::TIPO_OC_SIN_CAPEX,
            $erpSinCapex['tipo'],
        );

        $erpSinOc = ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::clasificar(
            100.0, false, null, false, false,
        );
        $this->assertSame(
            ComprobanteProveedorAuditorAnticipoBsUsoCapexSupport::TIPO_SIN_OC,
            $erpSinOc['tipo'],
        );
    }
}
