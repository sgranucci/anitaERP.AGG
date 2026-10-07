<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ChequeConsultaChequeraSupport;
use PHPUnit\Framework\TestCase;

class ChequeConsultaChequeraSupportTest extends TestCase
{
    public function test_etiqueta_distingue_tipo_y_codigo(): void
    {
        $this->assertSame('Diferido · 3', ChequeConsultaChequeraSupport::etiquetaCompacta('3', 'D'));
        $this->assertSame('Al día · 2', ChequeConsultaChequeraSupport::etiquetaCompacta('2', 'N'));
        $this->assertSame(
            'Diferido · 3 · 43.357.606 – 43.358.605',
            ChequeConsultaChequeraSupport::etiquetaCompleta('3', 'D', 43357606, 43358605)
        );
    }

    public function test_disponibles_resta_ultimo_usado(): void
    {
        $this->assertSame(5, ChequeConsultaChequeraSupport::disponibles(43358600, 43357606, 43358605));
        $this->assertSame(0, ChequeConsultaChequeraSupport::disponibles(43358605, 43357606, 43358605));
        $this->assertSame(10, ChequeConsultaChequeraSupport::disponibles(null, 1, 10));
    }

    public function test_ultimo_dentro_de_rango_ignora_numeros_de_otra_chequera(): void
    {
        $numeros = [79179784, 79179791, 41561956];

        $this->assertSame(79179784, ChequeConsultaChequeraSupport::ultimoDentroDeRango($numeros, 79179385, 79179784));
        $this->assertSame(41561956, ChequeConsultaChequeraSupport::ultimoDentroDeRango($numeros, 41561956, 41562455));
        $this->assertSame(0, ChequeConsultaChequeraSupport::ultimoDentroDeRango($numeros, 82020249, 82020648));
    }

    public function test_proximo_avisa_cuando_la_chequera_se_termina(): void
    {
        $this->assertSame('79179385', ChequeConsultaChequeraSupport::proximoNumeroEnRango(79179385, 79179784, 79179384, '148'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La chequera 148 (79179385-79179784) no tiene más números');
        ChequeConsultaChequeraSupport::proximoNumeroEnRango(79179385, 79179784, 79179784, '148');
    }
}
