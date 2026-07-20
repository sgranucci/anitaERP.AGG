<?php

namespace App\Support\Sueldos\Formula;

/**
 * Depurador de fórmulas: registra el árbol de evaluación paso a paso
 * (cada subexpresión con su valor resultante) para poder explicar
 * "por qué un concepto dio ese importe".
 *
 * Implementación con almacén plano + índices de padre (robusto, sin
 * referencias anidadas). Las ramas no evaluadas (short-circuit de si()/&&/||)
 * no aparecen, reflejando el cálculo real.
 */
class RastreadorFormula
{
    /** @var array<int, array{expr: string, tipo: string, valor: mixed, detalle: ?string, padre: ?int, hijos: array<int,int>}> */
    private array $nodos = [];

    /** @var array<int, int> Pila de índices en curso. */
    private array $pila = [];

    public function entrar(string $expr, string $tipo, ?string $detalle = null): void
    {
        $padre = empty($this->pila) ? null : $this->pila[count($this->pila) - 1];
        $idx = count($this->nodos);
        $this->nodos[$idx] = [
            'expr' => $expr,
            'tipo' => $tipo,
            'valor' => null,
            'detalle' => $detalle,
            'padre' => $padre,
            'hijos' => [],
        ];
        if ($padre !== null) {
            $this->nodos[$padre]['hijos'][] = $idx;
        }
        $this->pila[] = $idx;
    }

    /**
     * @param  mixed  $valor
     */
    public function salir($valor): void
    {
        if (empty($this->pila)) {
            return;
        }
        $idx = array_pop($this->pila);
        $this->nodos[$idx]['valor'] = $valor;
    }

    public function anotar(string $detalle): void
    {
        if (empty($this->pila)) {
            return;
        }
        $idx = $this->pila[count($this->pila) - 1];
        $this->nodos[$idx]['detalle'] = $detalle;
    }

    /**
     * Árbol anidado (para UI / JSON).
     *
     * @return array<int, array<string, mixed>>
     */
    public function arbol(): array
    {
        $raices = [];
        foreach ($this->nodos as $idx => $nodo) {
            if ($nodo['padre'] === null) {
                $raices[] = $this->construir($idx);
            }
        }

        return $raices;
    }

    /**
     * @return array<string, mixed>
     */
    private function construir(int $idx): array
    {
        $nodo = $this->nodos[$idx];
        $hijos = [];
        foreach ($nodo['hijos'] as $hijoIdx) {
            $hijos[] = $this->construir($hijoIdx);
        }

        return [
            'expr' => $nodo['expr'],
            'tipo' => $nodo['tipo'],
            'valor' => $nodo['valor'],
            'detalle' => $nodo['detalle'],
            'hijos' => $hijos,
        ];
    }

    /**
     * Render de texto indentado (consola / logs).
     */
    public function texto(): string
    {
        $lineas = [];
        foreach ($this->arbol() as $nodo) {
            $this->renderNodo($nodo, 0, $lineas);
        }

        return implode("\n", $lineas);
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @param  array<int, string>  $lineas
     */
    private function renderNodo(array $nodo, int $prof, array &$lineas): void
    {
        $sangria = str_repeat('  ', $prof);
        $valor = $this->valorTexto($nodo['valor']);
        $detalle = $nodo['detalle'] ? '   // '.$nodo['detalle'] : '';
        $lineas[] = $sangria.$nodo['expr'].' = '.$valor.$detalle;
        foreach ($nodo['hijos'] as $hijo) {
            $this->renderNodo($hijo, $prof + 1, $lineas);
        }
    }

    /**
     * @param  mixed  $valor
     */
    private function valorTexto($valor): string
    {
        if ($valor === null) {
            return '—';
        }
        if (is_bool($valor)) {
            return $valor ? 'verdadero' : 'falso';
        }
        if (is_string($valor)) {
            return '"'.$valor.'"';
        }
        if (is_int($valor) || (is_float($valor) && $valor === floor($valor) && abs($valor) < 1e15)) {
            return (string) (int) $valor;
        }
        if (is_float($valor)) {
            return rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
        }

        return (string) $valor;
    }
}
