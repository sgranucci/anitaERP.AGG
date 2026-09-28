<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PagoproveedorEnvioMasivoClasificacionSupport;
use PHPUnit\Framework\TestCase;

class PagoproveedorEnvioMasivoClasificacionSupportTest extends TestCase
{
    public function test_medio_cheque_sin_senial_de_transferencia(): void
    {
        $this->assertSame(
            'cheque',
            PagoproveedorEnvioMasivoClasificacionSupport::medio(true, false, true)
        );
    }

    public function test_medio_transferencia_por_cbu_o_cuenta_bancaria(): void
    {
        $this->assertSame(
            'transferencia',
            PagoproveedorEnvioMasivoClasificacionSupport::medio(false, true, false)
        );
        $this->assertSame(
            'transferencia',
            PagoproveedorEnvioMasivoClasificacionSupport::medio(false, false, true)
        );
    }

    public function test_medio_mixto_y_excluido(): void
    {
        $this->assertSame(
            'mixto',
            PagoproveedorEnvioMasivoClasificacionSupport::medio(true, true, true)
        );
        $this->assertNull(
            PagoproveedorEnvioMasivoClasificacionSupport::medio(false, false, false)
        );
    }

    public function test_snapshot_detecta_cheque_y_transferencia(): void
    {
        $snap = [
            'cheques' => [['numerocheque' => '100']],
            'medios_caja' => [['cuenta' => 'Banco Macro TMR [0001]']],
        ];
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneCheques($snap));
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneTransferencia($snap));
        $this->assertFalse(PagoproveedorEnvioMasivoClasificacionSupport::snapshotTieneTransferencia([
            'medios_caja' => [['cuenta' => 'Efectivo (EFE)']],
        ]));
    }

    public function test_asociacion_ok_cuando_empresa_importe_y_cbu_coinciden(): void
    {
        $cbu = '2850590940094012429091';
        $r = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
            'transferencia',
            15,
            true,
            2,
            2,
            1000.0,
            1000.0,
            1100.0,
            $cbu,
            $cbu
        );

        $this->assertSame('ok', $r['estado']);
        $this->assertSame('Asociada', $r['etiqueta']);
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($r['estado']));
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::seleccionadaPorDefecto(true, 'transferencia', $r['estado']));
    }

    public function test_asociacion_sin_vincular_no_se_tilda(): void
    {
        $r = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
            'transferencia',
            null,
            false,
            1,
            0,
            0,
            500,
            500,
            '',
            ''
        );

        $this->assertSame('sin_vincular', $r['estado']);
        $this->assertFalse(PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($r['estado']));
        $this->assertFalse(PagoproveedorEnvioMasivoClasificacionSupport::seleccionadaPorDefecto(true, 'transferencia', $r['estado']));
    }

    public function test_asociacion_importe_o_cbu_distintos(): void
    {
        $r = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
            'mixto',
            9,
            true,
            1,
            1,
            800,
            1000,
            1200,
            '2850590940094012429091',
            '0110590940094012429091'
        );

        $this->assertSame('importe_y_cbu', $r['estado']);
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::adjuntaComprobante($r['estado']));
    }

    public function test_cheque_no_requiere_transferencia_y_viene_tildado(): void
    {
        $r = PagoproveedorEnvioMasivoClasificacionSupport::evaluarAsociacion(
            'cheque',
            null,
            false,
            1,
            0,
            0,
            100,
            100,
            '',
            ''
        );

        $this->assertSame('no_aplica', $r['estado']);
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::seleccionadaPorDefecto(true, 'cheque', $r['estado']));
        $this->assertFalse(PagoproveedorEnvioMasivoClasificacionSupport::seleccionadaPorDefecto(false, 'cheque', $r['estado']));
    }

    public function test_filtro_de_medio(): void
    {
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::filtroIncluye('cheque', 'mixto'));
        $this->assertFalse(PagoproveedorEnvioMasivoClasificacionSupport::filtroIncluye('cheque', 'transferencia'));
        $this->assertTrue(PagoproveedorEnvioMasivoClasificacionSupport::filtroIncluye('ambas', 'cheque'));
    }

    public function test_elige_transferencia_persistida_por_importe_y_cbu(): void
    {
        $cbu = '2850461630001487461006';
        $r = PagoproveedorEnvioMasivoClasificacionSupport::elegirPersistida([
            'neto' => 1690583.70,
            'bruto' => 1690583.70,
            'cbu' => $cbu,
            'cuit' => '30525390086',
            'fecha' => '2026-09-24',
        ], [
            [
                'id' => 10,
                'amount' => 100.0,
                'cbu' => $cbu,
                'cuit' => '30525390086',
                'fecha' => '2026-09-24',
            ],
            [
                'id' => 11,
                'amount' => 1690583.70,
                'cbu' => $cbu,
                'cuit' => '30525390086',
                'fecha' => '2026-09-24',
            ],
        ], []);

        $this->assertSame('ok', $r['estado']);
        $this->assertSame(11, $r['transferencia_id']);
    }

    public function test_no_reutiliza_transferencia_ya_asignada(): void
    {
        $candidato = [
            'id' => 11,
            'amount' => 500.0,
            'cbu' => '2850461630001487461006',
            'cuit' => '30525390086',
            'fecha' => '2026-09-24',
        ];
        $op = [
            'neto' => 500.0,
            'bruto' => 500.0,
            'cbu' => '2850461630001487461006',
            'cuit' => '30525390086',
            'fecha' => '2026-09-24',
        ];

        $r = PagoproveedorEnvioMasivoClasificacionSupport::elegirPersistida($op, [$candidato], [11 => true]);

        $this->assertSame('sin_vincular', $r['estado']);
        $this->assertNull($r['transferencia_id']);
    }
}
