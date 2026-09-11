<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\RendicionMaquina\RendicionMaquinaAjusteWigosSupport;
use PHPUnit\Framework\TestCase;

class RendicionMaquinaAjusteWigosSupportTest extends TestCase
{
    public function test_totalcoin_qr_esta_en_campos_ajustables(): void
    {
        $campos = RendicionMaquinaAjusteWigosSupport::camposAjustables();
        $this->assertArrayHasKey(
            RendicionMaquinaAjusteWigosSupport::CAMPO_TOTALCOIN_QR_MAQUINAS,
            $campos
        );
        $this->assertFalse(
            RendicionMaquinaAjusteWigosSupport::requierePermisoAjustar(
                RendicionMaquinaAjusteWigosSupport::CAMPO_TOTALCOIN_QR_MAQUINAS
            )
        );
    }

    public function test_fusiona_totalcoin_si_difiere_de_la_precarga(): void
    {
        $ajustes = RendicionMaquinaAjusteWigosSupport::fusionarAjusteTotalCoin(
            [],
            114399424.94,
            114449945.00
        );

        $this->assertCount(1, $ajustes);
        $this->assertSame(
            RendicionMaquinaAjusteWigosSupport::CAMPO_TOTALCOIN_QR_MAQUINAS,
            $ajustes[0]['campo']
        );
        $this->assertSame(114399424.94, $ajustes[0]['valor_wigos']);
        $this->assertSame(114449945.00, $ajustes[0]['valor_ajustado']);
    }

    public function test_no_duplica_si_el_payload_ya_trae_totalcoin(): void
    {
        $ya = [[
            'campo' => RendicionMaquinaAjusteWigosSupport::CAMPO_TOTALCOIN_QR_MAQUINAS,
            'valor_wigos' => 1.0,
            'valor_ajustado' => 2.0,
        ]];
        $out = RendicionMaquinaAjusteWigosSupport::fusionarAjusteTotalCoin($ya, 1.0, 3.0);
        $this->assertCount(1, $out);
        $this->assertSame(2.0, $out[0]['valor_ajustado']);
    }

    public function test_original_totalcoin_usa_la_clave_guardada(): void
    {
        $this->assertSame(
            114398945.0,
            RendicionMaquinaAjusteWigosSupport::valorOriginalTotalCoinDesdeWigosJson([
                RendicionMaquinaAjusteWigosSupport::CAMPO_TOTALCOIN_QR_MAQUINAS => 114398945,
            ])
        );
    }
}
