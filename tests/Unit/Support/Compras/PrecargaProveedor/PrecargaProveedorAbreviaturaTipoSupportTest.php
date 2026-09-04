<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorAbreviaturaTipoSupportTest extends TestCase
{
    public function test_cc_gastronomia_fuerza_fga(): void
    {
        $this->assertSame(
            'FGA',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 85, 'Directo', 'B')
        );
    }

    public function test_cc_logistica_indirecto_bienes_es_fib(): void
    {
        $this->assertSame(
            'FIB',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 103, 'Indirecto', 'B')
        );
    }

    public function test_cc_104_es_feg(): void
    {
        $this->assertSame(
            'FEG',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 104, 'Indirecto', 'B')
        );
    }

    public function test_servicio_con_iva_indirecto_es_fis(): void
    {
        $this->assertSame(
            'FIS',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 103, 'Indirecto', 'S')
        );
    }

    public function test_nota_credito_respeta_inicial(): void
    {
        $this->assertSame(
            'CIB',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('NC', 103, 'Indirecto', 'B')
        );
    }
}
