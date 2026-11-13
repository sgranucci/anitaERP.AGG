<?php

namespace Tests\Unit\Support\Contable\ConciliacionBancaria;

use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaPendienteSupport;
use PHPUnit\Framework\TestCase;

class ConciliacionBancariaPendienteSupportAnulaTest extends TestCase
{
    public function test_detecta_anula_con_referencia_de_cheque(): void
    {
        $mov = [
            'tipo_comp' => 'OPP',
            'descripcion' => 'ANULA OPP 117522 PROCESSING DAT Ch: 70507390',
            'debe' => 9470949.75,
            'haber' => 0,
        ];

        $this->assertTrue(ConciliacionBancariaPendienteSupport::esAnulaChequeContable($mov));
        $this->assertFalse(ConciliacionBancariaPendienteSupport::esChequeNoAcreditado($mov));
    }

    public function test_cheque_normal_sigue_siendo_cheque(): void
    {
        $mov = [
            'tipo_comp' => 'CHP',
            'descripcion' => 'PROCESSING DATA ARGENTINA Ch: 70507390',
            'debe' => 0,
            'haber' => 9470949.75,
        ];

        $this->assertFalse(ConciliacionBancariaPendienteSupport::esAnulaChequeContable($mov));
        $this->assertTrue(ConciliacionBancariaPendienteSupport::esChequeNoAcreditado($mov));
    }

    public function test_anula_sin_ch_no_es_anula_cheque(): void
    {
        $mov = [
            'tipo_comp' => 'OPP',
            'descripcion' => 'ANULA OPP 117522 TRANSFERENCIA',
            'debe' => 1000,
            'haber' => 0,
        ];

        $this->assertFalse(ConciliacionBancariaPendienteSupport::esAnulaChequeContable($mov));
    }

    public function test_particion_manda_anula_a_anulas_no_a_otros(): void
    {
        $part = ConciliacionBancariaPendienteSupport::particionarContables([
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'ANULA OPP 117522 PROCESSING DAT Ch: 70507390',
                'debe' => 9470949.75,
                'haber' => 0,
                'fecha' => '20261103',
            ],
        ]);

        $this->assertCount(0, $part['cheques']);
        $this->assertCount(0, $part['otros']);
        $this->assertCount(1, $part['anulas']);
        $this->assertSame(9470949.75, $part['suma_anulas']);
        $this->assertSame(0.0, $part['suma_otros']);
    }

    public function test_filas_anulas_para_pendientes_cheques(): void
    {
        $filas = ConciliacionBancariaPendienteSupport::filasAnulasParaPendientesCheques([
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'ANULA OPP 117522 PROCESSING DAT Ch: 70507390',
                'debe' => 9470949.75,
                'haber' => 0,
                'fecha' => '20261103',
                'nro_asiento_fmt' => '369124',
            ],
        ]);

        $this->assertCount(1, $filas);
        $this->assertSame('ANULA', $filas[0]['tip']);
        $this->assertSame('70507390', $filas[0]['numero_cheque']);
        $this->assertSame(9470949.75, $filas[0]['debe']);
        $this->assertTrue($filas[0]['incluye_caratula']);
    }

    public function test_filas_anulas_omite_si_el_original_sigue_pendiente(): void
    {
        $filas = ConciliacionBancariaPendienteSupport::filasAnulasParaPendientesCheques([
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'NOVA GAMES S.A Ch: 82048489',
                'debe' => 0,
                'haber' => 37648684,
                'fecha' => '20261112',
            ],
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'ANULA OPP 126010 NOVA GAMES S.A Ch: 82048489',
                'debe' => 37648684,
                'haber' => 0,
                'fecha' => '20261112',
            ],
        ]);

        $this->assertSame([], $filas);
    }

    public function test_suma_caratula_incluye_anula_si_no_queda_el_original(): void
    {
        $pendientes = [
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'ANULA OPP 117522 PROCESSING DAT Ch: 70507390',
                'debe' => 9470949.75,
                'haber' => 0,
            ],
        ];

        $this->assertSame(
            9470949.75,
            ConciliacionBancariaPendienteSupport::sumaAnulasChequeParaCaratula($pendientes),
        );
    }

    public function test_suma_caratula_omite_anula_si_el_cheque_sigue_pendiente(): void
    {
        $pendientes = [
            [
                'tipo_comp' => 'CHP',
                'descripcion' => 'PROCESSING DATA ARGENTINA Ch: 70507390',
                'debe' => 0,
                'haber' => 9470949.75,
            ],
            [
                'tipo_comp' => 'OPP',
                'descripcion' => 'ANULA OPP 117522 PROCESSING DAT Ch: 70507390',
                'debe' => 9470949.75,
                'haber' => 0,
            ],
        ];

        $this->assertSame(
            0.0,
            ConciliacionBancariaPendienteSupport::sumaAnulasChequeParaCaratula($pendientes),
        );
    }
}
