<?php

namespace App\Support\Stock;

/**
 * Rubros de existencias finales que contaduría abre en solapas.
 * Alimento, bebida y cigarrillo salen del tipo de artículo.
 * Descartables, librería y limpieza salen del uso. Farmacia sale de la categoría.
 * El orden es el de la planilla de totales (sector / costo).
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
            ['clave' => 'alimento', 'titulo' => 'Alimentos'],
            ['clave' => 'bebidas', 'titulo' => 'Bebidas'],
            ['clave' => 'descartables', 'titulo' => 'Descartables'],
            ['clave' => 'cigarrillos', 'titulo' => 'Cigarrillos'],
            ['clave' => 'limpieza', 'titulo' => 'Limpieza'],
            ['clave' => 'farmacia', 'titulo' => 'Farmacia'],
            ['clave' => 'libreria', 'titulo' => 'Librería'],
            ['clave' => self::OTROS, 'titulo' => 'Otros'],
        ];
    }

    /**
     * El comentario del recuento manda la solapa cuando nombra el rubro
     * (almacén, bebidas, cigarrillos). Limpieza y descartables juntos se parten por el artículo.
     *
     * @param  array<string, mixed>  $fila
     */
    public static function claveDesdeRecuento(string $comentario, array $fila): string
    {
        $rubro = self::rubroDelComentario($comentario);
        if ($rubro === null || $rubro === 'limpieza_descartables') {
            return self::clave($fila);
        }

        return $rubro;
    }

    /**
     * Rubro que el comentario declara para todo el recuento.
     * Limpieza y descartables contados juntos quedan en un mismo grupo
     * y después se abren por artículo. Sin palabra de rubro, null.
     */
    public static function rubroDelComentario(string $comentario): ?string
    {
        $texto = self::normalizar($comentario);
        if ($texto === '') {
            return null;
        }
        if (str_contains($texto, 'CIGARRILLO')) {
            return 'cigarrillos';
        }
        if (str_contains($texto, 'BEBIDA')) {
            return 'bebidas';
        }
        if (str_contains($texto, 'LIMPIEZA') && str_contains($texto, 'DESCARTABLE')) {
            return 'limpieza_descartables';
        }
        if (str_contains($texto, 'ALMACEN') || str_contains($texto, 'ALIMENTO')) {
            return 'alimento';
        }
        if (str_contains($texto, 'LIMPIEZA')) {
            return 'limpieza';
        }
        if (str_contains($texto, 'DESCARTABLE')) {
            return 'descartables';
        }

        return null;
    }

    public static function textoMencionaCigarrillos(string $texto): bool
    {
        return str_contains(self::normalizar($texto), 'CIGARRILLO');
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
