<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaDetalleFerliSupport;
use PHPUnit\Framework\TestCase;

class TransferenciaMercaderiaDetalleFerliCantidadTest extends TestCase
{
    public function test_suma_cantidad_desde_medidas_json(): void
    {
        $json = json_encode([
            ['medida' => '37', 'cantidad' => 1, 'talle_id' => 22],
            ['medida' => '38', 'cantidad' => 2, 'talle_id' => 23],
            ['medida' => '39', 'cantidad' => 1, 'talle_id' => 24],
        ], JSON_THROW_ON_ERROR);

        $this->assertEqualsWithDelta(
            4.0,
            TransferenciaMercaderiaDetalleFerliSupport::sumaCantidadDesdeMedidas($json),
            0.000001
        );
    }

    public function test_cantidad_linea_preferida_usa_talles(): void
    {
        $det = [
            'medidas' => [
                ['medida' => '37', 'cantidad' => 1.0],
                ['medida' => '38', 'cantidad' => 2.0],
                ['medida' => '39', 'cantidad' => 1.0],
            ],
        ];
        $this->assertEqualsWithDelta(
            4.0,
            TransferenciaMercaderiaDetalleFerliSupport::cantidadLineaPreferida(3.0, $det),
            0.000001
        );
        $this->assertEqualsWithDelta(
            3.0,
            TransferenciaMercaderiaDetalleFerliSupport::cantidadLineaPreferida(3.0, null),
            0.000001
        );
    }
}
