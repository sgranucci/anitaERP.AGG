<?php

namespace Tests\Unit\Support\Contable\Efe;

use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use ReflectionMethod;
use Tests\TestCase;

class EfePosicionFinancieraPremiosEnEfectivoTest extends TestCase
{
    public function test_inserta_premios_en_efectivo_antes_de_municipalidad(): void
    {
        $support = $this->supportConDias([1, 2, 3]);
        $premios = [
            'Bingo 47%' => [1 => -100.0, 2 => 0.0, 3 => 0.0],
            'Premio 5% Pozo Ac.' => [1 => -10.0, 2 => 0.0, 3 => 0.0],
            'Municipalidad 4%' => [1 => -8.0, 2 => 0.0, 3 => 0.0],
            'Loteria 17%' => [1 => -34.0, 2 => 0.0, 3 => 0.0],
        ];
        $bingo = ['VENTA BINGO' => [1 => 200.0, 2 => 0.0, 3 => 0.0]];

        $out = $this->asegurar($support, $premios, $bingo);
        $claves = array_keys($out);

        $this->assertContains('Premios en efectivo', $claves);
        $this->assertSame(
            ['Bingo 47%', 'Premio 5% Pozo Ac.', 'Premios en efectivo', 'Municipalidad 4%', 'Loteria 17%'],
            $claves,
        );
        $this->assertSame(0.0, array_sum($out['Premios en efectivo']));
    }

    public function test_no_duplica_si_ya_existe(): void
    {
        $support = $this->supportConDias([1]);
        $premios = [
            'Premios en efectivo' => [1 => -50.0],
            'Municipalidad 4%' => [1 => -8.0],
        ];
        $bingo = ['VENTA BINGO' => [1 => 200.0]];

        $out = $this->asegurar($support, $premios, $bingo);

        $this->assertCount(1, array_filter(
            array_keys($out),
            static fn (string $k): bool => $k === 'Premios en efectivo',
        ));
        $this->assertSame(-50.0, $out['Premios en efectivo'][1]);
    }

    public function test_sin_bingo_no_agrega_fila(): void
    {
        $support = $this->supportConDias([1]);
        $out = $this->asegurar($support, [], ['VENTA BINGO' => [1 => 0.0]]);

        $this->assertArrayNotHasKey('Premios en efectivo', $out);
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
     * @param  array<string, array<int, float>>  $premios
     * @param  array<string, array<int, float>>  $bingo
     * @return array<string, array<int, float>>
     */
    private function asegurar(EfePosicionFinancieraSupport $support, array $premios, array $bingo): array
    {
        $method = new ReflectionMethod($support, 'asegurarPremiosEnEfectivoVisible');
        $method->setAccessible(true);

        return $method->invoke($support, $premios, $bingo);
    }
}
