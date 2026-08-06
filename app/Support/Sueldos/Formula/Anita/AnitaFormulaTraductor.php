<?php

namespace App\Support\Sueldos\Formula\Anita;

use App\Support\Sueldos\Formula\Ast;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;

/**
 * Traductor de fórmulas del parser legacy Anita (habformula) a la sintaxis del
 * motor de fórmulas del ERP (App\Support\Sueldos\Formula).
 *
 * Estrategia:
 *  - Cada concepto Anita es una lista de líneas (habf_linea ASC).
 *  - Líneas `NOMBRE := EXPR`: se guardan como variable temporal y se INLINEAN
 *    donde se usen luego (el ERP no tiene asignaciones ni let-bindings).
 *  - `CA := EXPR` → fórmula de cantidad; `VA := EXPR` → fórmula de valor.
 *  - La última línea sin `:=` → fórmula de importe.
 *  - Se parsea Anita a un AST compatible con el ERP y se renderiza con Ast::aTexto,
 *    luego se valida con el Parser del ERP.
 *  - Todo lo que no tiene equivalente exacto se reporta (requiere_revision).
 *
 * NO ejecuta la fórmula ni escribe en base: solo traduce y reporta.
 */
class AnitaFormulaTraductor
{
    /** @var array<int, array{t: string, v: string, pos: int}> */
    private array $tokens = [];

    private int $pos = 0;

    /** @var array<string, array<string, mixed>> Variables temporales (_V1) => AST inlineable. */
    private array $temporales = [];

    private ResultadoTraduccion $resultado;

    private EvaluadorFormula $motorErp;

    public function __construct()
    {
        $this->motorErp = new EvaluadorFormula;
    }

    /**
     * Traduce un concepto completo a partir de sus líneas de habformula.
     *
     * @param  list<string>  $lineas  ordenadas por habf_linea ASC
     */
    public function traducirConcepto(array $lineas): ResultadoTraduccion
    {
        $this->resultado = new ResultadoTraduccion;
        $this->temporales = [];
        $this->resultado->lineasOriginales = array_values($lineas);

        $importeAst = null;
        $importeContador = 0;

        $ultimaAsignacionAst = null;
        $ultimaAsignacionNombre = null;

        foreach ($lineas as $original) {
            $linea = $this->sanitizarLineaAnita((string) $original);
            if ($linea === '') {
                continue;
            }
            // Anita normaliza a mayúsculas antes de parsear.
            $linea = strtoupper($linea);

            // '#' → línea comentario (Anita devuelve 0): se ignora.
            if (str_starts_with($linea, '#')) {
                continue;
            }

            [$lhs, $rhs] = $this->separarAsignacion($linea);

            try {
                $ast = $this->parsearExpresion($rhs);
            } catch (FormulaException $e) {
                // Una línea rota no tumba el concepto si hay otra línea de importe válida.
                $this->resultado->agregarAdvertencia("No se pudo parsear «{$original}»: ".$e->getMessage());

                continue;
            }

            if ($lhs === null) {
                $importeAst = $ast;
                $importeContador++;

                continue;
            }

            if ($lhs === 'CA') {
                $this->resultado->formulaCantidad = $this->render($ast);

                continue;
            }
            if ($lhs === 'VA') {
                $this->resultado->formulaValor = $this->render($ast);

                continue;
            }

            if (str_starts_with($lhs, '__')) {
                $this->resultado->agregarNoTraducible(
                    "Variable persistente entre conceptos «{$lhs}»: el ERP no mantiene estado entre conceptos (revisar)."
                );
            }

            // Temporal inlineable (_V1, _R, V:=…, etc.)
            $this->temporales[$lhs] = $ast;
            $ultimaAsignacionAst = $ast;
            $ultimaAsignacionNombre = $lhs;
        }

        if ($importeContador > 1) {
            $this->resultado->agregarAdvertencia(
                "El concepto tenía {$importeContador} líneas de importe (sin :=); se usó la última (comportamiento Anita)."
            );
        }

        if ($importeAst === null && $ultimaAsignacionAst !== null) {
            // Conceptos que solo tienen V:=… / _Vn:=… sin línea final de importe.
            if (isset($this->temporales['V'])) {
                $importeAst = $this->temporales['V'];
                $this->resultado->agregarAdvertencia('Se usó la asignación V:= como fórmula de importe (no había línea sin :=).');
            } else {
                $importeAst = $ultimaAsignacionAst;
                $this->resultado->agregarAdvertencia(
                    "Se usó la última asignación «{$ultimaAsignacionNombre}:=» como fórmula de importe (no había línea sin :=)."
                );
            }
        }

        if ($importeAst !== null) {
            $this->resultado->formula = $this->render($importeAst);
        } elseif ($this->resultado->formulaCantidad === null && $this->resultado->formulaValor === null) {
            $this->resultado->traducible = false;
            $this->resultado->agregarAdvertencia('No se encontró una línea de importe (sin :=).');
        }

        $this->validarSalidas();

        return $this->resultado;
    }

