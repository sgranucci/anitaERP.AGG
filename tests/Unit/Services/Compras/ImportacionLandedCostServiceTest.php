<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Compras;

use App\Services\Compras\ImportacionLandedCostService;
use App\Support\Compras\ImportacionGastoTratamiento;
use PHPUnit\Framework\TestCase;

final class ImportacionLandedCostServiceTest extends TestCase
{
    public function test_prorrateo_por_valor(): void
    {
        $svc = new ImportacionLandedCostService();
        $partidas = [
            ['sku' => 'A', 'valor' => 100.0, 'cantidad' => 10],
            ['sku' => 'B', 'valor' => 300.0, 'cantidad' => 5],
        ];
        $out = $svc->prorratear($partidas, 40.0, ImportacionGastoTratamiento::BASE_VALOR);

        $this->assertCount(2, $out);
        $this->assertEquals(10.0, $out[0]['importe']);
        $this->assertEquals(30.0, $out[1]['importe']);
    }

    public function test_prorrateo_igual(): void
    {
        $svc = new ImportacionLandedCostService();
        $partidas = [
            ['sku' => 'A', 'valor' => 1],
            ['sku' => 'B', 'valor' => 1],
            ['sku' => 'C', 'valor' => 1],
        ];
        $out = $svc->prorratear($partidas, 90.0, ImportacionGastoTratamiento::BASE_IGUAL);
        $this->assertCount(3, $out);
        $this->assertEqualsWithDelta(30.0, $out[0]['importe'], 0.01);
        $this->assertEqualsWithDelta(30.0, $out[1]['importe'], 0.01);
        $this->assertEqualsWithDelta(30.0, $out[2]['importe'], 0.01);
    }

    public function test_simular_landed(): void
    {
        $svc = new ImportacionLandedCostService();
        $r = $svc->simular(
            1000.0,
            [['valor' => 1000.0, 'cantidad' => 1]],
            [
                ['importe' => 100.0, 'base_prorrateo' => 'valor', 'inventariable' => true],
                ['importe' => 50.0, 'base_prorrateo' => 'igual', 'inventariable' => false],
            ]
        );
        $this->assertEquals(1100.0, $r['landed']);
        $this->assertEqualsWithDelta(0.15, $r['coeficiente'], 0.0001);
    }
}
