<?php

namespace Tests\Unit\Support\Listado;

use App\Support\Caja\Bingo\BingoCartonListadoFiltros;
use App\Support\Caja\Estacionamiento\ItemEstacionamientoListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class QueryRetornoListadoTest extends TestCase
{
    public function test_desde_request_ignora_empresa_id_del_body_sin_query_previa(): void
    {
        $request = Request::create('/caja/bingo/carton/1', 'PUT', [
            'empresa_id' => 2,
            'codigo' => 'C2000',
            'nombre' => 'Cartón $2.000',
        ]);

        $query = QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class);

        $this->assertSame([], $query);
    }

    public function test_desde_request_conserva_filtros_de_la_query_aunque_el_body_tenga_otra_empresa(): void
    {
        $request = Request::create(
            '/caja/bingo/carton/1?empresa_id=1&filtro_valor=abc&page=3',
            'PUT',
            ['empresa_id' => 2, 'codigo' => 'C2000'],
        );

        $query = QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class);

        $this->assertSame([
            'filtro_valor' => 'abc',
            'empresa_id' => 1,
            'page' => 3,
        ], $query);
    }

    public function test_desde_request_no_filtra_por_empresa_en_otro_modulo_con_mismo_patron(): void
    {
        $request = Request::create('/caja/estacionamiento/item/5', 'PUT', [
            'empresa_id' => 3,
            'codigo' => 'HORA',
        ]);

        $query = QueryRetornoListado::desdeRequest($request, ItemEstacionamientoListadoFiltros::class);

        $this->assertArrayNotHasKey('empresa_id', $query);
    }

    public function test_request_trae_contexto_index_detecta_empresa_id_en_query(): void
    {
        $request = Request::create('/caja/bingo/carton?empresa_id=1', 'GET');

        $this->assertTrue(QueryRetornoListado::requestTraeContextoIndex($request));
    }

    public function test_request_trae_contexto_index_no_usa_empresa_id_del_body(): void
    {
        $request = Request::create('/caja/bingo/carton/1', 'PUT', ['empresa_id' => 2]);

        $this->assertFalse(QueryRetornoListado::requestTraeContextoIndex($request));
    }

    public function test_desde_request_si_index_solo_retorna_filtros_cuando_hay_contexto_de_listado(): void
    {
        $sinContexto = Request::create('/caja/bingo/carton/1', 'PUT', ['empresa_id' => 2]);
        $this->assertSame([], QueryRetornoListado::desdeRequestSiIndex($sinContexto, BingoCartonListadoFiltros::class));

        $conContexto = Request::create('/caja/bingo/carton/1?empresa_id=1', 'PUT', ['empresa_id' => 2]);
        $this->assertSame(
            ['empresa_id' => 1],
            QueryRetornoListado::desdeRequestSiIndex($conContexto, BingoCartonListadoFiltros::class),
        );
    }
}