    /**
     * Conveniencia: traduce una expresión Anita de una sola línea.
     */
    public function traducirExpresion(string $expr): ResultadoTraduccion
    {
        return $this->traducirConcepto([$expr]);
    }

    /**
     * Limpia basura típica de Informix (padding con `_`) y corrige typos frecuentes
     * en fórmulas Anita cargadas a mano.
     */
    private function sanitizarLineaAnita(string $linea): string
    {
        $linea = trim($linea);
        if ($linea === '') {
            return '';
        }
        // Padding al final del campo char(75): subrayados sueltos.
        $linea = rtrim($linea, "_ \t");
        // Basura al inicio (ej. `>(_V5+_V6+_V7`).
        $linea = ltrim($linea, "> \t");
        // Typo asignación con paréntesis de más: `(_R:=ACIM(0)+_T` → `_R:=ACIM(0)+_T`
        if (preg_match('/^\(+([A-Z_][A-Z0-9_]*\s*:=.+)$/i', $linea, $m)) {
            $linea = $m[1];
        }
        // Dígito pegado a temporal `_Vx` sin coma (error frecuente en fórmulas Anita).
        $linea = preg_replace('/(\d)(_[A-Za-z][A-Za-z0-9_]*)/', '$1,$2', $linea) ?? $linea;
        // Typo `CA:P=(680)` → `CA:=P(680)` (falta `=` del operador :=).
        $linea = preg_replace('/\b([A-Z]+):([A-Z])=\(/i', '$1:=$2(', $linea) ?? $linea;
        // Leyenda / comentario sin expresión matemática → 0.
        $upper = strtoupper($linea);
        if (preg_match('/^[A-ZÁÉÍÓÚÜÑ][A-ZÁÉÍÓÚÜÑ0-9 .]+$/u', $upper)
            && ! preg_match('/[+\-*\/^()<>=]/', $upper)
            && ! preg_match('/\b(IF|IM|V|F|IC|VC|BR|CA|VA|ACIM|CANTAS)\b/', $upper)) {
            return '0';
        }
        // Balanceo básico de paréntesis (sobra o falta al final).
        $abre = substr_count($linea, '(');
        $cierra = substr_count($linea, ')');
        if ($abre > $cierra) {
            $linea .= str_repeat(')', $abre - $cierra);
        } elseif ($cierra > $abre) {
            $extra = $cierra - $abre;
            while ($extra > 0 && str_ends_with($linea, ')')) {
                $linea = substr($linea, 0, -1);
                $extra--;
            }
        }
        $linea = trim($linea);
        // Solo basura de paréntesis → 0.
        if ($linea === '' || $linea === '(' || $linea === ')' || $linea === '()') {
            return '0';
        }

        return $linea;
    }

    /**
     * Separa `NOMBRE := EXPR`. Devuelve [null, linea] si no hay asignación.
     *
     * @return array{0: ?string, 1: string}
     */
    private function separarAsignacion(string $linea): array
    {
        $p = strpos($linea, ':=');
        if ($p === false) {
            return [null, $linea];
        }
        $lhs = trim(substr($linea, 0, $p));
        $rhs = trim(substr($linea, $p + 2));
        if (! preg_match('/^[A-Z_][A-Z0-9_]*$/', $lhs)) {
            // No es una asignación válida: tratar la línea completa como expresión.
            return [null, $linea];
        }

        return [$lhs, $rhs];
    }

