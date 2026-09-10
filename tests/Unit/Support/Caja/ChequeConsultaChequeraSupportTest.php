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
}
