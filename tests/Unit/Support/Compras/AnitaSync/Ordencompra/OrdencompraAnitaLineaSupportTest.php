<?php

namespace Tests\Unit\Support\Compras\AnitaSync\Ordencompra;

use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaLineaSupport;
use Tests\TestCase;

/**
 * ocvley indexa por (ocvl_nro_orden, ocvl_linea): dos renglones con el mismo
 * penvp_orden abortan el INSERT al confirmar la COM.
 */
class OrdencompraAnitaLineaSupportTest extends TestCase
{
    public function test_conserva_orden_si_no_esta_usado(): void
    {
        $usados = [];

        $this->assertSame(1, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(1, $usados));
        $this->assertSame(3, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(3, $usados));
        $this->assertSame([1 => true, 3 => true], $usados);
    }

    public function test_null_toma_el_menor_libre(): void
    {
        $usados = [];

        $this->assertSame(0, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(null, $usados));
        $this->assertSame(1, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(null, $usados));
    }

    public function test_orden_repetido_toma_el_menor_libre(): void
    {
        $usados = [];

        $this->assertSame(1, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(1, $usados));
        $this->assertSame(0, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(1, $usados));
        $this->assertSame(2, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(1, $usados));
        $this->assertSame(3, OrdencompraAnitaLineaSupport::siguienteOrdenUnico(3, $usados));
    }

    public function test_caso_oc_gastab_con_orden_1_repetido(): void
    {
        $usados = [];
        $resultado = [];
        foreach ([1, 1, 1, 3] as $orden) {
            $resultado[] = OrdencompraAnitaLineaSupport::siguienteOrdenUnico($orden, $usados);
        }

        $this->assertSame([1, 0, 2, 3], $resultado);
        $this->assertCount(4, array_unique($resultado));
    }
}
