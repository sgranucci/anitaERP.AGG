<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\LogisticaBierzoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). Contrasta a-comprob.c calcula():
 * tot_logistica = (tot_grav + _tot_grav_otasa) * clim_logistica / 100
 */
final class LogisticaBierzoSupportTest extends TestCase
{
    public function test_uno_punto_cinco_sobre_gravado_total_no_ultimo_renglon(): void
    {
        $netos = [
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 3263620.47],
        ];

        $gravado = LogisticaBierzoSupport::gravadoDesdeNetos($netos);

        $this->assertSame(3263620.47, $gravado);
        $this->assertSame(48954.31, LogisticaBierzoSupport::importe($gravado, 1.5));
    }

    public function test_ultimo_renglon_no_es_la_base(): void
    {
        $this->assertSame(1339.27, LogisticaBierzoSupport::importe(89284.99, 1.5));
        $this->assertSame(48954.31, LogisticaBierzoSupport::importe(3263620.47, 1.5));
    }

    public function test_incluye_gravado_otra_tasa_y_excluye_exento(): void
    {
        $netos = [
            ['concepto' => 'Exento', 'tasa' => 0, 'importe' => 1000.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 800.0],
            ['concepto' => 'Gravado al 10.5%', 'tasa' => 10.5, 'importe' => 200.0],
        ];

        $this->assertSame(1000.0, LogisticaBierzoSupport::gravadoDesdeNetos($netos));
        $this->assertSame(15.0, LogisticaBierzoSupport::importe(1000.0, 1.5));
    }

    public function test_no_suma_la_propia_logistica(): void
    {
        $netos = [
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Total Logistica', 'tasa' => 21, 'importe' => 15.0],
        ];

        $this->assertSame(1000.0, LogisticaBierzoSupport::gravadoDesdeNetos($netos));
    }

    public function test_cero_si_no_hay_porcentaje_ni_gravado(): void
    {
        $this->assertSame(0.0, LogisticaBierzoSupport::importe(1000.0, 0));
        $this->assertSame(0.0, LogisticaBierzoSupport::importe(0, 1.5));
    }

    public function test_preparar_netos_pone_logistica_antes_del_gravado_sin_tasa(): void
    {
        $netos = [
            ['concepto' => 'Exento', 'tasa' => 0, 'importe' => 10.0],
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Total Logistica', 'tasa' => 21, 'importe' => 15.0],
        ];

        $ordenado = LogisticaBierzoSupport::prepararNetosParaImpresion($netos);

        $this->assertSame('Exento', $ordenado[0]['concepto']);
        $this->assertSame('Total Logistica', $ordenado[1]['concepto']);
        $this->assertSame(0, $ordenado[1]['tasa']);
        $this->assertSame(15.0, $ordenado[1]['importe']);
        $this->assertSame('Gravado al 21%', $ordenado[2]['concepto']);
        $this->assertSame(21, $ordenado[2]['tasa']);
    }

    public function test_preparar_conceptos_totales_fusiona_iva_duplicado_de_facturas_viejas(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'tasa' => 0, 'importe' => 6361755.0, 'baseimponible' => 0],
            ['concepto' => 'Gravado al 21.000%', 'tasa' => 21, 'importe' => 6361755.0, 'baseimponible' => 0],
            ['concepto' => 'Total Logistica', 'tasa' => 21, 'importe' => 95426.33, 'baseimponible' => 0],
            ['concepto' => 'Iva 21.000%', 'tasa' => 21, 'importe' => 1335968.55, 'baseimponible' => 6361755.0],
            ['concepto' => 'Iva 21%', 'tasa' => 21, 'importe' => 20039.53, 'baseimponible' => 95426.33],
            ['concepto' => 'Percepcion IVA 3%', 'tasa' => 3, 'importe' => 193715.44, 'baseimponible' => 0],
            ['concepto' => 'Total', 'tasa' => 0, 'importe' => 8039190.76, 'baseimponible' => 0],
        ];

        $ordenado = LogisticaBierzoSupport::prepararConceptosTotalesParaImpresion($conceptos);

        $this->assertSame('Subtotal', $ordenado[0]['concepto']);
        $this->assertSame('Total Logistica', $ordenado[1]['concepto']);
        $this->assertSame(0, $ordenado[1]['tasa']);
        $this->assertSame('Gravado al 21.000%', $ordenado[2]['concepto']);
        // Gravado impreso = mercadería + logística (base del IVA), no el Subtotal otra vez.
        $this->assertSame(6457181.33, $ordenado[2]['importe']);
        $this->assertSame('Iva 21.000%', $ordenado[3]['concepto']);
        $this->assertSame(1356008.08, $ordenado[3]['importe']);
        $this->assertSame(6457181.33, $ordenado[3]['baseimponible']);
        $this->assertSame('Percepcion IVA 3%', $ordenado[4]['concepto']);
        $this->assertCount(6, $ordenado);
    }

    public function test_preparar_netos_no_suma_logistica_al_gravado_para_grabar(): void
    {
        $netos = [
            ['concepto' => 'Gravado al 21%', 'tasa' => 21, 'importe' => 1000.0],
            ['concepto' => 'Total Logistica', 'tasa' => 21, 'importe' => 15.0],
        ];

        $ordenado = LogisticaBierzoSupport::prepararNetosParaImpresion($netos);

        $this->assertSame('Total Logistica', $ordenado[0]['concepto']);
        $this->assertSame('Gravado al 21%', $ordenado[1]['concepto']);
        $this->assertSame(1000.0, $ordenado[1]['importe']);
    }
}
