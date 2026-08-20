<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\FacturaelectronicaService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test puro (sin BD). ImpuestoService rotula la percepción con su tasa ("Percepcion IVA 3%"):
 * si no entra en arrayOtrosTributos, ARCA rechaza el comprobante por totales (validaciones 114-116).
 */
class FacturaelectronicaTributoPercepcionIvaTest extends TestCase
{
    public function test_la_percepcion_de_iva_con_tasa_entra_en_los_tributos(): void
    {
        [$tributos, $total] = $this->armaTributo([
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0, 'baseimponible' => 0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210.0, 'baseimponible' => 1000.0],
            ['concepto' => 'Percepcion IVA 3%', 'tasa' => 3, 'importe' => 30.0, 'baseimponible' => 1000.0],
        ]);

        self::assertCount(1, $tributos);
        self::assertSame(1, $tributos[0]['id']);
        self::assertSame(30.0, $tributos[0]['importe']);
        self::assertSame(30.0, $total);
    }

    public function test_sin_percepcion_no_agrega_tributos(): void
    {
        [$tributos, $total] = $this->armaTributo([
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0, 'baseimponible' => 0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 210.0, 'baseimponible' => 1000.0],
        ]);

        self::assertSame([], $tributos);
        self::assertSame(0, $total);
    }

    public function test_el_impuesto_interno_sigue_entrando(): void
    {
        [$tributos, $total] = $this->armaTributo([
            ['concepto' => 'Impuesto Interno', 'tasa' => 0, 'importe' => 12.5, 'baseimponible' => 0],
        ]);

        self::assertCount(1, $tributos);
        self::assertSame(4, $tributos[0]['id']);
        self::assertSame(12.5, $total);
    }

    /**
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @return array{0: list<array<string, mixed>>, 1: float|int}
     */
    private function armaTributo(array $conceptosTotales): array
    {
        $svc = (new ReflectionClass(FacturaelectronicaService::class))->newInstanceWithoutConstructor();

        $tributos = [];
        $total = 0;
        $svc->armaTributo($conceptosTotales, $tributos, $total);

        return [$tributos, $total];
    }
}
