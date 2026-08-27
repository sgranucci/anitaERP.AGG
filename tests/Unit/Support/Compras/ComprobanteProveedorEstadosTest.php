<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorEstados;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorEstadosTest extends TestCase
{
    public function test_badge_colores_por_estado(): void
    {
        $this->assertSame('badge badge-secondary', ComprobanteProveedorEstados::badge(ComprobanteProveedorEstados::BORRADOR)['class']);
        $this->assertSame('badge badge-success', ComprobanteProveedorEstados::badge(ComprobanteProveedorEstados::CONTABILIZADO)['class']);
        $this->assertSame('badge badge-danger', ComprobanteProveedorEstados::badge(null, true)['class']);
        $this->assertSame('Error Anita', ComprobanteProveedorEstados::badge(null, true)['label']);
    }

    public function test_filtro_error_anita_es_valido(): void
    {
        $this->assertTrue(ComprobanteProveedorEstados::esFiltroListadoValido(ComprobanteProveedorEstados::FILTRO_ERROR_ANITA));
        $this->assertTrue(ComprobanteProveedorEstados::esFiltroListadoValido(ComprobanteProveedorEstados::FILTRO_TODOS));
        $this->assertFalse(ComprobanteProveedorEstados::esFiltroListadoValido('INEXISTENTE'));
    }

    public function test_borrador_sin_asiento_no_tiene_huella_anita(): void
    {
        $this->assertFalse(ComprobanteProveedorEstados::tieneHuellaAnita((object) [
            'anita_nro_interno' => null,
            'asiento_id' => null,
        ]));
        $this->assertSame(
            'Borrar borrador (solo ERP)',
            ComprobanteProveedorEstados::textoBorrarTooltip(false)
        );
        $this->assertStringContainsString(
            'No se toca Anita',
            ComprobanteProveedorEstados::textoBorrarConfirm(248, false)
        );
    }

    public function test_contabilizado_tiene_huella_anita(): void
    {
        $this->assertTrue(ComprobanteProveedorEstados::tieneHuellaAnita([
            'anita_nro_interno' => 428006,
            'asiento_id' => 1,
        ]));
        $this->assertSame(
            'Borrar factura (ERP + Anita)',
            ComprobanteProveedorEstados::textoBorrarTooltip(true)
        );
    }
}
