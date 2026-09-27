<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Listado;

use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use PHPUnit\Framework\TestCase;

final class ListadoQbeFormulaSupportTest extends TestCase
{
    /** @var array<string, array{column: string, type: string}> */
    private array $campos = [
        'nombre' => ['column' => 'cliente.nombre', 'type' => 'texto'],
        'codigo' => ['column' => 'cliente.codigo', 'type' => 'texto'],
        'id' => ['column' => 'cliente.id', 'type' => 'entero'],
    ];

    public function test_compila_length(): void
    {
        $c = ListadoQbeFormulaSupport::compilar('LENGTH({nombre})', $this->campos);
        $this->assertNotNull($c);
        $this->assertSame('entero', $c['type']);
        $this->assertStringContainsString('CHAR_LENGTH', $c['sql']);
        $this->assertStringContainsString('cliente.nombre', $c['sql']);
    }

    public function test_compila_concat_con_literal(): void
    {
        $c = ListadoQbeFormulaSupport::compilar("CONCAT({codigo},'-',{nombre})", $this->campos);
        $this->assertNotNull($c);
        $this->assertSame('texto', $c['type']);
        $this->assertStringContainsString('CONCAT(', $c['sql']);
        $this->assertCount(1, $c['bindings']);
        $this->assertSame('-', $c['bindings'][0]);
    }

    public function test_rechaza_sql_libre(): void
    {
        $this->assertNull(ListadoQbeFormulaSupport::compilar('cliente.nombre', $this->campos));
        $this->assertNull(ListadoQbeFormulaSupport::compilar('DROP TABLE x', $this->campos));
        $this->assertNull(ListadoQbeFormulaSupport::compilar('LENGTH({inexistente})', $this->campos));
        $this->assertNull(ListadoQbeFormulaSupport::compilar('FOO({nombre})', $this->campos));
    }

    public function test_normaliza_criterio_formula_en_qbe(): void
    {
        $qbe = ListadoQbeSupport::normalizar([
            [
                'campo' => '__formula__',
                'formula' => 'LENGTH({nombre})',
                'op' => 'mayor',
                'valor' => '10',
            ],
        ], $this->campos, static fn (string $op, string $campo): string => $op);

        $this->assertTrue(ListadoQbeSupport::tieneCriterios($qbe));
        $this->assertSame('LENGTH({nombre})', $qbe['grupos'][0]['criterios'][0]['formula']);
        $this->assertSame('mayor', $qbe['grupos'][0]['criterios'][0]['op']);
    }

    public function test_rechaza_formula_invalida_en_normalizar(): void
    {
        $qbe = ListadoQbeSupport::normalizar([
            [
                'campo' => '__formula__',
                'formula' => 'SELECT * FROM cliente',
                'op' => 'igual',
                'valor' => '1',
            ],
        ], $this->campos, static fn (string $op, string $campo): string => $op);

        $this->assertFalse(ListadoQbeSupport::tieneCriterios($qbe));
    }
}
