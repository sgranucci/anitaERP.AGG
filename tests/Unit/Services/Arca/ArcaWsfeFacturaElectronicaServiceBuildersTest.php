<?php

namespace Tests\Unit\Services\Arca;

use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Services\Arca\WsaaService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Fija la forma canónica de los builders Iva / Tributos / CbtesAsoc que
 * espera el SoapClient nativo de PHP a partir del WSDL WSFEv1
 * (tns:ArrayOfAlicIva, tns:ArrayOfTributo, tns:ArrayOfCbteAsoc).
 *
 * Regresión real (2026-05-26): la forma envuelta
 *   [['AlicIva' => {...}], ['AlicIva' => {...}]]
 * provocaba "FECAESolicitar: SOAP-ERROR: Encoding: object has no 'Id'
 * property" porque PHP encodea cada entrada externa como tns:AlicIva.
 */
class ArcaWsfeFacturaElectronicaServiceBuildersTest extends TestCase
{
    private ArcaWsfeFacturaElectronicaService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $wsaa = $this->createMock(WsaaService::class);
        $condicionivaRepo = $this->createMock(CondicionivaRepositoryInterface::class);
        $this->svc = new ArcaWsfeFacturaElectronicaService($wsaa, $condicionivaRepo);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod(ArcaWsfeFacturaElectronicaService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->svc, ...$args);
    }

    public function test_build_iva_multiples_alicuotas_devuelve_shape_arrayofaliciva(): void
    {
        $out = $this->invoke('buildIva', [
            ['id' => 5, 'base_imp' => 100.0, 'importe' => 21.0],
            ['id' => 4, 'base_imp' => 50.0, 'importe' => 5.25],
        ]);

        self::assertIsArray($out);
        self::assertArrayHasKey('AlicIva', $out);
        self::assertCount(2, $out['AlicIva']);

        self::assertSame(5, $out['AlicIva'][0]['Id']);
        self::assertEqualsWithDelta(100.0, (float) $out['AlicIva'][0]['BaseImp'], 0.0001);
        self::assertEqualsWithDelta(21.0, (float) $out['AlicIva'][0]['Importe'], 0.0001);

        self::assertSame(4, $out['AlicIva'][1]['Id']);
        self::assertEqualsWithDelta(50.0, (float) $out['AlicIva'][1]['BaseImp'], 0.0001);
        self::assertEqualsWithDelta(5.25, (float) $out['AlicIva'][1]['Importe'], 0.0001);
    }

    public function test_build_iva_unica_alicuota_devuelve_lista_de_uno(): void
    {
        $out = $this->invoke('buildIva', [
            ['id' => 5, 'base_imp' => 100.0, 'importe' => 21.0],
        ]);

        self::assertSame(['AlicIva'], array_keys($out));
        self::assertCount(1, $out['AlicIva']);
        self::assertSame(['Id', 'BaseImp', 'Importe'], array_keys($out['AlicIva'][0]));
    }

    public function test_build_iva_descarta_lineas_con_importe_cero(): void
    {
        $out = $this->invoke('buildIva', [
            ['id' => 5, 'base_imp' => 100.0, 'importe' => 0.0],
        ]);

        self::assertNull($out);
    }

    public function test_build_iva_lista_vacia_devuelve_null(): void
    {
        self::assertNull($this->invoke('buildIva', []));
    }

    public function test_build_tributos_devuelve_shape_arrayoftributo(): void
    {
        $out = $this->invoke('buildTributos', [
            [
                'id' => 99,
                'desc' => 'Otro',
                'base_imp' => 1000.0,
                'alicuota' => 5.0,
                'importe' => 50.0,
            ],
        ]);

        self::assertSame(['Tributo'], array_keys($out));
        self::assertCount(1, $out['Tributo']);
        self::assertSame(99, $out['Tributo'][0]['Id']);
        self::assertSame('Otro', $out['Tributo'][0]['Desc']);
        self::assertEqualsWithDelta(1000.0, (float) $out['Tributo'][0]['BaseImp'], 0.0001);
        self::assertEqualsWithDelta(5.0, (float) $out['Tributo'][0]['Alic'], 0.0001);
        self::assertEqualsWithDelta(50.0, (float) $out['Tributo'][0]['Importe'], 0.0001);
    }

    public function test_build_tributos_descarta_importe_cero(): void
    {
        self::assertNull($this->invoke('buildTributos', [
            ['id' => 99, 'desc' => 'X', 'base_imp' => 100.0, 'alicuota' => 0.0, 'importe' => 0.0],
        ]));
    }

    public function test_build_cbtes_asoc_devuelve_shape_arrayofcbteasoc(): void
    {
        $out = $this->invoke('buildCbtesAsoc', [
            ['tipo' => 6, 'ptovta' => 3, 'nro' => 100],
            ['tipo' => 6, 'ptovta' => 3, 'nro' => 101],
        ]);

        self::assertSame(['CbteAsoc'], array_keys($out));
        self::assertCount(2, $out['CbteAsoc']);
        self::assertSame(6, $out['CbteAsoc'][0]['Tipo']);
        self::assertSame(3, $out['CbteAsoc'][0]['PtoVta']);
        self::assertSame(100, $out['CbteAsoc'][0]['Nro']);
        self::assertSame(101, $out['CbteAsoc'][1]['Nro']);
    }

    public function test_build_cbtes_asoc_lista_vacia_devuelve_null(): void
    {
        self::assertNull($this->invoke('buildCbtesAsoc', []));
    }

    public function test_build_fe_caea_det_request_incluye_cbte_fch_hs_gen(): void
    {
        $out = $this->invoke('buildFeCaeaDetRequest', 6, 3, [
            'fechacomprobante' => '20260801',
            'cbte_fch_hs_gen' => '20260801143522',
            'tipodoc' => 96,
            'numerodocumento' => '30111222',
            'numerocomprobante' => 1,
            'total' => 121,
            'nogravado' => 0,
            'gravado' => 100,
            'exento' => 0,
            'tributo' => 0,
            'iva' => 21,
            'moneda' => 'PES',
            'cotizacion' => 1,
            'condicioniva_id' => 1,
            'impuestos' => [],
            'tributos' => [],
            'comprobantesasociados' => [],
        ], '12345678901234', 0);

        self::assertArrayHasKey('CbteFchHsGen', $out);
        self::assertSame('20260801143522', $out['CbteFchHsGen']);
        self::assertSame('12345678901234', $out['CAEA']);
    }

    public function test_build_fe_caea_det_request_exige_cbte_fch_hs_gen_o_fecha(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CbteFchHsGen');

        $this->invoke('buildFeCaeaDetRequest', 6, 3, [
            'fechacomprobante' => '',
            'tipodoc' => 96,
            'numerodocumento' => '30111222',
            'numerocomprobante' => 1,
            'total' => 100,
            'nogravado' => 0,
            'gravado' => 100,
            'exento' => 0,
            'tributo' => 0,
            'iva' => 0,
            'moneda' => 'PES',
            'cotizacion' => 1,
            'condicioniva_id' => 1,
        ], '1', 0);
    }

    /**
     * Guarda explícita contra la forma envuelta histórica
     * [['AlicIva' => {...}], ['AlicIva' => {...}]] que rompe el SoapClient nativo.
     */
    public function test_build_iva_nunca_devuelve_forma_envuelta_legacy(): void
    {
        $out = $this->invoke('buildIva', [
            ['id' => 5, 'base_imp' => 100.0, 'importe' => 21.0],
            ['id' => 4, 'base_imp' => 50.0, 'importe' => 5.25],
        ]);

        self::assertIsArray($out);
        self::assertArrayNotHasKey(0, $out, 'No debe ser lista indexada (forma envuelta).');
        self::assertArrayHasKey('AlicIva', $out);
        foreach ($out['AlicIva'] as $item) {
            self::assertArrayHasKey('Id', $item);
            self::assertArrayNotHasKey('AlicIva', $item, 'No debe re-envolver AlicIva dentro de cada item.');
        }
    }
}
