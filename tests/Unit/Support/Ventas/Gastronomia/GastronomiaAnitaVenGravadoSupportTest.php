<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport;
use PHPUnit\Framework\TestCase;

final class GastronomiaAnitaVenGravadoSupportTest extends TestCase
{
    public function test_gravado_desde_subtotal_e_iva_cuando_no_hay_gravado_al(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'importe' => 4462.81, 'baseimponible' => 0],
            ['concepto' => 'Iva 21.000%', 'importe' => 937.19, 'baseimponible' => 4462.81],
            ['concepto' => 'Total', 'importe' => 5400, 'baseimponible' => 0],
        ];

        $this->assertSame(4462.81, GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptos, 5400.0));
    }

    public function test_gravado_cero_en_cortesia_minima(): void
    {
        $conceptos = [
            ['concepto' => 'Subtotal', 'importe' => 100, 'baseimponible' => 0],
            ['concepto' => 'Iva 21.000%', 'importe' => 0, 'baseimponible' => 0],
        ];

        $this->assertSame(0.0, GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptos, 0.01));
    }

    public function test_prefiere_gravado_al_si_existe(): void
    {
        $conceptos = [
            ['concepto' => 'Gravado al 21.000%', 'importe' => 1000, 'baseimponible' => 0],
            ['concepto' => 'Subtotal', 'importe' => 500, 'baseimponible' => 0],
        ];

        $this->assertSame(1000.0, GastronomiaAnitaVenGravadoSupport::gravadoDesdeConceptosTotales($conceptos, 1210.0));
    }
}
