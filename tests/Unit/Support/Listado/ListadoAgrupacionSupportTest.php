<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Listado;

use App\Support\Listado\ListadoAgrupacionSupport;
use PHPUnit\Framework\TestCase;

final class ListadoAgrupacionSupportTest extends TestCase
{
    /** @var array<string, array{column: string, label: string}> */
    private array $campos = [
        'zona' => ['column' => 'zonavta.nombre', 'label' => 'Zona'],
        'vendedor' => ['column' => 'vendedor.nombre', 'label' => 'Vendedor'],
        'id' => ['column' => 'cliente.id', 'label' => 'ID'],
    ];

    public function test_normalizar_max_dos_y_deduplica(): void
    {
        $agrupar = ListadoAgrupacionSupport::normalizar(
            ['zona', 'vendedor', 'zona', 'id'],
            $this->campos
        );
        $this->assertSame(['zona', 'vendedor'], $agrupar);
    }

    public function test_segmentar_inserta_cabeceras_y_conteo(): void
    {
        $filas = collect([
            (object) ['zona' => 'Norte', 'nombre' => 'A'],
            (object) ['zona' => 'Norte', 'nombre' => 'B'],
            (object) ['zona' => 'Sur', 'nombre' => 'C'],
        ]);

        $seg = ListadoAgrupacionSupport::segmentar(
            $filas,
            ['zona'],
            static fn ($row, string $campo): string => (string) ($row->{$campo} ?? ''),
            ['zona' => 'Zona']
        );

        $this->assertSame('header', $seg[0]['type']);
        $this->assertSame('Norte', $seg[0]['valor']);
        $this->assertSame(2, $seg[0]['count']);
        $this->assertSame('row', $seg[1]['type']);
        $this->assertSame('row', $seg[2]['type']);
        $this->assertSame('header', $seg[3]['type']);
        $this->assertSame('Sur', $seg[3]['valor']);
        $this->assertSame(1, $seg[3]['count']);
    }

    public function test_para_query_string(): void
    {
        $this->assertSame(
            ['group' => ['zona', 'vendedor']],
            ListadoAgrupacionSupport::paraQueryString(['zona', 'vendedor'])
        );
        $this->assertSame([], ListadoAgrupacionSupport::paraQueryString([]));
    }
}
