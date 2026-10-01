<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\CuentaCentrocostoAsignadosSupport;
use PHPUnit\Framework\TestCase;

class CuentaCentrocostoAsignadosSupportTest extends TestCase
{
    public function test_un_solo_asignado_se_toma_aunque_no_haya_eleccion(): void
    {
        $this->assertSame(8, CuentaCentrocostoAsignadosSupport::resolverDesdeLista(true, [8], 0, 3));
    }

    public function test_varios_asignados_respeta_el_elegido_de_la_lista(): void
    {
        $this->assertSame(5, CuentaCentrocostoAsignadosSupport::resolverDesdeLista(true, [4, 5, 9], 5, 4));
    }

    public function test_varios_sin_eleccion_valida_queda_en_cero(): void
    {
        $this->assertSame(0, CuentaCentrocostoAsignadosSupport::resolverDesdeLista(true, [4, 5], 0, 99));
    }

    public function test_sin_matriz_conserva_el_centro_de_la_factura(): void
    {
        $this->assertSame(3, CuentaCentrocostoAsignadosSupport::resolverDesdeLista(true, [], 0, 3));
    }

    public function test_cuenta_que_no_maneja_centro_no_pisa_el_de_la_factura(): void
    {
        $this->assertSame(3, CuentaCentrocostoAsignadosSupport::resolverDesdeLista(false, [8], 0, 3));
    }
}
