<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Listado;

use App\Support\Listado\ListadoQbeSupport;
use PHPUnit\Framework\TestCase;

final class ListadoQbeSupportTest extends TestCase
{
    /** @var array<string, array{column: string, type: string}> */
    private array $campos = [
        'nombre' => ['column' => 'cliente.nombre', 'type' => 'texto'],
        'id' => ['column' => 'cliente.id', 'type' => 'entero'],
        'estado' => ['column' => 'cliente.estado', 'type' => 'texto'],
    ];

    private function normOp(string $op, string $campo): string
    {
        $type = $this->campos[$campo]['type'] ?? 'texto';
        $ok = $type === 'entero'
            ? ['igual', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'entre', 'vacio']
            : ['contiene', 'no_contiene', 'empieza', 'termina', 'igual', 'distinto', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'entre', 'vacio'];

        return in_array($op, $ok, true) ? $op : $ok[0];
    }

    public function test_legacy_plano_a_un_grupo_and(): void
    {
        $qbe = ListadoQbeSupport::normalizar([
            ['campo' => 'nombre', 'op' => 'contiene', 'valor' => 'acme'],
            ['campo' => 'estado', 'op' => 'igual', 'valor' => '0'],
        ], $this->campos, fn ($op, $c) => $this->normOp($op, $c));

        $this->assertTrue(ListadoQbeSupport::tieneCriterios($qbe));
        $this->assertCount(1, $qbe['grupos']);
        $this->assertSame('and', $qbe['grupos'][0]['logic']);
        $this->assertFalse($qbe['grupos'][0]['not']);
        $this->assertCount(2, $qbe['grupos'][0]['criterios']);
    }

    public function test_grupos_or_y_not(): void
    {
        $qbe = ListadoQbeSupport::normalizar([
            'entre_grupos' => 'or',
            'grupos' => [
                [
                    'logic' => 'and',
                    'not' => true,
                    'criterios' => [
                        ['campo' => 'nombre', 'op' => 'contiene', 'valor' => 'x'],
                    ],
                ],
                [
                    'logic' => 'or',
                    'not' => 0,
                    'criterios' => [
                        ['campo' => 'id', 'op' => 'entre', 'valor' => '1', 'valor_hasta' => '10'],
                    ],
                ],
            ],
        ], $this->campos, fn ($op, $c) => $this->normOp($op, $c));

        $this->assertSame('or', $qbe['entre_grupos']);
        $this->assertTrue($qbe['grupos'][0]['not']);
        $this->assertSame('or', $qbe['grupos'][1]['logic']);
        $this->assertSame('entre', $qbe['grupos'][1]['criterios'][0]['op']);
    }

    public function test_para_query_string_aplana_grupo_simple(): void
    {
        $qbe = [
            'entre_grupos' => 'and',
            'grupos' => [[
                'logic' => 'and',
                'not' => false,
                'criterios' => [
                    ['campo' => 'nombre', 'op' => 'contiene', 'valor' => 'a'],
                ],
            ]],
        ];
        $qs = ListadoQbeSupport::paraQueryString($qbe);
        $this->assertArrayHasKey('qbe', $qs);
        $this->assertArrayIsList($qs['qbe']);
        $this->assertSame('nombre', $qs['qbe'][0]['campo']);
    }

    public function test_vacio_sin_criterios(): void
    {
        $this->assertFalse(ListadoQbeSupport::tieneCriterios(ListadoQbeSupport::vacio()));
        $this->assertSame([], ListadoQbeSupport::paraQueryString(ListadoQbeSupport::vacio()));
    }
}
