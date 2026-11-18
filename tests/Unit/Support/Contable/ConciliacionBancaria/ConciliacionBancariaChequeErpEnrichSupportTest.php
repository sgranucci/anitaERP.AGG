<?php

namespace Tests\Unit\Support\Contable\ConciliacionBancaria;

use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaChequeErpEnrichSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaSinConciliarReporteSupport;
use Tests\TestCase;

class ConciliacionBancariaChequeErpEnrichSupportTest extends TestCase
{
    public function test_reporte_completa_detalle_y_op_desde_cheque_erp(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cheque')) {
            $this->markTestSkipped('Sin tabla cheque');
        }

        $existe = \Illuminate\Support\Facades\DB::table('cheque')
            ->where('numerocheque', '82048436')
            ->where('empresa_id', 1)
            ->where('cuentacaja_id', 52)
            ->exists();
        if (! $existe) {
            $this->markTestSkipped('Cheque 82048436 no está en este entorno');
        }

        $resultado = [
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 52, 'codigo' => '127', 'nombre' => 'TEST'],
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [[
                'tip' => 'CHP',
                'numero_cheque' => '82048436',
                'fecha_emision' => '2026-11-05',
                'fecha_cheque' => '2026-11-13',
                'fecha_entrega' => null,
                'importe' => 43543.86,
                'entregado_a' => '',
                'proveedor_codigo' => '',
                'nro_op' => '',
                'origen_json' => ['semilla' => true],
            ]],
        ];

        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar($resultado);
        $this->assertCount(1, $reporte['filas']);
        $fila = $reporte['filas'][0];
        $this->assertSame('CHP', $fila['tip']);
        $this->assertSame('82048436', $fila['numero']);
        $this->assertSame('SUSFERRIN SRL', $fila['detalle']);
        $this->assertSame('125906', $fila['n_orig']);
        $this->assertNotEmpty($fila['cheque_id']);
        $this->assertNotEmpty($fila['pagoproveedor_id']);
    }

    public function test_enriquecer_pendientes_rellena_campos_vacios(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cheque')) {
            $this->markTestSkipped('Sin tabla cheque');
        }

        $existe = \Illuminate\Support\Facades\DB::table('cheque')
            ->where('numerocheque', '82048436')
            ->exists();
        if (! $existe) {
            $this->markTestSkipped('Cheque 82048436 no está en este entorno');
        }

        $out = ConciliacionBancariaChequeErpEnrichSupport::enriquecerPendientes([[
            'tip' => 'CHP',
            'numero_cheque' => '82048436',
            'entregado_a' => '',
            'proveedor_codigo' => '',
            'nro_op' => '',
            'fecha_entrega' => null,
            'origen_json' => ['semilla' => true],
        ]], 1, 52);

        $this->assertSame('SUSFERRIN SRL', $out[0]['entregado_a']);
        $this->assertSame('655', $out[0]['proveedor_codigo']);
        $this->assertSame('125906', $out[0]['nro_op']);
    }
}
