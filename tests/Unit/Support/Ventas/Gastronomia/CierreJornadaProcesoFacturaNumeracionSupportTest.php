<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaNumeracionSupport;
use Tests\TestCase;

final class CierreJornadaProcesoFacturaNumeracionSupportTest extends TestCase
{
    public function test_siguiente_incrementa_en_memoria(): void
    {
        $secuencia = new CierreJornadaProcesoFacturaNumeracionSupport(999_999, 1, 'B');

        $this->assertSame(1, $secuencia->siguiente());
        $this->assertSame(2, $secuencia->siguiente());
        $this->assertSame(3, $secuencia->siguiente());
    }

    public function test_piso_anita_supera_max_erp(): void
    {
        $secuencia = new CierreJornadaProcesoFacturaNumeracionSupport(999_998, 1, 'B', 183_680);

        $this->assertSame(183_681, $secuencia->siguiente());
        $this->assertSame(183_682, $secuencia->siguiente());
    }

    public function test_max_erp_retorna_cero_si_ids_invalidos(): void
    {
        $this->assertSame(0, CierreJornadaProcesoFacturaNumeracionSupport::maxNumerocomprobanteErp(0, 1, 'B'));
        $this->assertSame(0, CierreJornadaProcesoFacturaNumeracionSupport::maxNumerocomprobanteErp(1, 0, 'B'));
    }
}
