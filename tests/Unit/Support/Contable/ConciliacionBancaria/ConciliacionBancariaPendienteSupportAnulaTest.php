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

    public function test_particion_manda_anula_a_otros(): void
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
        $this->assertCount(1, $part['otros']);
        $this->assertSame(9470949.75, $part['suma_otros']);
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
