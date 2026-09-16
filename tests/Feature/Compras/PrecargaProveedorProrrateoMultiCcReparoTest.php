<?php

namespace Tests\Feature\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport;
use Tests\TestCase;

/**
 * Apertura de conceptos en tipos prorrateados (FPB/…): el reparo sólo completa lo que falta
 * para llegar al total y nunca descarta ni reclasifica líneas que ya cuadran.
 */
class PrecargaProveedorProrrateoMultiCcReparoTest extends TestCase
{
    /** @var list<string> */
    private array $tiposOrigen = ['FGA', 'FIB', 'FNB'];

    private PrecargaProveedorProrrateoMultiCcSupport $support;

    protected function setUp(): void
    {
        parent::setUp();

        // Los conceptos y tipos son datos maestros: el test corre contra una base real.
        try {
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin conexión a base de datos: '.$e->getMessage());
        }

        foreach ($this->tiposOrigen as $abreviatura) {
            if (! Tipotransaccion_Compra::where('abreviatura', $abreviatura)->exists()) {
                $this->markTestSkipped('Falta el tipo maestro '.$abreviatura.'.');
            }
        }

        $this->support = app(PrecargaProveedorProrrateoMultiCcSupport::class);
    }

    public function test_no_toca_factura_exenta_cuyos_conceptos_ya_cierran_el_total(): void
    {
        $exento = $this->conceptoPorCodigo(3);
        $percepcion = $this->conceptoPorCodigo(140);

        $lineas = [
            ['concepto_ivacompra_id' => $exento->id, 'codigo_concepto_anita' => 3, 'monto' => 3822275.67],
            ['concepto_ivacompra_id' => $percepcion->id, 'codigo_concepto_anita' => 140, 'monto' => 11466.83],
        ];

        $resultado = $this->support->repararAperturaSiFaltaGi(
            $lineas,
            $this->tiposOrigen,
            3822275.67,
            3833742.50,
        );

        $this->assertFalse($resultado['reparo']);
        $this->assertSame($lineas, $resultado['lineas']);
    }

    public function test_reclasifica_exento_a_gravado_solo_si_la_diferencia_es_el_iva(): void
    {
        $exento = $this->conceptoPorCodigo(3);

        $resultado = $this->support->repararAperturaSiFaltaGi(
            [['concepto_ivacompra_id' => $exento->id, 'codigo_concepto_anita' => 3, 'monto' => 1000000.0]],
            $this->tiposOrigen,
            1000000.0,
            1210000.0,
        );

        $this->assertTrue($resultado['reparo']);
        $this->assertCount(2, $resultado['lineas']);
        $this->assertSame(
            [1000000.0, 210000.0],
            array_map(static fn (array $l): float => (float) $l['monto'], $resultado['lineas'])
        );
        $this->assertSame(
            ['G', 'I'],
            array_map(fn (array $l): string => $this->tipoConcepto((int) $l['concepto_ivacompra_id']), $resultado['lineas'])
        );
    }

    public function test_agrega_gravado_cuando_el_agente_solo_envio_percepciones(): void
    {
        $percepcion = $this->conceptoPorCodigo(140);

        $resultado = $this->support->repararAperturaSiFaltaGi(
            [['concepto_ivacompra_id' => $percepcion->id, 'codigo_concepto_anita' => 140, 'monto' => 57491.84]],
            $this->tiposOrigen,
            19163946.33,
            19221438.17,
        );

        $this->assertTrue($resultado['reparo']);
        $this->assertCount(2, $resultado['lineas']);
        $this->assertSame('G', $this->tipoConcepto((int) $resultado['lineas'][1]['concepto_ivacompra_id']));
        $this->assertSame(19163946.33, (float) $resultado['lineas'][1]['monto']);
    }

    public function test_no_inventa_iva_cuando_el_gravado_ya_cierra_el_total(): void
    {
        $gravado = $this->conceptoPorCodigo(50);

        $lineas = [['concepto_ivacompra_id' => $gravado->id, 'codigo_concepto_anita' => 50, 'monto' => 14170380.75]];

        $resultado = $this->support->repararAperturaSiFaltaGi(
            $lineas,
            $this->tiposOrigen,
            14170380.75,
            14170380.75,
        );

        $this->assertFalse($resultado['reparo']);
        $this->assertSame($lineas, $resultado['lineas']);
    }

    public function test_prorrateo_de_iva_no_mezcla_gravados_de_distinta_alicuota(): void
    {
        $gravado21 = $this->conceptoPorCodigo(50);
        $gravado105 = $this->conceptoPorCodigo(6);
        $iva21 = $this->conceptoPorCodigo(311);

        $lineas = $this->support->prorratearLineasIva(
            [
                ['concepto_ivacompra_id' => $gravado21->id, 'codigo_concepto_anita' => 50, 'monto' => 1000000.0],
                ['concepto_ivacompra_id' => $gravado105->id, 'codigo_concepto_anita' => 6, 'monto' => 500000.0],
                ['concepto_ivacompra_id' => $iva21->id, 'codigo_concepto_anita' => 311, 'monto' => 210000.0],
            ],
            ['FGA' => 1000.0, 'FIB' => 2000.0, 'FNB' => 5000.0],
            $this->tiposOrigen,
        );

        $gravados = array_values(array_filter(
            $lineas,
            fn (array $l): bool => $this->tipoConcepto((int) $l['concepto_ivacompra_id']) === 'G'
        ));
        $this->assertCount(2, $gravados, 'Los gravados 21% y 10,5% deben quedar en líneas separadas.');

        $ivas = array_values(array_filter(
            $lineas,
            fn (array $l): bool => $this->tipoConcepto((int) $l['concepto_ivacompra_id']) === 'I'
        ));
        $this->assertCount(3, $ivas, 'El IVA se reparte entre los tres finos origen.');
        $this->assertSame(210000.0, round(array_sum(array_map(
            static fn (array $l): float => (float) $l['monto'],
            $ivas
        )), 2));
    }

    private function conceptoPorCodigo(int $codigo): Concepto_Ivacompra
    {
        $concepto = Concepto_Ivacompra::where('codigo', $codigo)->first();
        if (! $concepto) {
            $this->markTestSkipped('Falta el concepto IVA compra con código '.$codigo.'.');
        }

        return $concepto;
    }

    private function tipoConcepto(int $conceptoId): string
    {
        return strtoupper(trim((string) (Concepto_Ivacompra::find($conceptoId)?->tipoconcepto ?? '')));
    }
}
