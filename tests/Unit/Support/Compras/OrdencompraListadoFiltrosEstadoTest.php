<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraListadoFiltros;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class OrdencompraListadoFiltrosEstadoTest extends TestCase
{
    public function test_estado_invalido_se_ignora(): void
    {
        $this->assertSame('', OrdencompraListadoFiltros::normalizarEstadoExterno('FOO'));
        $this->assertSame('', OrdencompraListadoFiltros::normalizarEstadoExterno(null));
    }

    public function test_estado_valido_queda_en_query_y_en_limpiar(): void
    {
        $filtros = OrdencompraListadoFiltros::resolverDesdeRequest(Request::create('/compras/ordencompra', 'GET', [
            'empresa_todas' => 1,
            'estado' => 'pendiente',
        ]));

        $this->assertSame(OrdencompraEstados::PENDIENTE, $filtros['estado']);
        $this->assertSame(OrdencompraEstados::PENDIENTE, OrdencompraListadoFiltros::paraQueryString($filtros)['estado']);
        $this->assertSame(OrdencompraEstados::PENDIENTE, OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros)['estado']);
    }
}