    // ---------------------------------------------------------------------
    // Render + validación
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $ast
     */
    private function render(array $ast): string
    {
        return Ast::aTexto($ast);
    }

    private function validarSalidas(): void
    {
        foreach (['formula', 'formulaCantidad', 'formulaValor'] as $campo) {
            $txt = $this->resultado->$campo;
            if ($txt === null || $txt === '') {
                continue;
            }
            $error = $this->motorErp->validar($txt);
            if ($error !== null) {
                $this->resultado->traducible = false;
                $this->resultado->agregarAdvertencia("La salida ERP no valida ({$campo}): {$error} → {$txt}");
            }
        }
    }

    // ---------------------------------------------------------------------
    // Parser recursivo descendente Anita → AST ERP
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function parsearExpresion(string $expr): array
    {
        $this->tokens = (new AnitaLexer)->tokenizar($expr);
        $this->pos = 0;

        if ($this->actual()['t'] === AnitaLexer::FIN) {
            // Línea vacía tras comentario, etc.: valor 0.
            return ['t' => 'num', 'v' => 0.0];
        }

        $nodo = $this->nivelOr();

        if ($this->actual()['t'] !== AnitaLexer::FIN) {
            $tk = $this->actual();
            throw FormulaException::sintaxis("token Anita inesperado '{$tk['v']}'", $tk['pos']);
        }

        return $nodo;
    }

