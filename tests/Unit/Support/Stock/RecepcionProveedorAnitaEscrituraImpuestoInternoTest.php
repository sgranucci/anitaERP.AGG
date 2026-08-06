<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport as Esc;
use Tests\TestCase;

/**
 * Blinda la línea recepmov sintética del impuesto interno de cigarrillos (SKU IMPINTERNO)
 * que hace coincidir la suma de montos de recepmov con el asiento COM en Anita.
 */
class RecepcionProveedorAnitaEscrituraImpuestoInternoTest extends TestCase
{
    private const CLAVE = ['tipo' => 'COM', 'letra' => 'X', 'sucursal' => 1, 'nro' => 164899];

    public function test_linea_impuesto_interno_usa_cantidad_uno_y_precio_igual_al_importe(): void
    {
        $map = $this->mapa($this->construir(1091288.19, ordenMax: 1));

        $this->assertSame("'000IMPINTERNO'", $map['recv_articulo']);
        $this->assertSame('1.0000', $map['recv_cantidad']);
        $this->assertSame('1091288.1900', $map['recv_precio']);
        // Sin descuento, sin rechazo: el monto de la línea es exactamente el impuesto interno.
        $this->assertSame('0.0000', $map['recv_dto_art']);
        $this->assertSame('0.0000', $map['recv_cantrech']);
        $this->assertSame("''", $map['recv_motivo_rech']);
        $this->assertSame("'1'", $map['recv_cod_mon']);
    }

    public function test_orden_es_maximo_mas_uno(): void
    {
        $this->assertSame('2', $this->mapa($this->construir(100.0, ordenMax: 1))['recv_orden']);
        $this->assertSame('6', $this->mapa($this->construir(100.0, ordenMax: 5))['recv_orden']);
    }

    public function test_importe_redondea_a_dos_decimales(): void
    {
        $this->assertSame('1091288.1900', $this->mapa($this->construir(1091288.194, ordenMax: 1))['recv_precio']);
        $this->assertSame('1091288.2000', $this->mapa($this->construir(1091288.196, ordenMax: 1))['recv_precio']);
    }

    public function test_importe_no_positivo_devuelve_null(): void
    {
        $this->assertNull($this->construir(0.0, ordenMax: 1));
        $this->assertNull($this->construir(0.001, ordenMax: 1));
    }

    public function test_importe_negativo_usa_valor_absoluto_como_precio(): void
    {
        $map = $this->mapa($this->construir(-50.0, ordenMax: 1));
        $this->assertSame('1.0000', $map['recv_cantidad']);
        $this->assertSame('50.0000', $map['recv_precio']);
    }

    public function test_devolucion_usa_cantidad_negativa_y_precio_positivo(): void
    {
        $map = $this->mapa($this->construir(396367.89, ordenMax: 1, signoCantidad: -1.0));

        $this->assertSame('-1.0000', $map['recv_cantidad']);
        $this->assertSame('396367.8900', $map['recv_precio']);
    }

    /** @return array{campos: string, valores: string}|null */
    private function construir(float $importe, int $ordenMax, float $signoCantidad = 1.0): ?array
    {
        return Esc::recepmovImpuestoInternoInsert(
            '003793',
            self::CLAVE,
            $ordenMax,
            Esc::skuAnita13('IMPINTERNO'),
            'Impuestos internos',
            $importe,
            1861,
            20260707,
            '1',
            0,
            1,
            1430.0,
            '0',
            0,
            $signoCantidad,
        );
    }

    /**
     * @param  array{campos: string, valores: string}|null  $insert
     * @return array<string, string>
     */
    private function mapa(?array $insert): array
    {
        $this->assertNotNull($insert);

        return array_combine(
            explode(', ', $insert['campos']),
            explode(', ', $insert['valores']),
        );
    }
}
