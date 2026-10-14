<?php

namespace Tests\Unit\Support\Queue;

use App\Support\Queue\CorreoFallidoReintentoSupport;
use PHPUnit\Framework\TestCase;

class CorreoFallidoReintentoSupportTest extends TestCase
{
    public function test_reencola_un_mail_que_no_pudo_conectar_al_smtp(): void
    {
        $decision = CorreoFallidoReintentoSupport::decision(
            $this->payloadMail(),
            'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host "smtp.office365.com:587": stream_socket_client(): Unable to connect to smtp.office365.com:587 (La red es inaccesible)',
            24,
        );

        $this->assertSame(CorreoFallidoReintentoSupport::REENCOLAR, $decision);
    }

    public function test_no_reencola_un_rechazo_permanente_ni_un_job_que_no_es_correo(): void
    {
        $permanente = CorreoFallidoReintentoSupport::decision(
            $this->payloadMail(),
            'Symfony\Component\Mailer\Exception\UnexpectedResponseException: Expected response code "250" but got code "550".',
            24,
        );
        $waitry = CorreoFallidoReintentoSupport::decision(
            json_encode(['data' => ['commandName' => 'App\\Jobs\\EnviarWaitryComandaJob']], JSON_THROW_ON_ERROR),
            'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established',
            24,
        );

        $this->assertSame(CorreoFallidoReintentoSupport::OMITIR, $permanente);
        $this->assertSame(CorreoFallidoReintentoSupport::OMITIR, $waitry);
    }

    public function test_frena_cuando_el_correo_ya_llego_al_tope(): void
    {
        $payload = json_decode($this->payloadMail(), true, 512, JSON_THROW_ON_ERROR);
        $payload[CorreoFallidoReintentoSupport::CLAVE_REINTENTOS] = 24;

        $decision = CorreoFallidoReintentoSupport::decision(
            json_encode($payload, JSON_THROW_ON_ERROR),
            'Symfony\Component\Mailer\Exception\TransportException: php_network_getaddresses: getaddrinfo for smtp.office365.com failed: Fallo temporal en la resolución del nombre',
            24,
        );

        $this->assertSame(CorreoFallidoReintentoSupport::TOPE, $decision);
    }

    private function payloadMail(): string
    {
        return json_encode([
            'displayName' => 'App\\Mail\\Configuracion\\ModuloAvisoMail',
            'data' => ['commandName' => 'Illuminate\\Mail\\SendQueuedMailable'],
        ], JSON_THROW_ON_ERROR);
    }
}
