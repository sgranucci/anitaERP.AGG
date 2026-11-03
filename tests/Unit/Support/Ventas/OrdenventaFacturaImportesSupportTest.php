<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\OrdenventaFacturaImportesSupport as S;
use Tests\TestCase;

class OrdenventaFacturaImportesSupportTest extends TestCase
{
    public function test_reemplaza_iva_y_percepciones_por_los_de_la_orden(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'importe' => 1000],
            ['concepto' => 'Gravado al 21%', 'importe' => 1000, 'tasa' => 21],
            ['concepto' => 'Iva 21%', 'importe' => 210, 'tasa' => 21, 'impuesto_id' => 3],
            ['concepto' => 'Perc. Buenos Aires 3%', 'importe' => 30, 'provincia_id' => 1],
            ['concepto' => 'Total', 'importe' => 1240],
        ];

        $out = S::aplicar($conceptos, 200, 50, 1, 3);

        self::assertSame(200.0, $this->importe($out, 'Iva 21%'));
        self::assertSame(50.0, $this->importe($out, 'Perc. Buenos Aires 3%'));
        self::assertSame(1250.0, $this->importe($out, 'Total'));
        self::assertSame(1000.0, $this->importe($out, 'Gravado al 21%'));
    }

    public function test_agrega_iva_y_percepcion_si_el_calculo_no_los_trajo(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 500],
            ['concepto' => 'Total', 'importe' => 500],
        ];

        $out = S::aplicar($conceptos, 105, 15, 4, 3);

        self::assertSame(105.0, $this->importe($out, 'Iva'));
        self::assertSame(15.0, $this->importe($out, 'Perc.'));
        self::assertSame(620.0, $this->importe($out, 'Total'));
    }

    public function test_en_cero_no_suma_el_impuesto_calculado(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21%', 'importe' => 800],
            ['concepto' => 'Iva 21%', 'importe' => 168, 'impuesto_id' => 3],
            ['concepto' => 'Total', 'importe' => 968],
        ];

        $out = S::aplicar($conceptos, 0, 0);

        self::assertSame(0.0, $this->importe($out, 'Iva 21%'));
        self::assertSame(800.0, $this->importe($out, 'Total'));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function importe(array $filas, string $concepto): float
    {
        foreach ($filas as $fila) {
            if (($fila['concepto'] ?? '') === $concepto) {
                return (float) $fila['importe'];
            }
        }

        return 0.0;
    }
}
