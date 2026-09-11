<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorPadronIibbEtiquetaSupport;
use PHPUnit\Framework\TestCase;

class ProveedorPadronIibbEtiquetaSupportTest extends TestCase
{
    public function test_muestra_tasa_de_retencion(): void
    {
        $this->assertSame(
            '3%',
            ProveedorPadronIibbEtiquetaSupport::retencion([
                'tasa' => 3.0,
                'tipocontribuyente' => 'C',
                'origen' => 'padron',
            ])
        );
    }

    public function test_muestra_cero_si_el_padron_publica_cero(): void
    {
        $this->assertSame(
            '0%',
            ProveedorPadronIibbEtiquetaSupport::retencion([
                'tasa' => 0.0,
                'origen' => 'padron',
            ])
        );
    }

    public function test_sin_padron_o_sin_tasa(): void
    {
        $this->assertSame('No esta en padron', ProveedorPadronIibbEtiquetaSupport::retencion(null));
        $this->assertSame(
            'No esta en padron',
            ProveedorPadronIibbEtiquetaSupport::retencion([
                'tasa' => null,
                'origen' => 'padron',
            ])
        );
    }
}
