<?php

namespace Tests\Unit\Support\Contable\Efe;

use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use ReflectionMethod;
use Tests\TestCase;

class EfePosicionFinancieraMediosMaquinaCeroTest extends TestCase
{
    public function test_asegura_medios_sin_movimiento_con_etiqueta_valormae(): void
    {
        $support = $this->supportConDias([1, 2]);
        $medios = [
            'Efectivo pesos' => [1 => 100.0, 2 => 0.0],
            'TotalCoin QR Maquina' => [1 => 50.0, 2 => 0.0],
        ];
        $valormae = [
            81 => ['desc' => 'Efectivo pesos', 'tipo' => '0'],
            84 => ['desc' => 'TotalCoin QR Maquina', 'tipo' => '9'],
            85 => ['desc' => 'TotalCoin QR Caja', 'tipo' => '9'],
            99 => ['desc' => 'Transf.Check MS', 'tipo' => '7'],
            100 => ['desc' => 'DEPOSITO EFECTIVO PAGO QR', 'tipo' => '7'],
        ];
        $codigos = [
            81 => true,
            84 => true,
            85 => true,
            99 => true,
            100 => true,
        ];

        $out = $this->asegurar($support, $medios, $valormae, $codigos);

        $this->assertArrayHasKey('TotalCoin QR Caja', $out);
        $this->assertArrayHasKey('DEPOSITO EFECTIVO PAGO QR', $out);
        $this->assertArrayHasKey('Transf.Check MS', $out);
        $this->assertSame(0.0, array_sum($out['TotalCoin QR Caja']));
        $this->assertSame(0.0, array_sum($out['DEPOSITO EFECTIVO PAGO QR']));
        $this->assertSame(0.0, array_sum($out['Transf.Check MS']));
        $this->assertSame(100.0, $out['Efectivo pesos'][1]);
    }

    public function test_no_duplica_ni_pisa_montos_existentes(): void
    {
        $support = $this->supportConDias([1]);
        $medios = [
            'TotalCoin QR Caja' => [1 => 840000.0],
        ];
        $valormae = [
            85 => ['desc' => 'TotalCoin QR Caja', 'tipo' => '9'],
        ];

        $out = $this->asegurar($support, $medios, $valormae, [85 => true]);

        $this->assertSame(840000.0, $out['TotalCoin QR Caja'][1]);
    }

    /**
     * @param  list<int>  $dias
     */
    private function supportConDias(array $dias): EfePosicionFinancieraSupport
    {
        $support = new EfePosicionFinancieraSupport;
        $prop = new \ReflectionProperty($support, 'dias');
        $prop->setAccessible(true);
        $prop->setValue($support, $dias);

        return $support;
    }

    /**
     * @param  array<string, array<int, float>>  $medios
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigos
     * @return array<string, array<int, float>>
     */
    private function asegurar(
        EfePosicionFinancieraSupport $support,
        array $medios,
        array $valormae,
        array $codigos,
    ): array {
        $method = new ReflectionMethod($support, 'asegurarMediosMaquinaVisibles');
        $method->setAccessible(true);

        return $method->invoke($support, $medios, $valormae, $codigos);
    }
}
