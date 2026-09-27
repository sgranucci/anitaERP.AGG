<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Listado;

use App\Support\Listado\ListadoOrdenamientoSupport;
use PHPUnit\Framework\TestCase;

final class ListadoOrdenamientoSupportTest extends TestCase
{
    /** @var array<string, array{column: string, label: string}> */
    private array $campos = [
        'id' => ['column' => 'cliente.id', 'label' => 'ID'],
        'nombre' => ['column' => 'cliente.nombre', 'label' => 'Nombre'],
        'zona' => ['column' => 'zonavta.nombre', 'label' => 'Zona'],
    ];

    public function test_normalizar_lista_y_deduplica(): void
    {
        $orden = ListadoOrdenamientoSupport::normalizar([
            ['campo' => 'zona', 'dir' => 'asc'],
            ['campo' => 'nombre', 'dir' => 'DESC'],
            ['campo' => 'zona', 'dir' => 'desc'],
            ['campo' => 'hacker', 'dir' => 'asc'],
        ], $this->campos);

        $this->assertSame([
            ['campo' => 'zona', 'dir' => 'asc'],
            ['campo' => 'nombre', 'dir' => 'desc'],
        ], $orden);
    }

    public function test_rechaza_columna_sql_insegura(): void
    {
        $this->assertFalse(ListadoOrdenamientoSupport::esColumnaSqlSegura('cliente.id; drop'));
        $this->assertFalse(ListadoOrdenamientoSupport::esColumnaSqlSegura('id'));
        $this->assertTrue(ListadoOrdenamientoSupport::esColumnaSqlSegura('cliente.nombre'));
    }

    public function test_toggle_primario_invierte_o_promueve(): void
    {
        $actual = [
            ['campo' => 'nombre', 'dir' => 'asc'],
            ['campo' => 'id', 'dir' => 'desc'],
        ];

        $mismo = ListadoOrdenamientoSupport::togglePrimario($actual, 'nombre', $this->campos);
        $this->assertSame('desc', $mismo[0]['dir']);
        $this->assertSame('nombre', $mismo[0]['campo']);

        $otro = ListadoOrdenamientoSupport::togglePrimario($actual, 'zona', $this->campos);
        $this->assertSame('zona', $otro[0]['campo']);
        $this->assertSame('asc', $otro[0]['dir']);
        $this->assertSame('nombre', $otro[1]['campo']);
    }

    public function test_para_query_string(): void
    {
        $qs = ListadoOrdenamientoSupport::paraQueryString([
            ['campo' => 'zona', 'dir' => 'asc'],
            ['campo' => 'nombre', 'dir' => 'desc'],
        ]);

        $this->assertSame([
            'sort' => [
                0 => ['campo' => 'zona', 'dir' => 'asc'],
                1 => ['campo' => 'nombre', 'dir' => 'desc'],
            ],
        ], $qs);
    }
}
