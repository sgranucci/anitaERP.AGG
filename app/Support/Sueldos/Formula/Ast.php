<?php

namespace App\Support\Sueldos\Formula;

/**
 * Nodos del AST (representados como arrays livianos) y utilidades para
 * renderizarlos a texto (para el rastreador/depurador).
 *
 * Tipos de nodo:
 *  - ['t'=>'num','v'=>float]
 *  - ['t'=>'txt','v'=>string]
 *  - ['t'=>'var','nombre'=>string]              (ruta con puntos)
 *  - ['t'=>'call','nombre'=>string,'args'=>[]]
 *  - ['t'=>'un','op'=>string,'e'=>nodo]
 *  - ['t'=>'bin','op'=>string,'a'=>nodo,'b'=>nodo]
 *  - ['t'=>'ter','cond'=>nodo,'a'=>nodo,'b'=>nodo]
 */
class Ast
{
    /**
     * Renderiza un nodo (subárbol) como expresión legible.
     *
     * @param  array<string, mixed>  $n
     */
    public static function aTexto(array $n): string
    {
        switch ($n['t']) {
            case 'num':
                return self::numTexto((float) $n['v']);
            case 'txt':
                return '"'.$n['v'].'"';
            case 'var':
                return (string) $n['nombre'];
            case 'call':
                $args = array_map(fn ($a) => self::aTexto($a), $n['args']);

                return $n['nombre'].'('.implode(', ', $args).')';
            case 'un':
                return $n['op'].self::aTexto($n['e']);
            case 'bin':
                return '('.self::aTexto($n['a']).' '.$n['op'].' '.self::aTexto($n['b']).')';
            case 'ter':
                return '('.self::aTexto($n['cond']).' ? '.self::aTexto($n['a']).' : '.self::aTexto($n['b']).')';
        }

        return '?';
    }

    private static function numTexto(float $v): string
    {
        if ($v === floor($v) && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }
}
