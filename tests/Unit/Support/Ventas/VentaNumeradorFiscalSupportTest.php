<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentaNumeradorFiscalSupport;
use Tests\TestCase;

final class VentaNumeradorFiscalSupportTest extends TestCase
{
    public function test_proximo_numero_es_maximo_ultimo_o_piso_mas_uno(): void
    {
        $this->assertSame(1, VentaNumeradorFiscalSupport::proximoNumero(0, 0));
        $this->assertSame(45, VentaNumeradorFiscalSupport::proximoNumero(44, 0));
        $this->assertSame(44, VentaNumeradorFiscalSupport::proximoNumero(0, 43));
        $this->assertSame(69, VentaNumeradorFiscalSupport::proximoNumero(68, 43));
    }

    public function test_flag_de_uso_queda_apagado_por_defecto(): void
    {
        config(['facturacion.NUMERADOR_FISCAL_EN_USO' => false]);

        $this->assertFalse(VentaNumeradorFiscalSupport::estaEnUso());
    }

    public function test_fusion_anita_manda_erp_solo_si_falta_la_serie(): void
    {
        $anita = [
            ['puntoventa_id' => 3, 'codigo_afip' => 1, 'max_nro' => 50],
        ];
        $erp = [
            ['puntoventa_id' => 3, 'codigo_afip' => 1, 'max_nro' => 68],
            ['puntoventa_id' => 3, 'codigo_afip' => 201, 'max_nro' => 2],
        ];

        $conFallback = VentaNumeradorFiscalSupport::fusionarMaximosSemilla($anita, $erp, true);
        $porAfip = [];
        foreach ($conFallback as $fila) {
            $porAfip[$fila['codigo_afip']] = $fila;
        }

        $this->assertSame(50, $porAfip[1]['max_nro']);
        $this->assertSame('anita', $porAfip[1]['origen']);
        $this->assertSame(2, $porAfip[201]['max_nro']);
        $this->assertSame('erp', $porAfip[201]['origen']);

        $sinFallback = VentaNumeradorFiscalSupport::fusionarMaximosSemilla($anita, $erp, false);
        $this->assertCount(1, $sinFallback);
        $this->assertSame(1, $sinFallback[0]['codigo_afip']);
    }
}
