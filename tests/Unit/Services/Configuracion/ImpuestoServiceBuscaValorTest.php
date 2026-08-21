<?php

namespace Tests\Unit\Services\Configuracion;

use App\Services\Configuracion\ImpuestoService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test puro (sin BD). buscaValor("Total") no debe sumar "Total Abasto" / "Total Logistica".
 */
class ImpuestoServiceBuscaValorTest extends TestCase
{
    public function test_total_no_suma_total_abasto(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 1000.0],
            ['concepto' => 'Iva 21%', 'importe' => 210.0],
            ['concepto' => 'Total Abasto', 'importe' => 50.0],
            ['concepto' => 'Total', 'importe' => 1260.0],
        ];

        self::assertSame(1260.0, $this->buscaValor($conceptos, 'Total'));
        self::assertSame(50.0, $this->buscaValor($conceptos, 'Total Abasto'));
    }

    public function test_iva_sigue_matcheando_por_prefijo(): void
    {
        $conceptos = [
            ['concepto' => 'Iva 21.000%', 'importe' => 210.0],
            ['concepto' => 'Iva 10.500%', 'importe' => 105.0],
        ];

        self::assertSame(315.0, $this->buscaValor($conceptos, 'Iva '));
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    private function buscaValor(array $conceptos, string $key): float
    {
        $svc = (new ReflectionClass(ImpuestoService::class))->newInstanceWithoutConstructor();

        return (float) $svc->buscaValor($conceptos, 'concepto', $key, 'importe');
    }
}
