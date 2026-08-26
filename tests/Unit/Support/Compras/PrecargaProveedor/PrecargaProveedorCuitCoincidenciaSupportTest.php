<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorCuitCoincidenciaSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorCuitCoincidenciaSupportTest extends TestCase
{
    public function test_cuit_identicos_coinciden(): void
    {
        $this->assertTrue(
            PrecargaProveedorCuitCoincidenciaSupport::coinciden('20-18907979-7', '20189079797')
        );
    }

    public function test_digito_extra_al_final_coincide_por_prefijo_de_11(): void
    {
        $this->assertTrue(
            PrecargaProveedorCuitCoincidenciaSupport::coinciden('20-18907979-7', '201890797979')
        );
    }

    public function test_cuits_distintos_no_coinciden(): void
    {
        $this->assertFalse(
            PrecargaProveedorCuitCoincidenciaSupport::coinciden('20-18907979-7', '30701234567')
        );
    }

    public function test_prefijo_distinto_no_coincide(): void
    {
        $this->assertFalse(
            PrecargaProveedorCuitCoincidenciaSupport::coinciden('20189079797', '301890797979')
        );
    }

    public function test_vacio_no_coincide(): void
    {
        $this->assertFalse(
            PrecargaProveedorCuitCoincidenciaSupport::coinciden('20189079797', '')
        );
    }
}
