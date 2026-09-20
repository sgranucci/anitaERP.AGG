<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ReporteStockOtSituacionSupport;
use PHPUnit\Framework\TestCase;

class ReporteStockOtSituacionSupportTest extends TestCase
{
    public function test_es_tipoot_stock(): void
    {
        self::assertTrue(ReporteStockOtSituacionSupport::esTipootStock('S'));
        self::assertTrue(ReporteStockOtSituacionSupport::esTipootStock('s'));
        self::assertFalse(ReporteStockOtSituacionSupport::esTipootStock('N'));
        self::assertFalse(ReporteStockOtSituacionSupport::esTipootStock(''));
        self::assertFalse(ReporteStockOtSituacionSupport::esTipootStock(null));
    }

    public function test_ids_tareas_sin_avance_incluye_pendiente_y_cierres(): void
    {
        config([
            'consprod.TAREA_PENDIENTE_FABRICACION' => 31,
            'consprod.TAREA_TERMINADA' => 32,
            'consprod.TAREA_TERMINADA_STOCK' => 39,
            'consprod.TAREA_FACTURADA' => 33,
        ]);

        $ids = ReporteStockOtSituacionSupport::idsTareasSinAvanceFabricacion();
        self::assertContains(31, $ids);
        self::assertContains(32, $ids);
        self::assertContains(39, $ids);
        self::assertContains(33, $ids);
    }
}
