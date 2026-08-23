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
}
