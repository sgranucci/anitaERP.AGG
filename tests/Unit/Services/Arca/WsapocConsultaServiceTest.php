<?php

namespace Tests\Unit\Services\Arca;

use App\Services\Arca\WsapocConsultaService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WsapocConsultaServiceTest extends TestCase
{
    public function test_gateway_html_de_arca_se_detecta_como_html_vacio(): void
    {
        $method = new ReflectionMethod(WsapocConsultaService::class, 'esRespuestaHtmlOVacia');
        $method->setAccessible(true);
        $svc = (new \ReflectionClass(WsapocConsultaService::class))->newInstanceWithoutConstructor();

        $html = '<html><head><title></title>10114288348689193919</head><body><br><br></body></html>';
        $this->assertTrue($method->invoke($svc, $html));
        $this->assertTrue($method->invoke($svc, ''));
        $this->assertFalse($method->invoke($svc, '<?xml version="1.0"?><soap:Envelope></soap:Envelope>'));
    }
}
