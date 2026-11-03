<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownImpuestoConceptos;
use PHPUnit\Framework\TestCase;

final class TransferenciaCrownImpuestoConceptosTest extends TestCase
{
    public function test_el_neto_va_en_gravado_y_el_iva_en_su_linea(): void
    {
        $filas = TransferenciaCrownImpuestoConceptos::filas([
            'gravado21' => 41280.99,
            'iva21' => 8669.01,
            'total' => 49950,
        ]);

        $gravado = $this->linea($filas, 'Gravado al 21.000%');
        $iva = $this->linea($filas, 'Iva 21.000%');
        $total = $this->linea($filas, 'Total');

        $this->assertSame(41280.99, $gravado['importe']);
        $this->assertSame(41280.99, $gravado['base']);
        $this->assertSame(8669.01, $iva['importe']);
        $this->assertSame(41280.99, $iva['base']);
        $this->assertSame(49950.0, $total['importe']);
        $this->assertSame(41280.99 + 8669.01, $gravado['importe'] + $iva['importe']);
    }

    public function test_factura_b_de_consumidor_final_cierra_con_el_total(): void
    {
        $filas = TransferenciaCrownImpuestoConceptos::filas([
            'gravado21' => 7438.02,
            'iva21' => 1561.98,
            'total' => 9000,
        ]);

        $gravado = $this->linea($filas, 'Gravado al 21.000%');
        $iva = $this->linea($filas, 'Iva 21.000%');

        $this->assertSame(7438.02, $gravado['importe']);
        $this->assertSame(1561.98, $iva['importe']);
        $this->assertSame(9000.0, $gravado['importe'] + $iva['importe']);
    }

    public function test_el_exento_viaja_en_el_importe(): void
    {
        $filas = TransferenciaCrownImpuestoConceptos::filas([
            'exento' => 1500,
            'total' => 1500,
        ]);

        $exento = $this->linea($filas, 'Exento');
        $this->assertSame(1500.0, $exento['importe']);
        $this->assertNull($this->buscar($filas, 'Gravado al 21.000%'));
    }

    public function test_la_cortesia_no_queda_como_neto_gravado(): void
    {
        $filas = TransferenciaCrownImpuestoConceptos::filas([
            'gravado21' => 0.01,
            'iva21' => 0,
            'total' => 0.01,
        ]);

        $this->assertSame(0.01, $this->linea($filas, 'Exento')['importe']);
        $this->assertNull($this->buscar($filas, 'Gravado al 21.000%'));
        $this->assertNull($this->buscar($filas, 'Iva 21.000%'));
    }

    /**
     * @param  list<array{concepto: string, tasa: float, base: float, importe: float}>  $filas
     * @return array{concepto: string, tasa: float, base: float, importe: float}
     */
    private function linea(array $filas, string $concepto): array
    {
        $linea = $this->buscar($filas, $concepto);
        $this->assertNotNull($linea, $concepto);

        return $linea;
    }

    /**
     * @param  list<array{concepto: string, tasa: float, base: float, importe: float}>  $filas
     * @return array{concepto: string, tasa: float, base: float, importe: float}|null
     */
    private function buscar(array $filas, string $concepto): ?array
    {
        foreach ($filas as $fila) {
            if ($fila['concepto'] === $concepto) {
                return $fila;
            }
        }

        return null;
    }
}
