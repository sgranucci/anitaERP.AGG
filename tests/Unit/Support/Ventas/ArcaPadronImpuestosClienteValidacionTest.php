<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use Tests\TestCase;

class ArcaPadronImpuestosClienteValidacionTest extends TestCase
{
    public function test_no_aplica_para_condicion_iva_no_controlada(): void
    {
        $resultado = ArcaPadronImpuestosClienteValidacion::validar(3, [
            'impuestos' => [],
            'error' => null,
        ]);

        $this->assertFalse($resultado['aplica']);
        $this->assertTrue($resultado['ok']);
    }

    public function test_responsable_inscripto_requiere_iva_activo(): void
    {
        $resultado = ArcaPadronImpuestosClienteValidacion::validar(1, [
            'impuestos' => [
                [
                    'idImpuesto' => 30,
                    'descripcionImpuesto' => 'IVA',
                    'estadoImpuesto' => 'NI',
                    'fuente' => 'regimen_general',
                ],
            ],
            'error' => null,
        ]);

        $this->assertTrue($resultado['aplica']);
        $this->assertFalse($resultado['ok']);
        $this->assertTrue($resultado['debe_suspender']);
    }

    public function test_responsable_inscripto_ok_con_iva_activo(): void
    {
        $resultado = ArcaPadronImpuestosClienteValidacion::validar(1, [
            'impuestos' => [
                [
                    'idImpuesto' => 30,
                    'descripcionImpuesto' => 'IVA',
                    'estadoImpuesto' => 'AC',
                    'fuente' => 'regimen_general',
                ],
            ],
            'error' => null,
        ]);

        $this->assertTrue($resultado['ok']);
        $this->assertFalse($resultado['debe_suspender']);
    }

    public function test_monotributo_requiere_impuesto_activo_en_bloque_monotributo(): void
    {
        $resultado = ArcaPadronImpuestosClienteValidacion::validar(4, [
            'impuestos' => [
                [
                    'idImpuesto' => 20,
                    'descripcionImpuesto' => 'MONOTRIBUTO',
                    'estadoImpuesto' => 'AC',
                    'fuente' => 'regimen_general',
                ],
            ],
            'datosMonotributo' => [
                'categoriaMonotributo' => ['idCategoria' => 8],
            ],
            'error' => null,
        ]);

        $this->assertTrue($resultado['aplica']);
        $this->assertFalse($resultado['ok']);
    }

    public function test_monotributo_ok_con_impuesto_en_bloque_y_categoria(): void
    {
        $resultado = ArcaPadronImpuestosClienteValidacion::validar(4, [
            'impuestos' => [
                [
                    'idImpuesto' => 20,
                    'descripcionImpuesto' => 'MONOTRIBUTO',
                    'estadoImpuesto' => 'AC',
                    'fuente' => 'monotributo',
                ],
            ],
            'datosMonotributo' => [
                'categoriaMonotributo' => ['idCategoria' => 8],
            ],
            'error' => null,
        ]);

        $this->assertTrue($resultado['ok']);
    }
}
