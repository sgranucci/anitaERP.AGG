<?php

namespace Tests\Unit\Support\Contable\ConciliacionBancaria;

use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaSinConciliarReporteSupport;
use Tests\TestCase;

/**
 * Regresión vs Excel Contaduría (BSA 1112 / RSA 326): el reporte estilo Anita
 * no debe listar ASI/TRA/IB ni duplicar OPP ya presentes en cpromae.
 */
class ConciliacionBancariaSinConciliarReporteSupportTest extends TestCase
{
    public function test_excluye_asi_tra_e_ib_estilo_anita(): void
    {
        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar([
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 150, 'codigo' => '1112', 'nombre' => 'BSA'],
            'fecha_desde' => '2019-01-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [[
                'tip' => 'CHP',
                'numero_cheque' => '713312',
                'fecha_emision' => '2019-03-26',
                'fecha_cheque' => '2019-03-27',
                'importe' => 2753.77,
                'entregado_a' => 'I.Q.C. SOCIEDAD ANONIMA',
                'hash' => 'ch-713312',
            ]],
            'pendientes_contables_otros' => [
                [
                    'tipo_comp' => 'ASI',
                    'nro' => '370164',
                    'nro_asiento' => '370164',
                    'fecha' => '2026-11-01',
                    'descripcion' => 'Cierre rendición máquinas',
                    'debe' => 13539400,
                    'haber' => 0,
                    'hash' => 'asi-370164',
                ],
                [
                    'tipo_comp' => 'TRA',
                    'nro' => '4064',
                    'fecha' => '2026-11-11',
                    'descripcion' => 'Trf bi bank a bma',
                    'debe' => 46000000,
                    'haber' => 0,
                    'hash' => 'tra-4064',
                ],
            ],
            'pendientes_banco' => [[
                'id' => 725592,
                'voucher_number' => '725592',
                'process_date' => '2026-11-17',
                'code_description_ib' => 'CRED.INT',
                'importe' => 200000,
                'debit_credit_type' => 'C',
                'hash' => 'ib-725592',
            ]],
        ]);

        $this->assertSame(1, $reporte['totales']['n']);
        $this->assertSame('CHP', $reporte['filas'][0]['tip']);
        $this->assertSame('713312', $reporte['filas'][0]['numero']);
        $tips = array_column($reporte['filas'], 'tip');
        $this->assertNotContains('ASI', $tips);
        $this->assertNotContains('TRA', $tips);
        $this->assertNotContains('IB', $tips);
    }

    public function test_no_duplica_opp_cpromae_y_mayor_con_otro_detalle(): void
    {
        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar([
            'empresa_id' => 3,
            'cuentacaja' => (object) ['id' => 77, 'codigo' => '326', 'nombre' => 'RSA'],
            'fecha_desde' => '2024-01-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [[
                'tip' => 'OPP',
                'numero_cheque' => '68952',
                'fecha_emision' => '2026-11-03',
                'fecha_cheque' => '2026-11-03',
                'importe' => 152751444,
                'entregado_a' => 'SUELDOS',
                'hash' => 'opp-cpromae-68952',
            ]],
            'pendientes_contables_otros' => [[
                'tipo_comp' => 'OPP',
                'nro' => '68952',
                'fecha' => '2026-11-03',
                'descripcion' => 'NETOS 10-2026 - BANCO MACRO',
                'debe' => 0,
                'haber' => 152751444,
                'hash' => 'opp-mayor-68952',
            ]],
        ]);

        $this->assertSame(1, $reporte['totales']['n']);
        $this->assertSame('OPP', $reporte['filas'][0]['tip']);
        $this->assertSame('68952', $reporte['filas'][0]['numero']);
        $this->assertSame('SUELDOS', $reporte['filas'][0]['detalle']);
    }

    public function test_opp_solo_en_mayor_si_entra(): void
    {
        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar([
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 150, 'codigo' => '1112', 'nombre' => 'BSA'],
            'fecha_desde' => '2024-01-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_contables_otros' => [[
                'tipo_comp' => 'OPP',
                'nro' => '111975',
                'fecha' => '2024-11-15',
                'descripcion' => 'Pago: 111975 AGUA Y SANEAMIENT',
                'debe' => 0,
                'haber' => 19241.95,
                'hash' => 'opp-111975',
            ]],
        ]);

        $this->assertSame(1, $reporte['totales']['n']);
        $this->assertSame('OPP', $reporte['filas'][0]['tip']);
        $this->assertSame('111975', $reporte['filas'][0]['numero']);
    }

    public function test_incluye_cob_y_ted_en_debitos_como_anita(): void
    {
        $reporte = ConciliacionBancariaSinConciliarReporteSupport::armar([
            'empresa_id' => 1,
            'cuentacaja' => (object) ['id' => 52, 'codigo' => '127', 'nombre' => 'MACRO GERLI'],
            'fecha_desde' => '2026-11-01',
            'fecha_hasta' => '2026-11-30',
            'mes' => 11,
            'anio' => 2026,
            'pendientes_cheques_cpromae' => [
                [
                    'tip' => 'COB',
                    'numero_cheque' => '1',
                    'fecha_emision' => '2026-11-13',
                    'fecha_cheque' => '2026-11-13',
                    'importe' => -1786343.4,
                    'debe' => 1786343.4,
                    'haber' => null,
                    'entregado_a' => 'PUCARA CINE SRL - COA Nro. 1 O',
                    'hash' => 'cob-1',
                ],
                [
                    'tip' => 'TED',
                    'numero_cheque' => '10867',
                    'fecha_emision' => '2026-11-18',
                    'fecha_cheque' => '2026-11-18',
                    'importe' => -16000000,
                    'debe' => 16000000,
                    'haber' => null,
                    'entregado_a' => 'TRA 4079 Trf bma a macro',
                    'hash' => 'ted-10867',
                ],
            ],
            'pendientes_contables_otros' => [[
                'tipo_comp' => 'TRA',
                'nro' => '4079',
                'fecha' => '2026-11-18',
                'descripcion' => 'Trf bma a macro',
                'debe' => 16000000,
                'haber' => 0,
                'hash' => 'tra-4079',
            ]],
        ]);

        $this->assertSame(2, $reporte['totales']['n']);
        $tips = array_column($reporte['filas'], 'tip');
        $this->assertContains('COB', $tips);
        $this->assertContains('TED', $tips);
        $this->assertNotContains('TRA', $tips);

        $porTip = [];
        foreach ($reporte['filas'] as $f) {
            $porTip[$f['tip']] = $f;
        }
        $this->assertSame(1786343.4, $porTip['COB']['debito']);
        $this->assertSame(0.0, $porTip['COB']['credito']);
        $this->assertSame(16000000.0, $porTip['TED']['debito']);
        $this->assertSame(0.0, $porTip['TED']['credito']);
    }
}
