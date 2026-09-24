<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\FerliExcelStockImportPlanner;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): identificadores de filas EN PRODUCCION protegidos del CONOT.
 */
class FerliExcelStockImportPlannerProtegeEnProduccionTest extends TestCase
{
    public function test_identificadores_numericos_extrae_lote_y_varios(): void
    {
        self::assertSame([30796], FerliExcelStockImportPlanner::identificadoresNumericosFila([
            'identificador' => '30796',
        ]));
        self::assertSame([30796, 12], FerliExcelStockImportPlanner::identificadoresNumericosFila([
            'identificador' => '30796 / 12',
        ]));
        self::assertSame([], FerliExcelStockImportPlanner::identificadoresNumericosFila([
            'identificador' => '',
        ]));
    }
}
