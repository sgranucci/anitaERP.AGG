<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use PHPUnit\Framework\TestCase;

class GastronomiaCuentacajaIconoSupportTest extends TestCase
{
    public function test_resuelve_icono_por_nombre_mercado_pago(): void
    {
        $resultado = GastronomiaCuentacajaIconoSupport::resolver('Mercado pago', 'GMEP');

        $this->assertSame('gastro-icon-mercadopago', $resultado['icono']);
    }

    public function test_resuelve_icono_por_nombre_visa(): void
    {
        $resultado = GastronomiaCuentacajaIconoSupport::resolver('VISA ARGENTINA SA RSA', '342');

        $this->assertSame('fab fa-cc-visa', $resultado['icono']);
    }

    public function test_resuelve_icono_efectivo_caja_pesos(): void
    {
        $resultado = GastronomiaCuentacajaIconoSupport::resolver('CAJA PESOS BIYEMAS', '100');

        $this->assertSame('fa fa-money-bill-wave', $resultado['icono']);
    }

    public function test_resuelve_icono_default(): void
    {
        $resultado = GastronomiaCuentacajaIconoSupport::resolver('MEDIO DESCONOCIDO', '999');

        $this->assertSame('fa fa-cash-register', $resultado['icono']);
    }

    public function test_etiqueta_boton_fiserv_y_totalcoin(): void
    {
        $this->assertSame('Fiserv', GastronomiaCuentacajaIconoSupport::etiquetaBoton('MEDIO DE COBRO FISERV', '113010'));
        $this->assertSame('Totalcoin', GastronomiaCuentacajaIconoSupport::etiquetaBoton('TOTAL COIN CAJA', '11301012'));
        $this->assertSame('Mercado Pago', GastronomiaCuentacajaIconoSupport::etiquetaBoton('Mercado pago', 'GMEP'));
    }
}
