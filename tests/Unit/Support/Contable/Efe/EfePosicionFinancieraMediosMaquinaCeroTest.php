<?php

namespace Tests\Unit\Support\Contable\Efe;

use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use ReflectionMethod;
use Tests\TestCase;

class EfePosicionFinancieraMediosMaquinaCeroTest extends TestCase
{
    public function test_asegura_medios_sin_movimiento_por_etiqueta_cuentacaja(): void
    {
        $support = $this->supportConDias([1, 2]);
        $medios = [
            'Efectivo pesos' => [1 => 100.0, 2 => 0.0],
            'Totalcoin QR Máquina' => [1 => 50.0, 2 => 0.0],
        ];
        $etiquetas = [
            'Efectivo pesos' => true,
            'Efectivo dólares' => true,
            'Efectivo euros' => true,
            'Totalcoin QR Máquina' => true,
            'Totalcoin QR Caja' => true,
            'Depósito efectivo pago QR' => true,
            'Transferencia / Check MS' => true,
        ];

        $out = $this->asegurar($support, $medios, $etiquetas);

        $this->assertArrayHasKey('Efectivo dólares', $out);
        $this->assertArrayHasKey('Efectivo euros', $out);
        $this->assertArrayHasKey('Totalcoin QR Caja', $out);
        $this->assertArrayHasKey('Depósito efectivo pago QR', $out);
        $this->assertArrayHasKey('Transferencia / Check MS', $out);
        $this->assertSame(0.0, array_sum($out['Efectivo dólares']));
        $this->assertSame(100.0, $out['Efectivo pesos'][1]);
    }

    public function test_no_duplica_ni_pisa_montos_existentes(): void
    {
        $support = $this->supportConDias([1]);
        $medios = [
            'Totalcoin QR Caja' => [1 => 840000.0],
        ];

        $out = $this->asegurar($support, $medios, ['Totalcoin QR Caja' => true]);

        $this->assertSame(840000.0, $out['Totalcoin QR Caja'][1]);
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
     * @param  array<string, true>  $etiquetas
     * @return array<string, array<int, float>>
     */
    private function asegurar(
        EfePosicionFinancieraSupport $support,
        array $medios,
        array $etiquetas,
    ): array {
        $method = new ReflectionMethod($support, 'asegurarMediosMaquinaVisibles');
        $method->setAccessible(true);

        return $method->invoke($support, $medios, $etiquetas);
    }
}
