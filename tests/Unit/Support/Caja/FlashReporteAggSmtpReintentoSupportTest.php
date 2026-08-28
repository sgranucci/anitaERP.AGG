<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashReporteAggSmtpReintentoSupport;
use Tests\TestCase;

class FlashReporteAggSmtpReintentoSupportTest extends TestCase
{
    public function test_detecta_corte_de_office365(): void
    {
        $msg = 'Connection could not be established with host "smtp.office365.com:587": '
            .'stream_socket_client(): Unable to connect to smtp.office365.com:587 (Network is unreachable)';

        $this->assertTrue(FlashReporteAggSmtpReintentoSupport::esErrorTransporte($msg));
        $this->assertStringContainsString(
            'Se reintentará automáticamente',
            FlashReporteAggSmtpReintentoSupport::mensajeConAvisoReintento($msg)
        );
    }

    public function test_no_reintenta_autenticacion(): void
    {
        $this->assertFalse(FlashReporteAggSmtpReintentoSupport::esErrorTransporte(
            'Failed to authenticate on SMTP server with username "x" using 2 possible authenticators. Authenticator LOGIN returned Expected response code 235 but got code "535".'
        ));
    }
}
