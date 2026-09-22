<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use PHPUnit\Framework\TestCase;

final class ComprobanteProveedorFlujoOcComFacSupportTest extends TestCase
{
    public function test_nc_no_exige_com_aunque_haya_recepcion_en_el_legajo(): void
    {
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            null,
            true,
            '2026-09-10',
            'NC'
        );

        $this->assertTrue($politica['sin_com_por_tipo']);
        $this->assertFalse($politica['debe_asignar_com']);
        $this->assertFalse($politica['bloquea_sin_com']);
        $this->assertSame(
            ComprobanteProveedorModoCarga::SIN_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica, ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
        );
    }

    public function test_nd_no_exige_com_aunque_haya_recepcion_en_el_legajo(): void
    {
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            null,
            true,
            '2026-09-10',
            'ND'
        );

        $this->assertTrue($politica['sin_com_por_tipo']);
        $this->assertFalse($politica['debe_asignar_com']);
        $this->assertFalse($politica['bloquea_sin_com']);
        $this->assertSame(
            ComprobanteProveedorModoCarga::SIN_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica, ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
        );
    }

    public function test_factura_sigue_pidiendo_com_si_hay_disponible(): void
    {
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            null,
            true,
            '2026-09-10',
            'FC'
        );

        $this->assertFalse($politica['sin_com_por_tipo']);
        $this->assertTrue($politica['debe_asignar_com']);
        $this->assertSame(
            ComprobanteProveedorModoCarga::ASIGNA_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica)
        );
    }

    public function test_badge_bandeja_anticipada_con_com_no_dice_contrato_sin_com(): void
    {
        $politica = [
            'es_anticipada' => true,
            'tiene_com' => true,
            'contrato_vigente' => false,
            'contrato_requiere_recepcion' => null,
        ];

        $this->assertSame(
            'FC + COM (opc.)',
            ComprobanteProveedorFlujoOcComFacSupport::etiquetaPaqueteOkBandeja($politica, false)
        );
        $this->assertStringContainsString(
            'anticipado',
            strtolower(ComprobanteProveedorFlujoOcComFacSupport::tituloPaqueteOkBandeja($politica, false))
        );
    }

    public function test_badge_bandeja_anticipada_sin_com(): void
    {
        $politica = [
            'es_anticipada' => true,
            'tiene_com' => false,
            'contrato_vigente' => false,
            'contrato_requiere_recepcion' => null,
        ];

        $this->assertSame(
            'FC anticipada',
            ComprobanteProveedorFlujoOcComFacSupport::etiquetaPaqueteOkBandeja($politica, false)
        );
    }

    public function test_badge_bandeja_contrato_sin_recepcion(): void
    {
        $politica = [
            'es_anticipada' => false,
            'tiene_com' => false,
            'contrato_vigente' => true,
            'contrato_requiere_recepcion' => false,
        ];

        $this->assertSame(
            'FC (contrato sin COM)',
            ComprobanteProveedorFlujoOcComFacSupport::etiquetaPaqueteOkBandeja($politica, false)
        );
    }

    public function test_badge_bandeja_exige_com(): void
    {
        $this->assertSame(
            'FC + COM',
            ComprobanteProveedorFlujoOcComFacSupport::etiquetaPaqueteOkBandeja([], true)
        );
    }

    public function test_modo_sugerido_anticipada_con_com_default_asigna_oc(): void
    {
        $politica = [
            'anticipada_elige_modo' => true,
            'es_anticipada' => true,
            'tiene_com' => true,
            'debe_asignar_com' => false,
            'permite_factura_anticipada' => false,
            'contrato_vigente' => false,
            'sin_com_por_tipo' => false,
        ];

        $this->assertSame(
            ComprobanteProveedorModoCarga::ASIGNA_OC,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica)
        );
        $this->assertSame(
            ComprobanteProveedorModoCarga::ASIGNA_RECEPCION,
            ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido(
                $politica,
                ComprobanteProveedorModoCarga::ASIGNA_RECEPCION
            )
        );
    }

    public function test_mensaje_bloquea_primera_anticipada(): void
    {
        $msg = ComprobanteProveedorFlujoOcComFacSupport::mensajeBloqueaComPrimeraAnticipada();
        $this->assertStringContainsString('primera factura', strtolower($msg));
        $this->assertStringContainsString('sin com', strtolower($msg));
    }
}
