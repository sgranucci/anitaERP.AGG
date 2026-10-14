<?php

namespace Tests\Unit\Support\Configuracion;

use App\Support\Configuracion\CorreoHtmlBase64;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

class CorreoHtmlBase64Test extends TestCase
{
    public function test_el_html_marcado_sale_en_base64_con_el_enlace_entero(): void
    {
        $url = 'http://10.20.30.210/anitaERP/public/arbolaprobacion/aprobar/SP/11560/'
            .rawurlencode('+2y+10+.+0GBuj.JNvlebgsuJsFHuY4HiKZyXjatvMix92MYVnz4SoT2V3m6');
        $html = '<html><body><a href="'.$url.'">Autorizar</a></body></html>';

        $email = (new Email())
            ->from('anita@example.com')
            ->to('firmante@example.com')
            ->subject('Aprobación')
            ->html($html);
        $email->getHeaders()->addTextHeader(CorreoHtmlBase64::HEADER, '1');
        $email->addPart(new DataPart('pdf', 'solicitud.pdf', 'application/pdf'));

        CorreoHtmlBase64::aplicarSiCorresponde($email);

        $cuerpo = $email->getBody();
        $this->assertInstanceOf(MixedPart::class, $cuerpo);
        $partes = $cuerpo->getParts();
        $this->assertInstanceOf(TextPart::class, $partes[0]);
        $this->assertSame($html, $partes[0]->getBody());
        $this->assertStringContainsString('solicitud.pdf', $partes[1]->getPreparedHeaders()->toString());

        $crudo = $email->toString();
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $crudo);
        $this->assertStringNotContainsString('Content-Transfer-Encoding: quoted-printable', $crudo);
        $this->assertStringNotContainsString($url, $crudo);
        $this->assertStringContainsString($url, $this->htmlDecodificado($crudo));
        $this->assertStringContainsString('solicitud.pdf', $crudo);
    }

    public function test_sin_la_marca_no_cambia_el_cuerpo(): void
    {
        $email = (new Email())
            ->from('anita@example.com')
            ->to('firmante@example.com')
            ->subject('Otro')
            ->html('<p>Hola</p>');

        CorreoHtmlBase64::aplicarSiCorresponde($email);

        $this->assertStringContainsString(
            'Content-Transfer-Encoding: quoted-printable',
            $email->toString()
        );
    }

    private function htmlDecodificado(string $crudo): string
    {
        $this->assertSame(1, preg_match(
            '/Content-Type: text\/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.+?)\r\n--/s',
            $crudo,
            $coincidencias
        ));

        $html = base64_decode(preg_replace('/\s+/', '', $coincidencias[1]) ?: '', true);
        $this->assertNotFalse($html);

        return $html;
    }
}
