<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorServicioSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorServicioSupportTest extends TestCase
{
    public function test_etiqueta_cliente_y_detalle(): void
    {
        $servicio = (object) ['id' => 1, 'cliente' => '45001234', 'detalle' => 'Luz planta'];

        $this->assertSame('45001234 — Luz planta', ComprobanteProveedorServicioSupport::etiqueta($servicio));
        $this->assertSame(
            'Cliente 45001234 — Luz planta',
            ComprobanteProveedorServicioSupport::leyendaSugerida($servicio)
        );
    }

    public function test_etiqueta_solo_cliente(): void
    {
        $servicio = (object) ['id' => 2, 'cliente' => '99', 'detalle' => ''];

        $this->assertSame('99', ComprobanteProveedorServicioSupport::etiqueta($servicio));
    }

    public function test_leyenda_desde_cliente_snapshot(): void
    {
        $this->assertSame(
            'Cliente 45001234',
            ComprobanteProveedorServicioSupport::leyendaDesdeCliente('45001234')
        );
        $this->assertNull(ComprobanteProveedorServicioSupport::leyendaDesdeCliente('  '));
    }

    public function test_opciones_para_json(): void
    {
        $opciones = ComprobanteProveedorServicioSupport::opcionesParaJson([
            (object) ['id' => 5, 'cliente' => 'ABC', 'detalle' => 'Gas'],
            (object) ['id' => 0, 'cliente' => 'X', 'detalle' => null],
        ]);

        $this->assertCount(1, $opciones);
        $this->assertSame(5, $opciones[0]['id']);
        $this->assertSame('ABC — Gas', $opciones[0]['etiqueta']);
        $this->assertSame('Cliente ABC — Gas', $opciones[0]['leyenda']);
    }

    public function test_errores_sin_proveedor_no_exige(): void
    {
        $this->assertSame([], ComprobanteProveedorServicioSupport::erroresAsignacion(null, null));
        $this->assertSame([], ComprobanteProveedorServicioSupport::erroresAsignacion(1, 0));
    }

    public function test_resolver_sin_proveedor_vacio(): void
    {
        $asig = ComprobanteProveedorServicioSupport::resolverAsignacion(10, null, 'ABC');
        $this->assertNull($asig['proveedor_servicio_id']);
        $this->assertNull($asig['servicio_cliente']);
    }

    public function test_opciones_para_proveedores_sin_ids(): void
    {
        $this->assertSame([], ComprobanteProveedorServicioSupport::opcionesParaProveedores([]));
        $this->assertSame([], ComprobanteProveedorServicioSupport::opcionesParaProveedores([0, -1]));
    }
}
