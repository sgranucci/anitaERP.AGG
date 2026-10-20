<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoComprobanteIvaExcelSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoComprobanteIvaExcelSupportTest extends TestCase
{
    public function test_vincula_por_cuenta_contable_y_toma_el_importe_en_absoluto(): void
    {
        $fila = (new IngresoEgresoComprobanteIvaExcelSupport())->analizarCeldas(
            ['33%', '214010-005', 'IMP A LOS CREDITOS BANCO', null, -5079310.67, 0],
            $this->indice()
        );

        $this->assertNotNull($fila);
        $this->assertSame('ambos', $fila['detectado']);
        $this->assertSame(10, $fila['concepto_id']);
        $this->assertSame(5079310.67, $fila['importe']);
        $this->assertSame(-5079310.67, $fila['importe_original']);
    }

    public function test_si_la_cuenta_y_el_codigo_difieren_propone_la_cuenta(): void
    {
        $fila = (new IngresoEgresoComprobanteIvaExcelSupport())->analizarCeldas(
            ['7', '532050-001', 'COMISIONES Y GTOS BANCARIOS', null, -100.5, 0],
            $this->indice()
        );

        $this->assertSame('ambiguo', $fila['detectado']);
        $this->assertSame(20, $fila['concepto_id']);
        $this->assertCount(2, $fila['alternativas']);
    }

    public function test_omite_encabezado_y_total_sin_cuenta(): void
    {
        $support = new IngresoEgresoComprobanteIvaExcelSupport();

        $this->assertNull($support->analizarCeldas(
            [null, null, null, 'Acumulado Hoy', 'Acumulado Hoy', 'Ajuste diario'],
            $this->indice()
        ));
        $this->assertNull($support->analizarCeldas(
            [null, null, null, 0, -50391623.95, 0],
            $this->indice()
        ));
    }

    public function test_codigo_de_concepto_cuando_no_hay_cuenta(): void
    {
        $fila = (new IngresoEgresoComprobanteIvaExcelSupport())->analizarCeldas(
            ['5', 'IVA C. FISCAL INDIRECTO', -82.5],
            $this->indice()
        );

        $this->assertSame('codigo_concepto', $fila['detectado']);
        $this->assertSame(30, $fila['concepto_id']);
        $this->assertSame(82.5, $fila['importe']);
    }

    /**
     * @return array<string, mixed>
     */
    private function indice(): array
    {
        $creditos = [
            'id' => 10,
            'codigo' => '33%',
            'nombre' => 'IMP A LOS CREDITOS BANCO',
            'tipoconcepto' => 'I',
            'cuenta_debe_id' => 1,
            'cuenta_debe_codigo' => '214010-005',
            'cuenta_debe_nombre' => 'IMP A LOS CREDITOS BANCO',
            'via' => 'cuenta_contable',
        ];
        $comisiones = [
            'id' => 20,
            'codigo' => '6',
            'nombre' => 'COMISIONES Y GTOS BANCARIOS',
            'tipoconcepto' => 'E',
            'cuenta_debe_id' => 2,
            'cuenta_debe_codigo' => '532050-001',
            'cuenta_debe_nombre' => 'COMISIONES Y GTOS BANCARIOS',
            'via' => 'cuenta_contable',
        ];
        $iva = [
            'id' => 30,
            'codigo' => '5',
            'nombre' => 'IVA C. FISCAL INDIRECTO',
            'tipoconcepto' => 'I',
            'cuenta_debe_id' => 3,
            'cuenta_debe_codigo' => '114010-007',
            'cuenta_debe_nombre' => 'IVA C. FISCAL INDIRECTO',
            'via' => 'codigo_concepto',
        ];

        return [
            'cuentas' => [
                '214010-005' => ['id' => 1, 'codigo' => '214010-005', 'nombre' => 'IMP A LOS CREDITOS BANCO'],
                '532050-001' => ['id' => 2, 'codigo' => '532050-001', 'nombre' => 'COMISIONES Y GTOS BANCARIOS'],
            ],
            'conceptos_por_cuenta' => [
                1 => [$creditos],
                2 => [$comisiones],
            ],
            'conceptos_por_codigo' => [
                '33%' => [$creditos],
                '7' => [[
                    'id' => 40,
                    'codigo' => '7',
                    'nombre' => 'OTRO CONCEPTO',
                    'tipoconcepto' => 'P',
                    'cuenta_debe_id' => 9,
                    'cuenta_debe_codigo' => '',
                    'cuenta_debe_nombre' => '',
                    'via' => 'codigo_concepto',
                ]],
                '5' => [$iva],
            ],
        ];
    }
}
