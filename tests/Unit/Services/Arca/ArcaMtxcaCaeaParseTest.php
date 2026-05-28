<?php

namespace Tests\Unit\Services\Arca;

use App\Services\Arca\ArcaMtxcaFacturaElectronicaService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ArcaMtxcaCaeaParseTest extends TestCase
{
    public function test_parse_caea_result_con_errores_en_raiz_lanza_codigo_604(): void
    {
        $svc = $this->serviceSinConstructor();
        $result = (object) [
            'arrayErrores' => (object) [
                'codigoDescripcion' => (object) [
                    'codigo' => 604,
                    'descripcion' => 'Ya existe un CAEA otorgado para el período solicitado',
                ],
            ],
        ];

        $this->expectExceptionMessage('[604]');
        $this->invokeParseCaeaResult($svc, $result, 'solicitarCAEA');
    }

    public function test_parse_caea_entre_fechas_selecciona_quincena(): void
    {
        $svc = $this->serviceSinConstructor();
        $result = (object) [
            'arrayCAEAResponse' => (object) [
                'CAEAResponse' => (object) [
                    'CAEA' => 86217108690903,
                    'periodo' => 202606,
                    'orden' => 1,
                    'fechaDesde' => '2026-06-01',
                    'fechaHasta' => '2026-06-15',
                    'fechaTopeInforme' => '2026-06-20',
                    'fechaProceso' => '2026-05-27',
                ],
            ],
        ];

        $parsed = $this->invokeParseCaeaEntreFechasResult($svc, $result, 202606, 1);

        self::assertSame('86217108690903', $parsed['caea']);
        self::assertSame(202606, $parsed['periodo']);
        self::assertSame(1, $parsed['orden']);
        self::assertSame('2026-06-01', $parsed['fch_vig_desde']);
    }

    public function test_unwrap_soap_response_usa_propiedad_o_raiz(): void
    {
        $svc = $this->serviceSinConstructor();
        $inner = (object) ['CAEAResponse' => (object) ['CAEA' => 123]];
        $wrapped = (object) ['solicitarCAEAResponse' => $inner];
        $flat = (object) ['arrayErrores' => null, 'CAEAResponse' => $inner->CAEAResponse];

        self::assertSame($inner, $this->invokeUnwrap($svc, $wrapped, 'solicitarCAEAResponse'));
        self::assertSame($flat, $this->invokeUnwrap($svc, $flat, 'solicitarCAEAResponse'));
    }

    private function serviceSinConstructor(): ArcaMtxcaFacturaElectronicaService
    {
        $ref = new ReflectionClass(ArcaMtxcaFacturaElectronicaService::class);

        return $ref->newInstanceWithoutConstructor();
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeParseCaeaResult(ArcaMtxcaFacturaElectronicaService $svc, object $result, string $op): array
    {
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('parseCaeaResult');
        $m->setAccessible(true);

        return $m->invoke($svc, $result, $op);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeParseCaeaEntreFechasResult(
        ArcaMtxcaFacturaElectronicaService $svc,
        object $result,
        int $periodo,
        int $orden,
    ): array {
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('parseCaeaEntreFechasResult');
        $m->setAccessible(true);

        return $m->invoke($svc, $result, 'consultarCAEAEntreFechas', $periodo, $orden);
    }

    private function invokeUnwrap(
        ArcaMtxcaFacturaElectronicaService $svc,
        object $raw,
        string $prop,
    ): ?object {
        $ref = new ReflectionClass($svc);
        $m = $ref->getMethod('unwrapSoapResponse');
        $m->setAccessible(true);

        return $m->invoke($svc, $raw, $prop);
    }
}
