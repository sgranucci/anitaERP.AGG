<?php

namespace App\Support\Stock;

/**
 * Rubros de existencias finales que contaduría abre en solapas.
 * Alimento, bebida y cigarrillo salen del tipo de artículo.
 * Descartables, librería y limpieza salen del uso. Farmacia sale de la categoría.
 */
final class ContaduriaInventarioRubroSupport
{
    public const OTROS = 'otros';

    /**
     * @return list<array{clave:string,titulo:string}>
     */
    public static function rubros(): array
    {
        return [
            ['clave' => 'alimento', 'titulo' => 'Alimento'],
            ['clave' => 'bebidas', 'titulo' => 'Bebidas'],
            ['clave' => 'cigarrillos', 'titulo' => 'Cigarrillos'],
            ['clave' => 'descartables', 'titulo' => 'Descartables'],
            ['clave' => 'farmacia', 'titulo' => 'Farmacia'],
            ['clave' => 'libreria', 'titulo' => 'Librería'],
            ['clave' => 'limpieza', 'titulo' => 'Limpieza'],
            ['clave' => self::OTROS, 'titulo' => 'Otros'],
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function clave(array $fila): string
    {
        $tipo = self::normalizar((string) ($fila['tipo'] ?? ''));
        if ($tipo === 'CIGARRILLO') {
            return 'cigarrillos';
        }
        if ($tipo === 'BEBIDA') {
            return 'bebidas';
        }
        if ($tipo === 'ALIMENTO') {
            return 'alimento';
        }

        $uso = self::normalizar((string) ($fila['uso'] ?? ''));
        if ($uso === 'DESCARTABLES') {
            return 'descartables';
        }
        if ($uso === 'LIBRERIA') {
            return 'libreria';
        }
        if ($uso === 'LIMPIEZA') {
            return 'limpieza';
        }

        $categoria = self::normalizar((string) ($fila['categoria'] ?? ''));
        if (str_contains($categoria, 'FARMACIA')) {
            return 'farmacia';
        }

        return self::OTROS;
    }

    public static function titulo(string $clave): string
    {
        foreach (self::rubros() as $rubro) {
            if ($rubro['clave'] === $clave) {
                return $rubro['titulo'];
            }
        }

        return 'Otros';
    }

    private static function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));

        return strtr($valor, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
        ]);
    }
}
