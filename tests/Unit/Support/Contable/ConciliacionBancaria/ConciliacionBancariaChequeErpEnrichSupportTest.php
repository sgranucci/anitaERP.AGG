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

    public function test_acp_completa_detalle_desde_cheque_anulado(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cheque')) {
            $this->markTestSkipped('Sin tabla cheque');
        }

        $existe = \Illuminate\Support\Facades\DB::table('cheque')
            ->where('numerocheque', '70507390')
            ->where('empresa_id', 1)
            ->where('cuentacaja_id', 52)
            ->exists();
        if (! $existe) {
            $this->markTestSkipped('Cheque 70507390 no está en este entorno');
        }

        $resultado = [
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 52, 'codigo' => '127', 'nombre' => 'TEST'],
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [[
                'tip' => 'ACP',
                'numero_cheque' => '70507390',
                'fecha_emision' => '2026-06-10',
                'fecha_cheque' => '2026-06-10',
                'importe' => 9470949.75,
                'entregado_a' => '',
                'proveedor_codigo' => '',
                'nro_op' => '',
                'origen_json' => ['semilla' => true, 'importe_desde_excel' => true],
            ]],
        ];

        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar($resultado);
        $fila = $reporte['filas'][0];
        $this->assertSame('ACP', $fila['tip']);
        $this->assertSame('PROCESSING DATA ARGENTINA', $fila['detalle']);
        $this->assertNotEmpty($fila['cheque_id']);
    }

    public function test_semilla_importe_cero_no_pisa_erp_y_reporte_recupera_monto(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cheque')) {
            $this->markTestSkipped('Sin tabla cheque');
        }

        $existe = \Illuminate\Support\Facades\DB::table('cheque')
            ->where('numerocheque', '57038794')
            ->where('cuentacaja_id', 52)
            ->exists();
        if (! $existe) {
            $this->markTestSkipped('Cheque 57038794 no está en este entorno');
        }

        $arm = (new \App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendientesCpromaeSupport())
            ->armar(
                '127',
                1,
                now()->endOfMonth(),
                ['57038794'],
                [
                    '57038794' => [
                        'tip' => 'CHP',
                        'importe' => 0,
                        'fecha_emision' => '2026-10-21',
                        'fecha_cheque' => '2026-12-04',
                    ],
                ],
                false,
                true,
            );
        $pend = collect($arm['pendientes'])->firstWhere('numero_cheque', '57038794');
        $this->assertNotNull($pend);
        $this->assertGreaterThan(0.01, (float) ($pend['importe'] ?? 0));

        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar([
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 52, 'codigo' => '127', 'nombre' => 'TEST'],
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [[
                'tip' => 'CHP',
                'numero_cheque' => '57038794',
                'fecha_emision' => '2026-10-21',
                'fecha_cheque' => '2026-12-04',
                'importe' => 0,
                'entregado_a' => 'BODEGAS Y VINEDOS LOPEZ SAIC',
                'nro_op' => '',
                'origen_json' => ['importe_desde_excel' => true],
            ]],
        ]);
        $fila = $reporte['filas'][0];
        $this->assertGreaterThan(0.01, (float) ($fila['credito'] ?? 0));
        $this->assertEqualsWithDelta(381777.14, (float) $fila['credito'], 0.02);
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
