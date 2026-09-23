<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Support\Ventas\ClienteProvinciaIibbSupport;
use PHPUnit\Framework\TestCase;

final class ClienteProvinciaIibbConvenioTest extends TestCase
{
    public function test_prioriza_provincia_de_entrega(): void
    {
        $entrega = new Cliente_Entrega([
            'provincia_id' => 10,
            'provincia_iibb_id' => 20,
        ]);
        $cliente = new Cliente([
            'provincia_id' => 30,
            'provincia_iibb_id' => 40,
        ]);

        $this->assertSame(
            10,
            ClienteProvinciaIibbSupport::idProvinciaEntregaParaConvenio($cliente, $entrega, 50)
        );
    }

    public function test_fallback_sede_iibb_de_entrega(): void
    {
        $entrega = new Cliente_Entrega([
            'provincia_id' => null,
            'provincia_iibb_id' => 20,
        ]);
        $cliente = new Cliente([
            'provincia_id' => 30,
            'provincia_iibb_id' => 40,
        ]);

        $this->assertSame(
            20,
            ClienteProvinciaIibbSupport::idProvinciaEntregaParaConvenio($cliente, $entrega)
        );
    }

    public function test_sin_entrega_usa_sede_iibb_del_cliente(): void
    {
        $cliente = new Cliente([
            'provincia_id' => 30,
            'provincia_iibb_id' => 40,
        ]);

        $this->assertSame(
            40,
            ClienteProvinciaIibbSupport::idProvinciaEntregaParaConvenio($cliente, null)
        );
    }

    public function test_sin_sede_usa_domicilio_del_cliente(): void
    {
        $cliente = new Cliente([
            'provincia_id' => 30,
            'provincia_iibb_id' => null,
        ]);

        $this->assertSame(
            30,
            ClienteProvinciaIibbSupport::idProvinciaEntregaParaConvenio($cliente, null)
        );
    }

    public function test_ultimo_fallback_provincia_de_la_factura(): void
    {
        $this->assertSame(
            50,
            ClienteProvinciaIibbSupport::idProvinciaEntregaParaConvenio(null, null, 50)
        );
    }
}