    private function nivelOr(): array
    {
        $izq = $this->nivelAnd();
        while ($this->esPalabra('OR')) {
            $this->avanzar();
            $der = $this->nivelAnd();
            $izq = ['t' => 'bin', 'op' => '||', 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function nivelAnd(): array
    {
        $izq = $this->nivelComparacion();
        while ($this->esPalabra('AND')) {
            $this->avanzar();
            $der = $this->nivelComparacion();
            $izq = ['t' => 'bin', 'op' => '&&', 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function nivelComparacion(): array
    {
        $izq = $this->nivelSuma();
        while (true) {
            $tk = $this->actual();
            if ($tk['t'] !== AnitaLexer::OP) {
                break;
            }
            $op = $tk['v'];
            $mapa = ['=' => '==', '<>' => '!=', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>='];
            if (! isset($mapa[$op])) {
                break;
            }
            $this->avanzar();
            $der = $this->nivelSuma();
            $izq = ['t' => 'bin', 'op' => $mapa[$op], 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function nivelSuma(): array
    {
        $izq = $this->nivelProducto();
        while ($this->esOp('+') || $this->esOp('-')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->nivelProducto();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function nivelProducto(): array
    {
        $izq = $this->nivelPotencia();
        while ($this->esOp('*') || $this->esOp('/')) {
            $op = $this->actual()['v'];
            $this->avanzar();
            $der = $this->nivelPotencia();
            $izq = ['t' => 'bin', 'op' => $op, 'a' => $izq, 'b' => $der];
        }

        return $izq;
    }

    private function nivelPotencia(): array
    {
        $base = $this->nivelUnario();
        if ($this->esOp('^')) {
            $this->avanzar();
            $exp = $this->nivelPotencia(); // asociativo a derecha

            return ['t' => 'bin', 'op' => '^', 'a' => $base, 'b' => $exp];
        }

        return $base;
    }

    private function nivelUnario(): array
    {
        if ($this->esOp('-')) {
            $this->avanzar();

            return ['t' => 'un', 'op' => '-', 'e' => $this->nivelUnario()];
        }
        if ($this->esOp('+')) {
            $this->avanzar();

            return $this->nivelUnario();
        }

        return $this->primario();
    }

    private function primario(): array
    {
        $tk = $this->actual();

        if ($tk['t'] === AnitaLexer::NUM) {
            $this->avanzar();

            return ['t' => 'num', 'v' => (float) $tk['v']];
        }

        if ($tk['t'] === AnitaLexer::PA) {
            $this->avanzar();
            $nodo = $this->nivelOr();
            $this->esperar(AnitaLexer::PC, ')');

            return $nodo;
        }

        if ($tk['t'] === AnitaLexer::ID) {
            $nombre = strtoupper($tk['v']);
            $this->avanzar();

            if ($this->actual()['t'] === AnitaLexer::PA) {
                $this->avanzar();
                $args = [];
                if ($this->actual()['t'] !== AnitaLexer::PC) {
                    $args[] = $this->nivelOr();
                    while ($this->actual()['t'] === AnitaLexer::COMA) {
                        $this->avanzar();
                        $args[] = $this->nivelOr();
                    }
                }
                $this->esperar(AnitaLexer::PC, ')');

                return $this->traducirLlamada($nombre, $args);
            }

            return $this->traducirVariable($nombre);
        }

        throw FormulaException::sintaxis("token Anita inesperado '{$tk['v']}'", $tk['pos']);
    }

    // ---------------------------------------------------------------------
    // Traducción de llamadas y variables
    // ---------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function traducirLlamada(string $nombre, array $args): array
    {
        if (! in_array($nombre, $this->resultado->funcionesUsadas, true)) {
            $this->resultado->funcionesUsadas[] = $nombre;
        }

        $map = AnitaFuncionMapa::funcion($nombre);

        if ($map === null) {
            // Función Anita desconocida: emitir call con nombre en minúsculas y marcar.
            $erp = strtolower($nombre);
            $this->resultado->agregarNoTraducible("Función Anita sin equivalente: {$nombre}()");

            return ['t' => 'call', 'nombre' => $erp, 'args' => $args];
        }

        // Manejo especial
        if (isset($map['especial'])) {
            return $this->traducirEspecial($map['especial'], $nombre, $args);
        }

        $erp = $map['erp'];

        // Anita IF(cond, a) → si(cond, a, 0)
        if ($nombre === 'IF' && count($args) === 2) {
            $args[] = ['t' => 'num', 'v' => 0.0];
            $this->resultado->agregarAdvertencia('«IF(cond, a)» → «si(cond, a, 0)» (falso implícito).');
        }
        // Typo frecuente en habformula: IF(IF(...)) con un solo argumento → unwrap.
        if ($nombre === 'IF' && count($args) === 1) {
            $this->resultado->agregarAdvertencia('«IF(expr)» de 1 argumento → se usa expr (IF redundante en Anita).');

            return $args[0];
        }

        if (! $map['exacto']) {
            $nota = $map['nota'] ?? '';
            $this->resultado->agregarAdvertencia("«{$nombre}()» → «{$erp}()» (aprox): {$nota}");
            if (! AnitaFuncionMapa::erpConoceFuncion($erp)) {
                $this->resultado->agregarNoTraducible("Falta implementar función de dominio ERP: {$erp}() (por {$nombre})");
            }
        }

        return ['t' => 'call', 'nombre' => $erp, 'args' => $args];
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @return array<string, mixed>
     */
    private function traducirEspecial(string $clave, string $nombre, array $args): array
    {
        switch ($clave) {
            case 'SQR':
                // SQR(x) → x^2
                $x = $args[0] ?? ['t' => 'num', 'v' => 0.0];

                return ['t' => 'bin', 'op' => '^', 'a' => $x, 'b' => ['t' => 'num', 'v' => 2.0]];

            case 'REDON':
                // REDON(x) → redondear(x, 2) (aproximación del redondeo por tabla)
                $this->resultado->agregarAdvertencia('«REDON()» → «redondear(x, 2)» (redondeo final por tabla, revisar).');
                $x = $args[0] ?? ['t' => 'num', 'v' => 0.0];

                return ['t' => 'call', 'nombre' => 'redondear', 'args' => [$x, ['t' => 'num', 'v' => 2.0]]];

            case 'B':
                // B(1)=básico, B(2)=jornal día, B(3)=jornal hora; resto → base_num(n).
                $arg0 = $args[0] ?? null;
                if ($arg0 !== null && $arg0['t'] === 'num') {
                    $n = (int) $arg0['v'];
                    $directo = [
                        1 => 'empleado.sueldo_basico',
                        2 => 'empleado.jornal_dia',
                        3 => 'empleado.jornal_hora',
                    ];
                    if (isset($directo[$n])) {
                        return ['t' => 'var', 'nombre' => $directo[$n]];
                    }
                }
                $this->resultado->agregarAdvertencia('«B(n)» → «base_num(n)»: n=1/2/3 = básico/jornal día/hora; resto por código de nombrebase.');

                return ['t' => 'call', 'nombre' => 'base_num', 'args' => $args];
        }

        // No debería llegar acá.
        return ['t' => 'call', 'nombre' => strtolower($nombre), 'args' => $args];
    }

    /**
     * @return array<string, mixed>
     */
    private function traducirVariable(string $nombre): array
    {
        // 1) Temporal inlineable (_V1, _R, V1, ...)
        if (isset($this->temporales[$nombre])) {
            return $this->temporales[$nombre]; // PHP copia el array por valor
        }
        // Anita mezcla V5 y _V5 en el mismo concepto.
        if (! str_starts_with($nombre, '_') && isset($this->temporales['_'.$nombre])) {
            return $this->temporales['_'.$nombre];
        }
        if (str_starts_with($nombre, '_') && isset($this->temporales[substr($nombre, 1)])) {
            return $this->temporales[substr($nombre, 1)];
        }

        // 2) Variable Anita conocida
        $map = AnitaFuncionMapa::variable($nombre);
        if ($map !== null) {
            if (! in_array($nombre, $this->resultado->variablesUsadas, true)) {
                $this->resultado->variablesUsadas[] = $nombre;
            }
            if (! $map['exacto']) {
                $nota = $map['nota'] ?? '';
                $this->resultado->agregarAdvertencia("Variable «{$nombre}» → «{$map['erp']}» (aprox): {$nota}");
            }
            if ($map['tipo'] === 'acum') {
                return ['t' => 'call', 'nombre' => 'acum', 'args' => [['t' => 'txt', 'v' => $map['erp']]]];
            }
            if ($map['tipo'] === 'var') {
                return ['t' => 'var', 'nombre' => $map['erp']];
            }
            // crudo: parsear el snippet ERP
            return $this->motorErp->compilar($map['erp']);
        }

        // 3) Temporal no definido (Anita lo trata como 0)
        if (str_starts_with($nombre, '_')) {
            $this->resultado->agregarAdvertencia("Variable temporal «{$nombre}» usada sin asignación previa: se asume 0.");

            return ['t' => 'num', 'v' => 0.0];
        }

        // 4) Variable Anita sin equivalente
        if (! in_array($nombre, $this->resultado->variablesUsadas, true)) {
            $this->resultado->variablesUsadas[] = $nombre;
        }
        $this->resultado->agregarNoTraducible("Variable Anita sin equivalente: {$nombre}");

        return ['t' => 'var', 'nombre' => 'anita_'.strtolower($nombre)];
    }

    // ---------------------------------------------------------------------
    // Helpers de tokens
    // ---------------------------------------------------------------------

    /**
     * @return array{t: string, v: string, pos: int}
     */
    private function actual(): array
    {
        return $this->tokens[$this->pos];
    }

    private function avanzar(): void
    {
        $this->pos++;
    }

    private function esOp(string $op): bool
    {
        $tk = $this->actual();

        return $tk['t'] === AnitaLexer::OP && $tk['v'] === $op;
    }

    private function esPalabra(string $palabra): bool
    {
        $tk = $this->actual();

        return $tk['t'] === AnitaLexer::ID && strtoupper($tk['v']) === $palabra;
    }

    private function esperar(string $tipo, string $lexema): void
    {
        $tk = $this->actual();
        if ($tk['t'] !== $tipo) {
            throw FormulaException::sintaxis("se esperaba '{$lexema}' en fórmula Anita", $tk['pos']);
        }
        $this->avanzar();
    }
}
