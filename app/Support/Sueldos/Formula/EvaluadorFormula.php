<?php

namespace App\Support\Sueldos\Formula;

/**
 * Fachada del motor de fórmulas: parsea (con caché de AST por texto) y evalúa,
 * opcionalmente devolviendo el rastro de cálculo (depurador).
 *
 * Uso:
 *   $motor = new EvaluadorFormula();
 *   $valor = $motor->evaluar('redondear(empleado.sueldo_basico * 0.11, 2)', $entorno);
 *   [$valor, $rastro] = $motor->evaluarConRastro($formula, $entorno);
 */
class EvaluadorFormula
{
    /** @var array<string, array<string, mixed>> Caché de AST por fórmula. */
    private array $cacheAst = [];

    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser;
    }

    /**
     * @return array<string, mixed>
     */
    public function compilar(string $formula): array
    {
        $clave = md5($formula);
        if (! isset($this->cacheAst[$clave])) {
            $this->cacheAst[$clave] = $this->parser->parsear($formula);
        }

        return $this->cacheAst[$clave];
    }

    /**
     * Valida la sintaxis sin evaluar. Devuelve null si es válida o el mensaje de error.
     */
    public function validar(string $formula): ?string
    {
        try {
            $this->compilar($formula);

            return null;
        } catch (FormulaException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return float|int|string|bool
     */
    public function evaluar(string $formula, EntornoFormula $entorno)
    {
        $ast = $this->compilar($formula);

        return (new Evaluador($entorno))->evaluar($ast);
    }

    /**
     * Evalúa y devuelve también el árbol de rastreo para depurar.
     *
     * @return array{0: float|int|string|bool, 1: RastreadorFormula}
     */
    public function evaluarConRastro(string $formula, EntornoFormula $entorno): array
    {
        $ast = $this->compilar($formula);
        $rastro = new RastreadorFormula;
        $valor = (new Evaluador($entorno, $rastro))->evaluar($ast);

        return [$valor, $rastro];
    }
}
