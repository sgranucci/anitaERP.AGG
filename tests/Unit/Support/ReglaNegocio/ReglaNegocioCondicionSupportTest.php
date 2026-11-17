<?php

declare(strict_types=1);

namespace Tests\Unit\Support\ReglaNegocio;

use App\Support\Configuracion\AvisoReglaCondicionSupport;
use App\Support\ReglaNegocio\ReglaNegocioCondicionSupport;
use PHPUnit\Framework\TestCase;

class ReglaNegocioCondicionSupportTest extends TestCase
{
    public function test_vacio_cumple(): void
    {
        $this->assertTrue(ReglaNegocioCondicionSupport::cumple(null, []));
        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([], ['monto' => 1]));
    }

    public function test_and_or_anidado(): void
    {
        $condiciones = [
            'logic' => 'and',
            'rules' => [
                ['campo' => 'monto', 'op' => 'gte', 'valor' => 1000],
                [
                    'logic' => 'or',
                    'rules' => [
                        ['campo' => 'tipo', 'op' => 'eq', 'valor' => 'TRA'],
                        ['campo' => 'tipo', 'op' => 'eq', 'valor' => 'TM'],
                    ],
                ],
            ],
        ];

        $this->assertTrue(ReglaNegocioCondicionSupport::cumple($condiciones, [
            'monto' => 1500,
            'tipo' => 'TRA',
        ]));
        $this->assertFalse(ReglaNegocioCondicionSupport::cumple($condiciones, [
            'monto' => 1500,
            'tipo' => 'OTRO',
        ]));
        $this->assertFalse(ReglaNegocioCondicionSupport::cumple($condiciones, [
            'monto' => 500,
            'tipo' => 'TRA',
        ]));
    }

    public function test_operadores_numericos_y_lista(): void
    {
        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'n', 'op' => 'gt', 'valor' => 10]],
        ], ['n' => 11]));

        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'tipo', 'op' => 'in', 'valor' => 'A,B,C']],
        ], ['tipo' => 'B']));

        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'tipo', 'op' => 'not_in', 'valor' => ['X', 'Y']]],
        ], ['tipo' => 'Z']));
    }

    public function test_contains_empty_y_dot_path(): void
    {
        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'texto', 'op' => 'contains', 'valor' => 'ferli']],
        ], ['texto' => 'Cliente Ferli SA']));

        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'nota', 'op' => 'empty', 'valor' => null]],
        ], ['nota' => '']));

        $this->assertTrue(ReglaNegocioCondicionSupport::cumple([
            'logic' => 'and',
            'rules' => [['campo' => 'meta.urgencia', 'op' => 'eq', 'valor' => 'alta']],
        ], ['meta' => ['urgencia' => 'alta']]));
    }

    public function test_legacy_mapa_plano(): void
    {
        $this->assertTrue(ReglaNegocioCondicionSupport::cumple(
            ['empresa_id' => 1, 'tipo' => ['OC', 'RE']],
            ['empresa_id' => 1, 'tipo' => 'OC']
        ));
        $this->assertFalse(ReglaNegocioCondicionSupport::cumple(
            ['empresa_id' => 1, 'tipo' => ['OC', 'RE']],
            ['empresa_id' => 1, 'tipo' => 'FA']
        ));
    }

    public function test_wrapper_aviso_delega(): void
    {
        $condiciones = [
            'logic' => 'and',
            'rules' => [['campo' => 'monto', 'op' => 'eq', 'valor' => 42]],
        ];
        $ctx = ['monto' => 42];

        $this->assertSame(
            ReglaNegocioCondicionSupport::cumple($condiciones, $ctx),
            AvisoReglaCondicionSupport::cumple($condiciones, $ctx)
        );
    }
}
