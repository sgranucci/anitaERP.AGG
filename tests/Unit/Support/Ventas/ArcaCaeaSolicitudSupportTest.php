<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaCaeaSolicitudSupport;
use PHPUnit\Framework\TestCase;

class ArcaCaeaSolicitudSupportTest extends TestCase
{
    public function test_wsfe_debe_consultar_si_ya_otorgado(): void
    {
        self::assertTrue(ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud(
            'WSFE — FECAEASolicitar: [15008] Ya existe un CAEA otorgado',
            'wsfev1',
        ));
    }

    public function test_wsfe_debe_consultar_si_sin_caea_en_respuesta(): void
    {
        self::assertTrue(ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud(
            'WSFE — FECAEASolicitar sin CAEA en la respuesta.',
            'wsfev1',
        ));
    }

    public function test_mtxca_debe_consultar_codigo_604(): void
    {
        self::assertTrue(ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud(
            'MTXCA — solicitarCAEA: [604] CAEA ya otorgado',
            'wsmtxca',
        ));
    }

    public function test_mtxca_debe_consultar_si_ya_existe_caea_otorgado(): void
    {
        self::assertTrue(ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud(
            'MTXCA — solicitarCAEA: [604] Ya existe un CAEA otorgado para el período solicitado',
            'wsmtxca',
        ));
    }

    public function test_no_consultar_error_generico(): void
    {
        self::assertFalse(ArcaCaeaSolicitudSupport::debeConsultarTrasFalloSolicitud(
            'SOAP-ERROR: Could not connect to host',
            'wsfev1',
        ));
    }
}
